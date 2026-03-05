<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ── Plugin metadata ────────────────────────────────────────────────────────────
$string['modulename']          = 'Leafr Flipbook';
$string['modulenameplural']    = 'Leafr Flipbooks';
$string['modulename_help']     = 'The Leafr Flipbook activity lets you present PDF documents as an interactive page-flip book directly within Moodle.';
$string['pluginadministration'] = 'Leafr administration';
$string['pluginname']          = 'Leafr Flipbook';

// ── Form fields ───────────────────────────────────────────────────────────────
$string['leafrname']           = 'Activity name';
$string['pdffile']             = 'PDF file';
$string['pdffile_help']        = 'Upload a PDF document. It will be displayed as an interactive flipbook. Maximum file size depends on your site configuration.';
$string['displaysettings']     = 'Display settings';
$string['showtoc']             = 'Show table of contents';
$string['downloadallowed']     = 'Allow PDF download';
$string['downloadallowed_help'] = 'When enabled, students can download the original PDF file.';
$string['initialpage']         = 'Initial page';
$string['completionsettings']  = 'Completion settings';
$string['completiontype']      = 'Completion trigger';
$string['completionpercent']   = 'Required percentage of pages (%)';
$string['completionpage']      = 'Required page number';

// ── Completion types ──────────────────────────────────────────────────────────
$string['completion_none']           = 'No automatic completion';
$string['completion_lastpage']       = 'Student must reach the last page';
$string['completion_percent']        = 'Student must see a percentage of pages';
$string['completion_specificpage']   = 'Student must reach a specific page';
$string['completionpageseen']        = 'Require page views';
$string['completionpageseen_desc']   = 'Student must view the required pages';
$string['completion_percent_desc']   = 'Student must see {$a}% of pages';
$string['completion_specificpage_desc'] = 'Student must reach page {$a}';

// ── UI strings ────────────────────────────────────────────────────────────────
$string['loading']             = 'Loading document...';
$string['errordocument']       = 'The document could not be loaded.';
$string['nopdfuploaded']       = 'No PDF file has been uploaded yet.';
$string['pageof']              = 'Page {page} of {total}';
$string['toc_title']           = 'Table of Contents';
$string['toc_empty']           = 'No table of contents available.';
$string['simple_view']         = 'Simple view (accessible)';
$string['pro_teaser']          = 'Upgrade to Leafr Pro to unlock bookmarks, analytics, and more.';

// ── Resume reading toast ──────────────────────────────────────────────────────
$string['resume_toast']        = 'You were last on page {page}.';
$string['resume_continue']     = 'Continue reading';
$string['resume_restart']      = 'Start from beginning';

// ── Completion feedback ───────────────────────────────────────────────────────
$string['completion_done']     = 'Activity marked as complete!';

// ── Bookmark strings ──────────────────────────────────────────────────────────
$string['bookmark_add']        = 'Add bookmark';
$string['bookmark_saved']      = 'Bookmark saved.';
$string['bookmark_deleted']    = 'Bookmark deleted.';
$string['bookmark_empty']      = 'No bookmarks set yet.';
$string['bookmark_limit']      = 'Maximum of 20 bookmarks per activity reached.';
$string['bookmarknotfound']    = 'Bookmark not found.';
$string['requirespro']         = 'This feature requires Leafr Pro.';

// ── Error strings ─────────────────────────────────────────────────────────────
$string['error_invalidpercent'] = 'Percentage must be between 1 and 100.';
$string['error_invalidpage']    = 'Page number must be at least 1.';

// ── Events ────────────────────────────────────────────────────────────────────
$string['event_course_module_viewed'] = 'Leafr flipbook viewed';
$string['event_page_viewed']          = 'Flipbook page viewed';

// ── Privacy API ───────────────────────────────────────────────────────────────
$string['privacy:metadata:preference:readingpos'] = 'The current reading position (page number) for this Leafr activity.';
$string['privacy:metadata:preference:progress']   = 'Which pages the user has seen in this Leafr activity.';
$string['privacy:metadata:preference:simpleview'] = 'Whether the user has enabled simple (accessible) view for this activity.';
$string['privacy:metadata:leafr_bookmarks']                   = 'Named bookmarks created by the user in a Leafr activity.';
$string['privacy:metadata:leafr_bookmarks:userid']            = 'The ID of the user who created the bookmark.';
$string['privacy:metadata:leafr_bookmarks:pageno']            = 'The page number the bookmark points to.';
$string['privacy:metadata:leafr_bookmarks:label']             = 'The user-defined label for the bookmark.';
$string['privacy:metadata:leafr_bookmarks:note']              = 'An optional note attached to the bookmark.';
$string['privacy:metadata:leafr_bookmarks:timecreated']       = 'Timestamp when the bookmark was created.';
$string['privacy:metadata:leafr_bookmarks:timemodified']      = 'Timestamp when the bookmark was last modified.';

// ── Misc ──────────────────────────────────────────────────────────────────────
$string['nonewmodules']        = 'There are no Leafr flipbook activities in this course.';
