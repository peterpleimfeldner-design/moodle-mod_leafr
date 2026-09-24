# Leafr flipbook (mod_leafr)

Leafr is a Moodle activity that shows a PDF document as a book students can leaf through, directly inside the course. No external service, no extra login: the PDF is stored in Moodle and rendered in the browser.

## Features

For students:

- **Book view** with a realistic page-turning animation: two-page spread on large screens, single pages on phones and for landscape documents, or a fixed layout chosen in the view menu (remembered per user).
- **Simple view** without animation: all pages below each other, scrollable, with the text of each page available to screen readers. It is used automatically when the operating system asks for reduced motion and can be switched on at any time.
- **Continue reading**: the document opens at the page read last.
- **Sidebar** with page thumbnails, contents (the PDF outline or chapters defined by the teacher), bookmarks and full-text search with highlighted matches.
- **Bookmarks with notes**: bookmark any page and add a personal note (up to 500 characters, saved automatically); print the list or save it as PDF.
- **Zoom** (50 % to 300 %), fit to page or width, **full screen**, page number input and progress bar.
- **Keyboard shortcuts** once the document has the focus: arrow keys, Page Up/Down, Home/End, `+`/`-`, `T` (contents), `B` (bookmark), `F` (full screen), `?` (help).
- Works on phones and tablets: swipe to turn pages, the toolbar adapts to the available width.

For teachers:

- **Completion** when the last page, a percentage of the pages, a specific page, or specific pages and chapters (e.g. "1-5, 8, 12-20") have been viewed. Required pages are marked for students.
- **Read confirmation** (optional completion rule): students confirm a statement of your choice after reading.
- **Overview report**: per participant only completion, required pages read and the confirmation date, with group filter and CSV export. Reading times and individual page views are deliberately not recorded.
- **Manual chapters** for PDFs without an outline, or instead of the PDF's own outline.
- Optional **download** of the original PDF (per activity setting and capability `mod/leafr:download`).
- Backup and restore, duplication, course reset, Privacy API (GDPR), events for page views.

## Requirements

- Moodle 4.5 or later (tested with 4.5, 5.0, 5.1 and 5.2)
- PHP 8.1 or later

## Installation

1. Download the ZIP file or clone this repository into the folder `mod/leafr` of your Moodle installation.
2. Log in as administrator and follow the upgrade notification, or run `php admin/cli/upgrade.php`.

## Usage

Add the activity "Leafr flipbook" to a course, upload a PDF file and save. Optionally define chapters, the start page and whether the PDF may be downloaded. Under "Completion conditions" choose which pages must be viewed and whether a read confirmation is required.

The number of pages is determined by the browser when the document is opened for the first time.

## Privacy

Leafr stores, per user and activity, the pages that were viewed and the last reading position, the bookmarks (page number and note), the time of a read confirmation, and two user preferences (simple view, page layout). All of this can be exported and deleted with the Moodle privacy tools. The browser additionally remembers locally that the one-time full screen tip has been shown. No data is sent to external services.

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
