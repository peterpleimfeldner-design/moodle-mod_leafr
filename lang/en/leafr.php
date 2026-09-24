<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English strings for mod_leafr.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addchapters'] = 'Add chapters';
$string['bookmark'] = 'Bookmark';
$string['bookmark_empty'] = 'No bookmarks yet. Add one with the bookmark button in the toolbar or the B key.';
$string['bookmark_note_chars'] = '{$a->used} of {$a->max} characters';
$string['bookmark_note_label'] = 'Note for';
$string['bookmark_note_placeholder'] = 'Add a note …';
$string['bookmark_note_saved'] = 'Saved';
$string['bookmark_page'] = 'Bookmark page {$a}';
$string['bookmark_print'] = 'Print or save as PDF';
$string['bookmark_remove'] = 'Remove bookmark';
$string['chapterno'] = 'Chapter {no}';
$string['chapterpage'] = 'Start page';
$string['chaptersheader'] = 'Chapters';
$string['chaptersintro'] = 'One row per chapter: title and start page. Used for the "Contents" tab when the PDF has no outline of its own, or always if "Always use the manual chapter list" above is enabled. Tip: PDFs exported from Word or PowerPoint usually contain an outline automatically if the headings use heading styles and the option to create bookmarks from headings is enabled when exporting; then this manual list is usually not needed.';
$string['chaptertitle'] = 'Chapter title';
$string['close'] = 'Close';
$string['completion_done'] = 'Well done! You have read enough of this document to complete the activity.';
$string['completion_lastpage'] = 'The last page must be viewed';
$string['completion_percent'] = 'A percentage of all pages must be viewed';
$string['completion_specificpage'] = 'A specific page must be viewed';
$string['completion_specificrange'] = 'Specific pages or chapters must be viewed';
$string['completionchapters'] = 'Or select required chapters';
$string['completionchapters_help'] = 'Selecting chapters here adds their pages to "Required pages" above once the settings are saved. Only chapters already saved in the "Chapters" section are listed.';
$string['completiondetail:lastpage'] = 'View the last page';
$string['completiondetail:page'] = 'View page {$a}';
$string['completiondetail:percent'] = 'View {$a}% of the pages';
$string['completiondetail:range'] = 'View pages {$a}';
$string['completionpage'] = 'Page number';
$string['completionpages'] = 'Required pages';
$string['completionpages_help'] = 'Page numbers or ranges that must all be viewed, e.g. "1-5, 8, 12-20". You can also select whole chapters below instead of typing page numbers.';
$string['completionpageseen'] = 'Require page views';
$string['completionpageseen_desc'] = 'Students must view pages of the document';
$string['completionpercent'] = 'Percentage of pages';
$string['completiontype'] = 'Pages to view';
$string['continuenotice'] = 'Continue on page {$a}';
$string['displaysettings'] = 'Display';
$string['document'] = 'Document';
$string['download'] = 'Download PDF';
$string['downloadallowed'] = 'Allow download';
$string['downloadallowed_help'] = 'If enabled, students with the capability "Download the PDF" can download the original PDF file.';
$string['error_invalidpage'] = 'Please enter a page number of 1 or higher.';
$string['error_invalidpagerange'] = 'Please enter valid page numbers or ranges, e.g. "1-5, 8, 12-20", or select at least one chapter.';
$string['error_invalidpercent'] = 'Please enter a percentage between 1 and 100.';
$string['errordocument'] = 'The document could not be loaded.';
$string['eventpageviewed'] = 'Page viewed';
$string['firstpage'] = 'First page';
$string['fit_page'] = 'Fit page';
$string['fit_width'] = 'Fit width';
$string['fitmode'] = 'Zoom fit';
$string['fullscreen_enter'] = 'Full screen';
$string['fullscreen_exit'] = 'Exit full screen';
$string['fullscreentip'] = 'Tip: Full screen gives you more room to read (press F).';
$string['gotopage'] = 'Go to page';
$string['help'] = 'Keyboard shortcuts';
$string['help_bookmark'] = 'Bookmark or remove the bookmark of the current page';
$string['help_close'] = 'Close a dialog or the sidebar, or leave full screen';
$string['help_firstlast'] = 'First or last page';
$string['help_fullscreen'] = 'Turn full screen on or off';
$string['help_help'] = 'Show this help';
$string['help_intro'] = 'The shortcuts work once you have clicked into the document.';
$string['help_nextprev'] = 'Next or previous page';
$string['help_toc'] = 'Show or hide the table of contents';
$string['help_zoom'] = 'Zoom in or out';
$string['initialpage'] = 'Start page';
$string['initialpage_help'] = 'The page shown when a student opens the document for the first time. Afterwards the document opens at the page the student last read.';
$string['lastpage'] = 'Last page';
$string['leafr:addinstance'] = 'Add a new Leafr flipbook';
$string['leafr:download'] = 'Download the PDF';
$string['leafr:view'] = 'View Leafr flipbook';
$string['leafr:viewreport'] = 'View the Leafr reading report';
$string['leafrname'] = 'Name';
$string['loading'] = 'Loading document …';
$string['matchofmatches'] = '{$a->index} of {$a->total}';
$string['modulename'] = 'Leafr flipbook';
$string['modulename_help'] = 'The Leafr flipbook shows a PDF document as a book that students leaf through directly in the course, with a realistic page-turning animation.

Students continue where they stopped last time, can search the text, jump to chapters via the table of contents or the page thumbnails, and set bookmarks with personal notes.

The activity can be completed automatically when the last page, a percentage of the pages, a specific page or selected pages and chapters have been viewed; a read confirmation can be required in addition. A simple, scrollable view without page-turning animation is available for accessibility.';
$string['modulenameplural'] = 'Leafr flipbooks';
$string['nextpage'] = 'Next page';
$string['nonewmodules'] = 'There are no Leafr flipbooks in this course.';
$string['nopdfuploaded'] = 'No PDF file has been added to this activity yet.';
$string['pagelabel'] = 'Page {$a}';
$string['pageofpages'] = 'Page {$a->page} of {$a->total}';
$string['pageprogress'] = 'Reading progress';
$string['pagesofpages'] = 'Pages {$a->first} and {$a->last} of {$a->total}';
$string['pdffile'] = 'PDF file';
$string['pdffile_help'] = 'The PDF document shown as a flipbook. The maximum file size depends on the settings of the site.';
$string['pluginadministration'] = 'Leafr flipbook administration';
$string['pluginname'] = 'Leafr flipbook';
$string['previouspage'] = 'Previous page';
$string['privacy:bookmarkssubcontext'] = 'Bookmarks';
$string['privacy:metadata:leafr_bookmarks'] = 'The bookmarks of a user in a Leafr flipbook.';
$string['privacy:metadata:leafr_bookmarks:note'] = 'The note added to the bookmark.';
$string['privacy:metadata:leafr_bookmarks:pageno'] = 'The bookmarked page.';
$string['privacy:metadata:leafr_bookmarks:timecreated'] = 'The time the bookmark was created.';
$string['privacy:metadata:leafr_bookmarks:timemodified'] = 'The time the bookmark was last updated.';
$string['privacy:metadata:leafr_bookmarks:userid'] = 'The ID of the user.';
$string['privacy:metadata:leafr_progress'] = 'The reading progress of a user in a Leafr flipbook.';
$string['privacy:metadata:leafr_progress:lastpage'] = 'The page the user read last.';
$string['privacy:metadata:leafr_progress:seenpages'] = 'The pages the user has viewed.';
$string['privacy:metadata:leafr_progress:timemodified'] = 'The time the progress was last updated.';
$string['privacy:metadata:leafr_progress:userid'] = 'The ID of the user.';
$string['privacy:metadata:preference:simpleview'] = 'Whether the user prefers the simple view without page-turning animation.';
$string['privacy:metadata:preference:spreadmode'] = 'Whether the user prefers single pages, double pages or automatic switching in the flipbook view.';
$string['progresssummary'] = '{$a->seen} of {$a->total} pages read';
$string['readerlabel'] = 'Flipbook: {$a}';
$string['reload'] = 'Reload page';
$string['required_badge'] = 'Required';
$string['requiredsummary'] = '{$a->seen} of {$a->total} required pages read';
$string['resetbookmarks'] = 'Delete the bookmarks of all users';
$string['resetprogress'] = 'Delete the reading progress of all users';
$string['restartreading'] = 'Start from the beginning';
$string['search_hint'] = 'Type a word and press Enter.';
$string['search_indexing'] = 'Preparing search …';
$string['search_label'] = 'Search text';
$string['search_next'] = 'Next match';
$string['search_noresults'] = 'No matches found.';
$string['search_noresults_scan'] = 'This document does not contain searchable text.';
$string['search_placeholder'] = 'Search in document';
$string['search_prev'] = 'Previous match';
$string['showtoc'] = 'Show table of contents';
$string['showtoc_help'] = 'Shows the PDF\'s own outline (the chapter structure stored in the file, often called "bookmarks" in PDF programs) in the "Contents" tab of the sidebar. If the PDF has no outline, the chapters entered in the "Chapters" section are used.';
$string['sidebar'] = 'Sidebar';
$string['simpleview'] = 'Simple view';
$string['spread_auto'] = 'Automatic';
$string['spread_double'] = 'Double page';
$string['spread_single'] = 'Single page';
$string['spreadmode'] = 'Page layout';
$string['subplugintype_leafrtool'] = 'Leafr tool';
$string['subplugintype_leafrtool_plural'] = 'Leafr tools';
$string['tab_bookmarks'] = 'Bookmarks';
$string['tab_contents'] = 'Contents';
$string['tab_search'] = 'Search';
$string['tab_thumbnails'] = 'Thumbnails';
$string['thumbs_legend_required'] = 'Required page';
$string['thumbs_legend_seen'] = 'Read';
$string['toc_empty'] = 'This PDF does not contain a table of contents.';
$string['toolbar'] = 'Reader controls';
$string['totalpages'] = 'of {$a}';
$string['usemanualchapters'] = 'Always use the manual chapter list';
$string['usemanualchapters_help'] = 'By default the automatic outline of the PDF is used for the "Contents" tab if there is one, and the manual list above is only used as a fallback when the PDF has none. Enable this to always use the manual list instead.';
$string['viewmenu'] = 'View';
$string['zoomheading'] = 'Zoom';
$string['zoomin'] = 'Zoom in';
$string['zoomlevel'] = 'Zoom: {$a}%';
$string['zoomout'] = 'Zoom out';
$string['zoomreset'] = 'Reset zoom';
