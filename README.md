# Leafr flipbook (mod_leafr)

Leafr is a Moodle activity that shows a PDF document as a book students can leaf through, directly inside the course. No external service, no extra login: the PDF is stored in Moodle and rendered in the browser.

## Features

- **Book view** with page-turning animation (two-page spread on large screens, single pages on phones and for landscape documents).
- **Simple view** without animation: all pages below each other, scrollable, with the text of each page available to screen readers. It is used automatically when the operating system asks for reduced motion and can be switched on at any time; the choice is remembered.
- **Continue reading**: the document opens at the page the student read last.
- **Completion**: the activity can be completed when the last page, a percentage of all pages or a specific page has been viewed.
- **Table of contents** built from the bookmarks (outline) of the PDF.
- **Zoom** (50 % to 300 %) with drag-to-pan, **full screen**, page number input and progress bar.
- **Keyboard shortcuts** while the reader has the focus: arrow keys, Page Up/Down, Home/End, `+`/`-`, `T` (table of contents), `F` (full screen), `?` (help).
- Optional **download** of the original PDF (per activity setting and capability `mod/leafr:download`).
- Backup and restore, course reset, Privacy API (GDPR), events for page views.

## Requirements

- Moodle 4.2 or later (tested with 4.2, 4.5, 5.0 and 5.1)
- PHP 8.1 or later

## Installation

1. Download the ZIP file or clone this repository into the folder `mod/leafr` of your Moodle installation.
2. Log in as administrator and follow the upgrade notification, or run `php admin/cli/upgrade.php`.

## Usage

Add the activity "Leafr flipbook" to a course, upload a PDF file and save. In the section "Completion conditions" you can require that students view the last page, a percentage of the pages or a specific page.

The number of pages is determined by the browser when the document is opened for the first time.

## Privacy

Leafr stores, per user and activity, the pages that were viewed and the last reading position, plus one user preference for the simple view. All data can be exported and deleted with the Moodle privacy tools.

## Third-party libraries

| Library | Version | License |
|---|---|---|
| [PDF.js](https://github.com/mozilla/pdf.js) | 3.11.174 | Apache 2.0 |
| [StPageFlip](https://github.com/Nodlik/StPageFlip) | 2.0.7 | MIT |

PDF.js runs with `isEvalSupported: false`, which prevents code execution from PDF files (CVE-2024-4367).

## Development

The JavaScript sources are in `amd/src` and are built with Moodle's Grunt (`grunt amd` inside `mod/leafr`). Tests:

- PHPUnit: `vendor/bin/phpunit --testsuite mod_leafr_testsuite`
- Behat: `vendor/bin/behat --tags=@mod_leafr`

## Bug reports

Please use the [issue tracker](https://github.com/peterpleimfeldner-design/moodle-mod_leafr/issues).

## License

2026 Peter Pleimfeldner

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
