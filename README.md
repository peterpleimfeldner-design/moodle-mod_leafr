# Leafr flipbook (mod_leafr)

Leafr is a Moodle activity that turns a PDF document into a book students can leaf through, directly inside the course — no external service, no extra login. The PDF stays in Moodle, is rendered in the browser, and every reading action (page turned, bookmark set, document read) is tracked the same way as any other Moodle activity.

## What Leafr is for

Moodle already lets you attach a PDF as a plain file. Leafr is for the cases where that is not enough:

- You want to know **who actually read** the document, not just who downloaded it — completion by last page, by a percentage of pages, or by specific required pages and chapters.
- The PDF is long enough that **navigation** matters: a table of contents, page thumbnails, full-text search and bookmarks with notes, instead of one long scroll or endless Ctrl+F.
- You need students to **actively confirm** they have read something (a policy, a safety handbook, terms of a placement) rather than infer it from a download timestamp.
- You want a **teacher overview** of who has completed the reading and who hasn't — without collecting reading times or a log of every page view, which Leafr deliberately leaves out.

If none of that applies and a plain file or a "Resource" activity is enough for your PDF, you don't need Leafr — and that's a fine outcome.

## Why a book, not a scroll

The page-turning book view exists because for longer structured documents (handbooks, manuals, multi-chapter guides), page-by-page navigation with a visible table of contents mirrors how people already read printed material, and it gives you a natural, countable unit ("page 12 of 40") to hang completion tracking on. It is not required, though: a **simple view** (all pages below each other, scrollable, fully accessible to screen readers) is available at any time and is used automatically when the browser requests reduced motion.

## Screenshots

| | |
|---|---|
| ![Sidebar with page thumbnails, read/required-page markers](docs/screenshots/sidebar-thumbnails.png) | ![Sidebar with the table of contents](docs/screenshots/sidebar-contents.png) |
| Sidebar: page thumbnails, with a checkmark for pages already read and a dot for required pages | Sidebar: table of contents, automatic from the PDF or defined by the teacher, required chapters marked |
| ![Sidebar with full-text search results](docs/screenshots/sidebar-search.png) | ![Sidebar with a bookmark and note](docs/screenshots/sidebar-bookmarks.png) |
| Sidebar: full-text search with highlighted matches | Sidebar: bookmarks with a personal note |
| ![View menu with page layout and zoom options](docs/screenshots/view-menu.png) | ![Read confirmation card](docs/screenshots/confirm-card.png) |
| View menu: page layout, simple view, zoom, full screen | Read confirmation, shown once the required pages have been read |
| ![Activity settings form with completion conditions](docs/screenshots/settings-form.png) | ![Teacher overview report with group filter](docs/screenshots/overview-report.png) |
| Activity settings: required pages/chapters combined with the read confirmation | Overview report for teachers, with group filter and CSV export |

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
- **Overview report**: per participant only completion, required pages read and the confirmation date, with group filter (respecting separate groups) and CSV export. Reading times and individual page views are deliberately not shown.
- **Manual chapters** for PDFs without an outline, or instead of the PDF's own outline.
- Optional **download** of the original PDF (per activity setting and capability `mod/leafr:download`).
- Backup and restore, duplication, course reset, Privacy API (GDPR), events for page views.

## Requirements

- Moodle 4.5 to 5.2 (tested with 4.5, 5.0, 5.1 and 5.2)
- PHP 8.1 or later

## Installation

1. Download the ZIP file or clone this repository into the folder `mod/leafr` of your Moodle installation.
2. Log in as administrator and follow the upgrade notification, or run `php admin/cli/upgrade.php`.

## Usage

Add the activity "Leafr flipbook" to a course, upload a PDF file and save. Optionally define chapters, the start page and whether the PDF may be downloaded. Under "Completion conditions" choose which pages must be viewed and whether a read confirmation is required.

The number of pages is determined by the browser when the document is opened for the first time.

## Privacy

Leafr stores, per user and activity, the pages that were viewed and the last reading position, the bookmarks (page number and note), the time of a read confirmation, and two user preferences (simple view, page layout). All of this can be exported and deleted with the Moodle privacy tools. The first view of each page is also written to the standard Moodle log as an event, subject to the site's log retention settings. The browser additionally remembers locally that the one-time full screen tip has been shown. No data is sent to external services.

## FAQ

**Why no Word or PowerPoint support?**
Leafr renders PDF with [PDF.js](https://github.com/mozilla/pdf.js), which is what gives it page-accurate navigation, text search and a stable page count to base completion tracking on. Word and PowerPoint files don't have a fixed page layout across devices, so there is no reliable "page" to track. If your material is in Word or PowerPoint, export it to PDF first — every common office suite can do this.

**Why isn't there a paid "Pro" version?**
There isn't one, and none is planned. Leafr is licensed under the GPLv3 (see [LICENSE](LICENSE)); everything in this repository is the whole plugin, with no license key, no feature gate and no hosted component. What you see here is what you get.

**What data does Leafr store, and does anything leave my Moodle site?**
Only what's listed under [Privacy](#privacy) above, and it stays inside your Moodle installation and database — Leafr does not call out to any external service, analytics tool or API. Everything a student can see about their own data, and everything an admin can export or delete, goes through Moodle's standard Privacy API.

**Can teachers see how long a student spent reading, or which pages they lingered on?**
No, deliberately not. The overview report shows only whether the activity is complete, which required pages have been read, and the read-confirmation date. Leafr does not build a page-by-page timeline of student behaviour.

**Is there a cloud or hosted version of Leafr?**
No. Leafr is a normal Moodle plugin that runs entirely on your own Moodle server, under whatever hosting and backup policy that server already has.

**Can I add more than one Leafr activity to a course?**
Yes — like any Moodle activity, add as many instances as you need, each with its own PDF and its own completion settings.

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
