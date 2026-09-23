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

$string['close'] = 'Close';
$string['completion_done'] = 'Well done! You have read enough of this document to complete the activity.';
$string['completion_lastpage'] = 'The last page must be viewed';
$string['completion_percent'] = 'A percentage of all pages must be viewed';
$string['completion_specificpage'] = 'A specific page must be viewed';
$string['completiondetail:lastpage'] = 'View the last page';
$string['completiondetail:page'] = 'View page {$a}';
$string['completiondetail:percent'] = 'View {$a}% of the pages';
$string['completionpage'] = 'Page number';
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
$string['error_invalidpercent'] = 'Please enter a percentage between 1 and 100.';
$string['errordocument'] = 'The document could not be loaded.';
$string['eventpageviewed'] = 'Page viewed';
$string['firstpage'] = 'First page';
$string['fullscreen_enter'] = 'Full screen';
$string['fullscreen_exit'] = 'Exit full screen';
$string['gotopage'] = 'Go to page';
$string['help'] = 'Keyboard shortcuts';
$string['help_close'] = 'Close dialog, table of contents or full screen';
$string['help_firstlast'] = 'First or last page';
$string['help_fullscreen'] = 'Full screen on or off';
$string['help_help'] = 'Show this help';
$string['help_intro'] = 'The shortcuts work when the reader has the focus.';
$string['help_nextprev'] = 'Next or previous page';
$string['help_toc'] = 'Table of contents on or off';
$string['help_zoom'] = 'Zoom in or out';
$string['initialpage'] = 'Start page';
$string['initialpage_help'] = 'The page shown when a student opens the document for the first time. Afterwards the document opens at the page the student last read.';
$string['lastpage'] = 'Last page';
$string['leafr:addinstance'] = 'Add a new Leafr flipbook';
$string['leafr:download'] = 'Download the PDF';
$string['leafr:view'] = 'View Leafr flipbook';
$string['leafrname'] = 'Name';
$string['loading'] = 'Loading document …';
$string['matchofmatches'] = '{$a->index} of {$a->total}';
$string['modulename'] = 'Leafr flipbook';
$string['modulename_help'] = 'The Leafr flipbook shows a PDF document as a book that students can leaf through directly in the course.

Students continue reading where they stopped last time. The activity can be completed automatically when the last page, a certain percentage of the pages or a specific page has been viewed.

A simple, scrollable view without page-turning animation is available for accessibility.';
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
$string['privacy:metadata:leafr_progress'] = 'The reading progress of a user in a Leafr flipbook.';
$string['privacy:metadata:leafr_progress:lastpage'] = 'The page the user read last.';
$string['privacy:metadata:leafr_progress:seenpages'] = 'The pages the user has viewed.';
$string['privacy:metadata:leafr_progress:timemodified'] = 'The time the progress was last updated.';
$string['privacy:metadata:leafr_progress:userid'] = 'The ID of the user.';
$string['privacy:metadata:preference:simpleview'] = 'Whether the user prefers the simple view without page-turning animation.';
$string['progresssummary'] = '{$a->seen} of {$a->total} pages read';
$string['readerlabel'] = 'Flipbook: {$a}';
$string['reload'] = 'Reload page';
$string['resetprogress'] = 'Delete the reading progress of all users';
$string['restartreading'] = 'Start from the beginning';
$string['search_indexing'] = 'Preparing search …';
$string['search_label'] = 'Search text';
$string['search_next'] = 'Next match';
$string['search_noresults'] = 'No matches found.';
$string['search_noresults_scan'] = 'This document does not contain searchable text.';
$string['search_placeholder'] = 'Search in document';
$string['search_prev'] = 'Previous match';
$string['search_resultcount'] = '{$a} matches';
$string['showtoc'] = 'Show table of contents';
$string['showtoc_help'] = 'Shows the bookmarks (outline) of the PDF as a table of contents. The PDF must contain bookmarks, which most programs can create when exporting to PDF.';
$string['sidebar'] = 'Sidebar';
$string['simpleview'] = 'Simple view';
$string['tab_contents'] = 'Contents';
$string['tab_search'] = 'Search';
$string['tab_thumbnails'] = 'Thumbnails';
$string['toc_empty'] = 'This PDF does not contain a table of contents.';
$string['toolbar'] = 'Reader controls';
$string['totalpages'] = 'of {$a}';
$string['zoomin'] = 'Zoom in';
$string['zoomlevel'] = 'Zoom: {$a}%';
$string['zoomout'] = 'Zoom out';
$string['zoomreset'] = 'Reset zoom';
