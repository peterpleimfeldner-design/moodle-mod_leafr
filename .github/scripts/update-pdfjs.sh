#!/usr/bin/env bash
# Replaces vendor/pdfjs with the legacy build of an official pdfjs-dist release from npm.
#
# Usage: bash .github/scripts/update-pdfjs.sh <version>     (for example 6.4.299)
#
# The files are copied unmodified. Only the extension .mjs is changed to .js, because web
# servers do not always know the .mjs MIME type and browsers refuse module scripts and module
# workers without a JavaScript MIME type. vendor/pdfjs/readme_moodle.txt records the source and
# the SHA-256 checksums, which .github/scripts/check-pdfjs.sh verifies.
set -euo pipefail

version="${1:?Usage: $0 <pdfjs-dist version>}"
root="$(cd "$(dirname "$0")/../.." && pwd)"
target="$root/vendor/pdfjs"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

(cd "$work" && npm pack "pdfjs-dist@$version" --silent >/dev/null && tar xzf "pdfjs-dist-$version.tgz")
pkg="$work/package"
grep -q "\"version\": \"$version\"" "$pkg/package.json" || { echo "Unexpected package version"; exit 1; }

rm -rf "$target"
mkdir -p "$target/wasm"
cp "$pkg/LICENSE" "$target/LICENSE"
cp "$pkg/legacy/build/pdf.min.mjs" "$target/pdf.min.js"
cp "$pkg/legacy/build/pdf.worker.min.mjs" "$target/pdf.worker.min.js"
# Image decoders used by the worker (JBIG2, JPEG 2000, ICC colour profiles) and their
# JavaScript fallbacks. quickjs-eval is only needed for PDF scripting, which Leafr does not enable.
for f in jbig2.wasm jbig2_nowasm_fallback.js openjpeg.wasm openjpeg_nowasm_fallback.js qcms_bg.wasm \
        LICENSE_JBIG2 LICENSE_OPENJPEG LICENSE_PDFJS_JBIG2 LICENSE_PDFJS_OPENJPEG LICENSE_PDFJS_QCMS LICENSE_QCMS; do
    cp "$pkg/wasm/$f" "$target/wasm/$f"
done

{
    echo "PDF.js $version (Mozilla, Apache-2.0), legacy build from the npm package pdfjs-dist."
    echo "Source: https://registry.npmjs.org/pdfjs-dist/-/pdfjs-dist-$version.tgz"
    echo "Project: https://github.com/mozilla/pdf.js"
    echo
    echo "Update: bash .github/scripts/update-pdfjs.sh <version>, then set the same version in"
    echo "thirdpartylibs.xml and PDFJS_VERSION in amd/src/pdf.js, rebuild the AMD modules and run"
    echo "bash .github/scripts/check-pdfjs.sh."
    echo
    echo "The files are unmodified. Renamed: legacy/build/pdf.min.mjs -> pdf.min.js,"
    echo "legacy/build/pdf.worker.min.mjs -> pdf.worker.min.js (servers do not always send a"
    echo "JavaScript MIME type for .mjs). wasm/ is a subset of the package's wasm/ directory."
    echo
    echo "SHA-256:"
    (cd "$target" && find . -type f ! -name readme_moodle.txt | sed 's|^\./||' | LC_ALL=C sort | xargs sha256sum)
} > "$target/readme_moodle.txt"

echo "vendor/pdfjs now contains PDF.js $version."
