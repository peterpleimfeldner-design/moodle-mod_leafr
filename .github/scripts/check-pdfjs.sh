#!/usr/bin/env bash
# Checks that the bundled PDF.js is consistent: the same version in thirdpartylibs.xml, in
# PDFJS_VERSION (amd/src/pdf.js), inside the library and the worker file, and that every file
# still has the checksum recorded in vendor/pdfjs/readme_moodle.txt (no accidental edits).
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$root"
fail=0

xmlversion="$(awk '/<location>vendor\/pdfjs<\/location>/{f=1} f&&/<version>/{gsub(/.*<version>|<\/version>.*/,"");print;exit}' thirdpartylibs.xml)"
jsversion="$(sed -n "s/^export const PDFJS_VERSION = '\(.*\)';$/\1/p" amd/src/pdf.js)"
echo "thirdpartylibs.xml: $xmlversion, amd/src/pdf.js: $jsversion"
[ -n "$xmlversion" ] && [ "$xmlversion" = "$jsversion" ] || { echo "ERROR: versions differ"; fail=1; }

for f in vendor/pdfjs/pdf.min.js vendor/pdfjs/pdf.worker.min.js; do
    if ! grep -q "pdfjsVersion = \"$xmlversion\"\|version:\"$xmlversion\"\|\"$xmlversion\"" "$f"; then
        echo "ERROR: $f does not contain version $xmlversion"
        fail=1
    fi
done

if ! grep -q "^SHA-256:" vendor/pdfjs/readme_moodle.txt; then
    echo "ERROR: vendor/pdfjs/readme_moodle.txt has no checksums"
    fail=1
else
    (cd vendor/pdfjs && sed -n '/^SHA-256:/,$p' readme_moodle.txt | tail -n +2 | sha256sum --check --quiet) \
        || { echo "ERROR: checksum mismatch in vendor/pdfjs"; fail=1; }
    listed="$(sed -n '/^SHA-256:/,$p' vendor/pdfjs/readme_moodle.txt | tail -n +2 | awk '{sub(/^[*]/, "", $2); print $2}' | LC_ALL=C sort)"
    present="$(cd vendor/pdfjs && find . -type f ! -name readme_moodle.txt | sed 's|^\./||' | LC_ALL=C sort)"
    [ "$listed" = "$present" ] || { echo "ERROR: files in vendor/pdfjs differ from readme_moodle.txt"; fail=1; }
fi

# The amd build must not reference the old RequireJS module name of PDF.js 3.
if grep -rq "pdfjs-dist/build/pdf" amd/src amd/build view.php; then
    echo "ERROR: old PDF.js module name still referenced"
    fail=1
fi

[ "$fail" = 0 ] && echo "PDF.js check passed."
exit "$fail"
