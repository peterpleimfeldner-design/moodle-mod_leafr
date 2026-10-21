#!/usr/bin/env bash
# Builds the plugin ZIP for the Moodle plugins directory and checks the finished ZIP.
#
# Usage: .github/scripts/build-release-zip.sh <tag-or-ref> [output.zip]
#   e.g. .github/scripts/build-release-zip.sh v1.2.1
#
# The ZIP is built with "git archive" from the given ref (never from the working tree), with
# everything in a "leafr/" folder; files marked export-ignore in .gitattributes (.github, ...)
# stay out. The checks run on the unpacked ZIP, i.e. on exactly what would be uploaded.
# Exit code 0 = package ok, 1 = at least one problem found, 2 = wrong usage.

set -u

ref="${1:-}"
if [ -z "$ref" ]; then
    echo "Usage: $0 <tag-or-ref> [output.zip]" >&2
    exit 2
fi
if [ -n "${2:-}" ]; then
    out="$2"
else
    name="${ref#v}"
    out="mod_leafr_${name//\//_}.zip"   # A ref like release/1.2.1 must not become a sub folder.
fi

if ! git rev-parse -q --verify "$ref^{commit}" >/dev/null; then
    echo "ERROR: '$ref' is not a known tag, branch or commit in this repository." >&2
    exit 2
fi

echo "Building $out from $ref ($(git rev-parse --short "$ref^{commit}"))"
rm -f "$out"
if ! git archive --format=zip --prefix=leafr/ -o "$out" "$ref"; then
    echo "ERROR: git archive failed." >&2
    exit 1
fi

errors=0
fail() {
    echo "FAIL: $*"
    errors=$((errors + 1))
}

# Unpack into a temporary folder that is removed again at the end.
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
if ! unzip -q "$out" -d "$tmp"; then
    echo "ERROR: the ZIP could not be unpacked." >&2
    exit 1
fi
root="$tmp/leafr"
names="$(unzip -Z1 "$out")"

# 1. Exactly one top-level folder "leafr".
top="$(printf '%s\n' "$names" | cut -d/ -f1 | sort -u)"
[ "$top" = "leafr" ] || fail "top-level folder must be only 'leafr', found: $(echo $top)"

# 2. LICENSE in the plugin's main folder, not empty, GPL text.
if [ ! -s "$root/LICENSE" ]; then
    fail "leafr/LICENSE is missing or empty (required by the Moodle plugins directory)"
elif ! grep -q "GNU GENERAL PUBLIC LICENSE" "$root/LICENSE"; then
    fail "leafr/LICENSE does not look like the GNU GPL"
fi

# 3. Required plugin files.
for f in version.php lib.php lang/en/leafr.php db/install.xml db/access.php thirdpartylibs.xml; do
    [ -s "$root/$f" ] || fail "required file leafr/$f is missing or empty"
done

# 4. Every <location> named in thirdpartylibs.xml exists.
if [ -f "$root/thirdpartylibs.xml" ]; then
    locations="$(sed -n 's:.*<location>\(.*\)</location>.*:\1:p' "$root/thirdpartylibs.xml")"
    for loc in $locations; do
        [ -e "$root/$loc" ] || fail "thirdpartylibs.xml names '$loc', but it is not in the ZIP"
    done
fi

# 5. Nothing internal in the ZIP, and no Windows path separators.
bad="$(printf '%s\n' "$names" | grep -E '(^|/)(\.git|\.github|node_modules)(/|$)')"
[ -z "$bad" ] || fail "internal folders in the ZIP: $(printf '%s\n' "$bad" | head -3 | tr '\n' ' ')"
backslash="$(printf '%s\n' "$names" | grep -F '\')"
[ -z "$backslash" ] || fail "paths with a backslash in the ZIP: $(printf '%s\n' "$backslash" | head -3 | tr '\n' ' ')"

# 6. For a release tag (v1.2.1): release and maturity in version.php must match.
if [ -f "$root/version.php" ]; then
    release="$(grep -o "plugin->release *= *'[^']*'" "$root/version.php" | cut -d"'" -f2)"
    maturity="$(grep -o 'plugin->maturity *= *[A-Z_]*' "$root/version.php" | grep -o '[A-Z_]*$')"
    echo "version.php: release '$release', maturity $maturity"
    case "$ref" in
        v[0-9]*)
            [ "$release" = "${ref#v}" ] || fail "tag $ref does not match \$plugin->release '$release'"
            [ "$maturity" = "MATURITY_STABLE" ] || fail "tag $ref, but \$plugin->maturity is $maturity, not MATURITY_STABLE"
            ;;
        *)
            echo "NOTE: '$ref' is not a release tag (v1.2.3), version and maturity check skipped."
            ;;
    esac
fi

if [ "$errors" -gt 0 ]; then
    echo "PACKAGE CHECK FAILED: $errors problem(s) in $out - do not upload this ZIP."
    exit 1
fi

files="$(printf '%s\n' "$names" | grep -vc '/$')"
size="$(wc -c < "$out" | tr -d ' ')"
echo "PACKAGE CHECK PASSED: $out"
echo "  files:  $files"
echo "  size:   $size bytes"
echo "  sha256: $(sha256sum "$out" | cut -d' ' -f1)"
