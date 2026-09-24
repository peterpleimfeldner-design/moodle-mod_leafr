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

$originalfile = leafr_get_pdf_file($context);
$file = $originalfile;
$conversion = null;
if ($originalfile && $originalfile->get_mimetype() !== 'application/pdf') {
    $conversion = tool_manager::get_converted_file((int)$leafr->id, $context);
    if ($conversion === null) {
        // No tool has ever noticed this file yet (e.g. after "duplicate activity", which copies
        // the original file but not a leafrtool_office subplugin's own conversion cache): give
        // every tool one more chance to pick it up, then check again.
        tool_manager::handle_content_saved((int)$leafr->id, $context);
        $conversion = tool_manager::get_converted_file((int)$leafr->id, $context);
    }
    $file = ($conversion && $conversion['status'] === 'ready') ? $conversion['file'] : null;
}
if ($file) {
    // $file may be a converted PDF owned by a leafrtool subplugin rather than mod_leafr's own
    // "content" file area, so the URL is built from the file's own identity, not assumed to be
    // mod_leafr's content area; the owning component must serve it via its own _pluginfile().
    $fileurl = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
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
        $bookmarks = json_encode(array_map(
            fn($b) => ['page' => (int)$b->pageno, 'note' => (string)$b->note],
            array_values(bookmarks::get_for_user((int)$leafr->id, (int)$USER->id))
        ));
    }
    $simpleview = get_user_preferences('mod_leafr_simpleview', null);
    $spreadmode = get_user_preferences('mod_leafr_spreadmode', 'auto');
    $requiredpages = $leafr->completiontype == progress::COMPLETION_SPECIFICRANGE
        ? progress::encode_pages(progress::required_pages($leafr))
        : '';
    $manualchapters = json_encode(chapters::decode($leafr->manualchapters ?? null));

    $downloadurl = '';
    if ($leafr->downloadallowed && has_capability('mod/leafr:download', $context)) {
        $downloadfile = tool_manager::get_download_file((int)$leafr->id, $context, $file);
        $downloadurl = moodle_url::make_pluginfile_url(
            $downloadfile->get_contextid(),
            $downloadfile->get_component(),
            $downloadfile->get_filearea(),
            $downloadfile->get_itemid(),
            $downloadfile->get_filepath(),
            $downloadfile->get_filename(),
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

if ($conversion && $conversion['status'] === 'pending') {
    // Simple, JS-independent refresh instead of polling: the background conversion normally only
    // takes a few seconds, and this page is cheap to reload.
    $PAGE->set_periodic_refresh_delay(15);
}

echo $OUTPUT->header();

if ($file) {
    echo $OUTPUT->render_from_template('mod_leafr/reader', $templatecontext);
    echo tool_manager::render_reader($cm, $context, $leafr);
} else if ($conversion && $conversion['status'] === 'pending') {
    echo $OUTPUT->render_from_template('mod_leafr/error', ['message' => get_string('conversionpending', 'leafr')]);
} else if ($conversion && $conversion['status'] === 'failed') {
    echo $OUTPUT->render_from_template('mod_leafr/error', ['message' => get_string('conversionfailed', 'leafr')]);
} else {
    echo $OUTPUT->render_from_template('mod_leafr/error', ['message' => get_string('nopdfuploaded', 'leafr')]);
}

echo $OUTPUT->footer();
