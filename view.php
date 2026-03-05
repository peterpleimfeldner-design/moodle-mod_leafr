<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Main entry point for mod_leafr - renders the PDF Flipbook reader.
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT); // Course module ID.

$cm      = get_coursemodule_from_id('leafr', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$leafr   = $DB->get_record('leafr', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/leafr:view', $context);

// Log the course module viewed event.
$event = \mod_leafr\event\course_module_viewed::create([
    'objectid' => $leafr->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('leafr', $leafr);
$event->trigger();

// Mark as viewed for completion tracking.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Get PDF file URL.
$fileurl = leafr_get_file_url($context, $cm->id);

// Get reading position for resume-reading toast.
$savedpage  = leafr_get_reading_position($cm->id);
$startpage  = max(1, (int)($leafr->initialpage ?? 1));
if ($savedpage > 1) {
    $startpage = $savedpage;
}

// Simple View detection (user preference or server-side default).
$simpleview = (bool) get_user_preferences('leafr_simpleview_' . $cm->id, 0);

// Pro features available?
$proavailable = leafr_pro_is_available();

// PAGE setup.
$PAGE->set_url('/mod/leafr/view.php', ['id' => $id]);
$PAGE->set_title(format_string($leafr->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($leafr);

// Configure RequireJS paths for vendor libraries.
// PDF.js 3.x uses a NAMED AMD define: define("pdfjs-dist/build/pdf", ...).
// The path must be registered under that exact name; pdfloader.js requires
// it by that same name. StPageFlip uses an anonymous AMD define with
// ["exports"], so a custom module name works fine for it.
$pdfjs_url  = (new moodle_url('/mod/leafr/vendor/pdfjs/pdf.min'))->out(false);
$stpf_url   = (new moodle_url('/mod/leafr/vendor/stpageflip/StPageFlip.browser'))->out(false);
$PAGE->requires->js_amd_inline(
    'require.config({paths:{"pdfjs-dist/build/pdf":"' . $pdfjs_url
    . '","mod_leafr/vendor-stpageflip":"' . $stpf_url . '"}});'
);

// Load our stylesheet.
$PAGE->requires->css('/mod/leafr/styles.css');

// AMD init with config.
$PAGE->requires->js_call_amd(
    'mod_leafr/reader',
    'init',
    [[
        'cmid'         => (int)$cm->id,
        'fileurl'      => $fileurl ?? '',
        'startPage'    => $startpage,
        'savedPage'    => (int)$savedpage,
        'wwwroot'      => $CFG->wwwroot,
        'sesskey'      => sesskey(),
        'config'       => [
            'completionType'    => (int)$leafr->completiontype,
            'completionPercent' => (int)$leafr->completionpercent,
            'completionPage'    => (int)$leafr->completionpage,
            'downloadAllowed'   => (bool)$leafr->downloadallowed,
            'showToc'           => (bool)$leafr->showtoc,
            'simpleView'        => $simpleview,
            'proAvailable'      => $proavailable,
        ],
        'strings'      => [
            'loading'           => get_string('loading', 'leafr'),
            'errordocument'     => get_string('errordocument', 'leafr'),
            'pageof'            => get_string('pageof', 'leafr'),
            'resume_toast'      => get_string('resume_toast', 'leafr'),
            'resume_continue'   => get_string('resume_continue', 'leafr'),
            'resume_restart'    => get_string('resume_restart', 'leafr'),
            'completion_done'   => get_string('completion_done', 'leafr'),
            'toc_title'         => get_string('toc_title', 'leafr'),
            'toc_empty'         => get_string('toc_empty', 'leafr'),
            'bookmark_add'      => get_string('bookmark_add', 'leafr'),
            'bookmark_saved'    => get_string('bookmark_saved', 'leafr'),
            'bookmark_deleted'  => get_string('bookmark_deleted', 'leafr'),
            'simple_view'       => get_string('simple_view', 'leafr'),
        ],
    ]]
);

// Render the page.
echo $OUTPUT->header();

/** @var mod_leafr_renderer $renderer */
$renderer = $PAGE->get_renderer('mod_leafr');

// Show activity intro if configured.
if ($leafr->intro && $cm->showdescription) {
    echo $OUTPUT->box(format_module_intro('leafr', $leafr, $cm->id), 'generalbox mod_introbox');
}

// Render the reader container.
if ($fileurl) {
    echo $renderer->render_reader($cm, $leafr, $context, $fileurl, $proavailable);
} else {
    echo $renderer->render_no_file();
}

echo $OUTPUT->footer();
