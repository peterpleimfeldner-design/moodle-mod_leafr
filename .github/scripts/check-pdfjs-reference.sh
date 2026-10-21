#!/usr/bin/env bash
# Checks that the bundled PDF.js reads the test PDFs in tests/fixtures exactly as recorded in
# .github/scripts/pdfjs-compare/reference.json: page count, page sizes, resolved outline, text of
# every page and the position of every text item (tolerance 0.5 units). This is what the reader's
# table of contents, search and search highlights rely on. The reference was recorded with
# PDF.js 3.11.174, the version Leafr shipped before 6.4.299, so a PDF.js update that changes any
# of it fails here instead of in a course.
#
# Usage: bash .github/scripts/check-pdfjs-reference.sh            (needs Node.js 22 or later)
#        bash .github/scripts/check-pdfjs-reference.sh --update   (records a new reference; only
#                                                                 after reviewing the differences)
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$root"
dir=.github/scripts/pdfjs-compare
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

# Node needs the module extension; the files are the unmodified .mjs files of the npm package.
cp vendor/pdfjs/pdf.min.js "$work/pdf.mjs"
cp vendor/pdfjs/pdf.worker.min.js "$work/pdf.worker.mjs"
node "$dir/extract.mjs" "$work/pdf.mjs" "$work/current.json" tests/fixtures/*.pdf

if [ "${1:-}" = "--update" ]; then
    cp "$work/current.json" "$dir/reference.json"
    echo "Reference updated: $dir/reference.json"
    exit 0
fi
node "$dir/compare.mjs" "$dir/reference.json" "$work/current.json"
