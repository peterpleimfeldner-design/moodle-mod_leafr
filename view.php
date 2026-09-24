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
 * Shows a Leafr flipbook.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_leafr\local\bookmarks;
use mod_leafr\local\chapters;
use mod_leafr\local\progress;
use mod_leafr\local\tool_manager;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'leafr');
$leafr = $DB->get_record('leafr', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/leafr:view', $context);

leafr_view($leafr, $course, $cm, $context);

$PAGE->set_url('/mod/leafr/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($leafr->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($leafr);

$file = leafr_get_pdf_file($context);
if ($file) {
    $fileurl = moodle_url::make_pluginfile_url(
        $context->id,
        'mod_leafr',
        'content',
        0,
        $file->get_filepath(),
        $file->get_filename()
    );

    $lastpage = 0;
    $completed = false;
    $seenpages = '';
    $bookmarks = '[]';
    if (isloggedin() && !isguestuser()) {
        $lastpage = progress::get_last_page((int)$leafr->id, (int)$USER->id);
        $completed = $leafr->completiontype > 0 && progress::is_complete($leafr, (int)$USER->id);
        $seenpages = progress::encode_pages(progress::get_seen_pages((int)$leafr->id, (int)$USER->id));
        $bookmarklist = [];
        foreach (array_values(bookmarks::get_for_user((int)$leafr->id, (int)$USER->id)) as $bookmark) {
            $bookmarklist[] = ['page' => (int)$bookmark->pageno, 'note' => (string)$bookmark->note];
        }
        $bookmarks = json_encode($bookmarklist);
    }
    $simpleview = get_user_preferences('mod_leafr_simpleview', null);
    $spreadmode = get_user_preferences('mod_leafr_spreadmode', 'auto');
    $requiredpages = $leafr->completiontype == progress::COMPLETION_SPECIFICRANGE
        ? progress::encode_pages(progress::required_pages($leafr))
        : '';
    $manualchapters = json_encode(chapters::decode($leafr->manualchapters ?? null));

    $downloadurl = '';
    if ($leafr->downloadallowed && has_capability('mod/leafr:download', $context)) {
        $downloadurl = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_leafr',
            'content',
            0,
            $file->get_filepath(),
            $file->get_filename(),
            true
        )->out(false);
    }

    // The PDF.js and StPageFlip builds are loaded as AMD modules from the vendor directory.
    // PDF.js registers itself with the fixed name "pdfjs-dist/build/pdf".
    $PAGE->requires->js_amd_inline('require.config({paths: ' . json_encode([
        'pdfjs-dist/build/pdf' => (new moodle_url('/mod/leafr/vendor/pdfjs/pdf.min'))->out(false),
        'mod_leafr/vendor-stpageflip' => (new moodle_url('/mod/leafr/vendor/stpageflip/StPageFlip.browser'))->out(false),
    ], JSON_UNESCAPED_SLASHES) . '});');

    $uniqid = html_writer::random_id('leafr-reader-');
    $templatecontext = [
        'uniqid' => $uniqid,
        'cmid' => $cm->id,
        'readerlabel' => get_string('readerlabel', 'leafr', format_string($leafr->name, true, ['context' => $context])),
        'fileurl' => $fileurl->out(false),
        'downloadurl' => $downloadurl,
        'startpage' => $lastpage ?: max(1, (int)$leafr->initialpage),
        'initialpage' => max(1, (int)$leafr->initialpage),
        'lastpage' => $lastpage,
        'seenpages' => $seenpages,
        'bookmarks' => $bookmarks,
        'bookmarknotemaxlength' => bookmarks::MAX_NOTE_LENGTH,
        'printurl' => (new moodle_url('/mod/leafr/print.php', ['id' => $cm->id]))->out(false),
        'totalpages' => (int)$leafr->totalpages,
        'showtoc' => !empty($leafr->showtoc),
        'simpleview' => $simpleview === null ? '' : (string)(int)$simpleview,
        'spreadmode' => in_array($spreadmode, ['auto', 'single', 'double'], true) ? $spreadmode : 'auto',
        'completed' => $completed,
        'requiredpages' => $requiredpages,
        'manualchapters' => $manualchapters,
        'usemanualchapters' => !empty($leafr->usemanualchapters),
    ];
    $PAGE->requires->js_call_amd('mod_leafr/reader', 'init', ['#' . $uniqid]);
}

echo $OUTPUT->header();

if ($file) {
    echo $OUTPUT->render_from_template('mod_leafr/reader', $templatecontext);
    echo tool_manager::render_reader($cm, $context, $leafr);
} else {
    echo $OUTPUT->render_from_template('mod_leafr/error', ['message' => get_string('nopdfuploaded', 'leafr')]);
}

echo $OUTPUT->footer();
