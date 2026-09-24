# Changelog

## 1.2.0 (unreleased)

### New
- **Sidebar** with four tabs: page thumbnails, contents (PDF outline or manual chapters), bookmarks
  and full-text search with result list, keyboard navigation and highlighted matches.
- **Bookmarks with notes** (back from 1.0.x, rebuilt): any page, including either page of a two-page
  spread, with a personal note of up to 500 characters that is saved automatically. Bookmarks and
  notes can be printed or saved as PDF.
- **Manual chapters**: teachers can define chapters (title and start page) for PDFs without an
  outline, or use them instead of the PDF's own outline.
- **Completion by specific pages or chapters**: page ranges such as "1-5, 8, 12-20" and/or selected
  chapters must be viewed. Required pages are marked in the thumbnails and the contents tab.
- **Read confirmation** (`leafrtool_confirm`): an optional completion rule that asks students to
  confirm with their own statement that they have read the document.
- **Overview for teachers** (`leafrtool_report`): per participant only completion, required pages
  read and the confirmation date, with group filter and CSV export. Reading times and individual
  page views are deliberately not shown.
- **View menu**: page layout (automatic, single page, double page; remembered per user), simple
  view, zoom, fit to page or width, and full screen.
- New activity icon, book-like page shadow, and the theme's primary colour as accent colour when it
  has enough contrast.
- Spanish language pack.
- Group support: the overview can be filtered by group and respects separate groups.
- Extension point for subplugins of type `leafrtool` (see `tool/README.md`).

### Improved
- The toolbar adapts to the actual width of the reader, so all controls stay reachable on phones
  and tablets; the download moves into the view menu on narrow screens.
- With an activity description above the reader, the reader uses the full window height and the
  page scrolls past the description.
- Page turning: animated also when turning back in single-page mode; page corners can be dragged,
  a click along the left or right edge turns the page.
- Touch: vertical swipes scroll a page that is taller than the screen, horizontal swipes turn it; a
  zoomed page can be moved with a finger.
- "Fit to page" and "Fit to width" reset the zoom to 100 %.
- Pages whose shape differs from the first page (e.g. landscape pages in a portrait document) are
  shown in their own proportions instead of being stretched.
- "Double page" is unavailable where no spread is possible (landscape documents, one-page documents,
  narrow screens).
- A one-time tip points out full screen mode.
- The reader only switches to dark colours when the Moodle page itself is dark.
- Chapter settings: one row per chapter.
- Minimum Moodle version 4.5; tested with Moodle 4.5, 5.0, 5.1 and 5.2.

### Fixed
- Hardening: guests cannot store bookmarks or report the page count, bookmarks are limited to
  the pages of the document, notes are plain text, and a read confirmation is only accepted once
  reading is finished.
- The read confirmation now also appears for activities without a page based completion rule
  (after the last page).
- Several layout issues in the view menu, sidebar and toolbar.

## 1.1.0 (2026-09-22)

Complete review and rework of the plugin.

### Fixed
- Web services did not work on Moodle 4.2 and later (now based on `core_external`).
- Reading progress, reading position and the simple view preference were never saved.
- The page based completion rule was never active.
- Backup and restore failed; `totalpages`, `showtoc` and description files were missing from backups.
- PDF.js runs with `isEvalSupported: false` (protection against CVE-2024-4367).
- The `vendor/` libraries were missing from the repository.

### Changed
- Reader rewritten as ES modules built with Moodle's Grunt: pages are rendered only around the
  current page (long PDFs no longer exhaust the browser), sharpness follows screen resolution and
  zoom, zoom from 50 % to 300 % with drag to pan.
- Simple view exposes the text of each page to screen readers; keyboard shortcuts only work while
  the reader has the focus (WCAG 2.1.4); help dialog with `?`.
- All texts moved to language files.

### Removed
- Bookmarks (reintroduced in 1.2.0) and unused web services, capabilities and files.

### Added
- Course reset can delete reading progress; existing progress is migrated on upgrade.
- PHPUnit and Behat tests, GitHub Actions CI.

## 1.0.7 (2026-03-10)
- Fixed: undeclared variables that could stop the flipbook, missing page count in completion
  requests, simple view marking pages as read too early, event listener and observer leaks,
  missing null checks.

## 1.0.3 (2026-03-05)
- Fixed: error on the Moodle dashboard, PDF not loading, missing `totalpages` field.
- Landscape PDFs are shown as single pages.

## 1.0.2 (2026-03-04)
- Fixed: PDF.js and StPageFlip not loading because of an AMD conflict.
- Completion settings moved into Moodle's standard completion section.

## 1.0.0 (2026-03-04)
- Initial release: PDF shown as a flipbook (PDF.js, StPageFlip), continue reading, keyboard and
  touch navigation, simple view, table of contents from the PDF outline, completion by last page,
  percentage or specific page, privacy API, backup and restore.
