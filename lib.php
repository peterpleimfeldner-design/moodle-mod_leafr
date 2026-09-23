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
 * Library of interface functions for mod_leafr.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_leafr\local\bookmarks;
use mod_leafr\local\chapters;
use mod_leafr\local\progress;

/**
 * Returns whether the module supports a feature.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if the feature is supported, null if unknown, a string for FEATURE_MOD_PURPOSE
 */
function leafr_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Normalises the settings submitted by the activity form.
 *
 * @param stdClass $data Form data
 */
function leafr_normalise_settings(stdClass $data): void {
    $data->completionpercent = max(1, min(100, (int)($data->completionpercent ?? 100)));
    $data->completionpage = max(1, (int)($data->completionpage ?? 1));
    $data->completiontype = (int)($data->completiontype ?? progress::COMPLETION_NONE);
    $data->downloadallowed = empty($data->downloadallowed) ? 0 : 1;
    $data->showtoc = empty($data->showtoc) ? 0 : 1;
    $data->initialpage = max(1, (int)($data->initialpage ?? 1));

    $chapterlist = chapters::from_submitted($data->chaptertitle ?? [], $data->chapterpage ?? []);
    $data->manualchapters = chapters::encode($chapterlist);
    $data->usemanualchapters = empty($data->usemanualchapters) ? 0 : 1;

    if ($data->completiontype === progress::COMPLETION_SPECIFICRANGE) {
        $pages = progress::decode_pages($data->completionpages ?? '');
        $selected = array_map('intval', $data->completionchapters ?? []);
        $pages = array_merge($pages, chapters::pages_for_selection($chapterlist, $selected, (int)($data->totalpages ?? 0)));
        $data->completionpages = progress::encode_pages($pages);
    } else {
        $data->completionpages = $data->completionpages ?? '';
    }
}

/**
 * Saves the PDF from the draft area of the form and returns whether the file changed.
 *
 * @param stdClass $data Form data containing pdffile and coursemodule
 * @return bool True if the stored PDF is different from before
 */
function leafr_save_pdf(stdClass $data): bool {
    if (!isset($data->pdffile)) {
        return false;
    }
    $context = context_module::instance($data->coursemodule);
    $fs = get_file_storage();
    $before = array_keys($fs->get_area_files($context->id, 'mod_leafr', 'content', 0, 'id', false));
    file_save_draft_area_files(
        $data->pdffile,
        $context->id,
        'mod_leafr',
        'content',
        0,
        ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']]
    );
    $after = array_keys($fs->get_area_files($context->id, 'mod_leafr', 'content', 0, 'id', false));
    return $before != $after;
}

/**
 * Adds a new instance of the activity.
 *
 * @param stdClass $data Form data
 * @param mod_leafr_mod_form|null $mform The form
 * @return int New instance id
 */
function leafr_add_instance($data, $mform = null) {
    global $DB;

    leafr_normalise_settings($data);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->totalpages = 0;
    $data->id = $DB->insert_record('leafr', $data);

    // The course module id is known at this point, so the file can be saved.
    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $data->coursemodule]);
    leafr_save_pdf($data);

    if (!empty($data->completionexpected)) {
        \core_completion\api::update_completion_date_event(
            $data->coursemodule,
            'leafr',
            $data->id,
            $data->completionexpected
        );
    }
    return $data->id;
}

/**
 * Updates an instance of the activity.
 *
 * @param stdClass $data Form data
 * @param mod_leafr_mod_form|null $mform The form
 * @return bool
 */
function leafr_update_instance($data, $mform = null) {
    global $DB;

    $data->totalpages = (int)$DB->get_field('leafr', 'totalpages', ['id' => $data->instance]);
    leafr_normalise_settings($data);
    $data->id = $data->instance;
    $data->timemodified = time();

    if (leafr_save_pdf($data)) {
        // A different PDF may have a different number of pages. It is measured again on the next view.
        $data->totalpages = 0;
    }
    $DB->update_record('leafr', $data);

    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'leafr',
        $data->id,
        $data->completionexpected ?? null
    );
    return true;
}

/**
 * Deletes an instance of the activity and all its data.
 *
 * @param int $id Instance id
 * @return bool
 */
function leafr_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('leafr', ['id' => $id])) {
        return false;
    }
    $cm = get_coursemodule_from_instance('leafr', $id);
    if ($cm) {
        \core_completion\api::update_completion_date_event($cm->id, 'leafr', $id, null);
    }
    progress::delete_for_instance($id);
    bookmarks::delete_for_instance($id);
    $DB->delete_records('leafr', ['id' => $id]);
    return true;
}

/**
 * Returns the information needed to display the activity on the course page and to evaluate completion.
 *
 * @param stdClass $coursemodule Course module record
 * @return cached_cm_info|false
 */
function leafr_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, completiontype, completionpercent, completionpage';
    if (!$leafr = $DB->get_record('leafr', ['id' => $coursemodule->instance], $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $leafr->name;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('leafr', $leafr, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC && $leafr->completiontype > 0) {
        $result->customdata['customcompletionrules']['completionpageseen'] = (int)$leafr->completiontype;
    }
    return $result;
}

/**
 * Serves the PDF file of an activity.
 *
 * @param stdClass $course Course record
 * @param stdClass $cm Course module record
 * @param context $context Context
 * @param string $filearea File area
 * @param array $args Remaining path arguments
 * @param bool $forcedownload Whether the file should be downloaded
 * @param array $options Additional options
 * @return bool False if the file was not found, otherwise the file is sent and the script ends
 */
function leafr_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== 'content') {
        return false;
    }
    require_course_login($course, true, $cm);
    require_capability('mod/leafr:view', $context);

    $leafr = $DB->get_record('leafr', ['id' => $cm->instance], 'id, downloadallowed', MUST_EXIST);
    if ($forcedownload && !($leafr->downloadallowed && has_capability('mod/leafr:download', $context))) {
        return false;
    }

    array_shift($args); // The item id is always 0.
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_leafr', 'content', 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Returns the PDF file of an activity.
 *
 * @param context_module $context Module context
 * @return stored_file|null
 */
function leafr_get_pdf_file(context_module $context): ?stored_file {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_leafr', 'content', 0, 'sortorder DESC, id ASC', false);
    return $files ? reset($files) : null;
}

/**
 * Marks the activity as viewed and triggers the course_module_viewed event.
 *
 * @param stdClass $leafr Instance record
 * @param stdClass $course Course record
 * @param cm_info|stdClass $cm Course module
 * @param context_module $context Module context
 */
function leafr_view(stdClass $leafr, stdClass $course, $cm, context_module $context): void {
    $event = \mod_leafr\event\course_module_viewed::create([
        'objectid' => $leafr->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('leafr', $leafr);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Adds the reset options to the course reset form.
 *
 * @param MoodleQuickForm $mform Course reset form
 */
function leafr_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'leafrheader', get_string('modulenameplural', 'leafr'));
    $mform->addElement('advcheckbox', 'reset_leafr_progress', get_string('resetprogress', 'leafr'));
    $mform->addElement('advcheckbox', 'reset_leafr_bookmarks', get_string('resetbookmarks', 'leafr'));
}

/**
 * Default values for the course reset form.
 *
 * @param stdClass $course Course record
 * @return array
 */
function leafr_reset_course_form_defaults($course) {
    return ['reset_leafr_progress' => 1, 'reset_leafr_bookmarks' => 1];
}

/**
 * Removes user data when a course is reset.
 *
 * @param stdClass $data Data submitted by the reset form
 * @return array Status messages
 */
function leafr_reset_userdata($data) {
    global $DB;

    $status = [];
    if (!empty($data->reset_leafr_progress)) {
        $DB->delete_records_select(
            'leafr_progress',
            'leafrid IN (SELECT id FROM {leafr} WHERE course = :courseid)',
            ['courseid' => $data->courseid]
        );
        $status[] = [
            'component' => get_string('modulenameplural', 'leafr'),
            'item' => get_string('resetprogress', 'leafr'),
            'error' => false,
        ];
    }
    if (!empty($data->reset_leafr_bookmarks)) {
        $DB->delete_records_select(
            'leafr_bookmarks',
            'leafrid IN (SELECT id FROM {leafr} WHERE course = :courseid)',
            ['courseid' => $data->courseid]
        );
        $status[] = [
            'component' => get_string('modulenameplural', 'leafr'),
            'item' => get_string('resetbookmarks', 'leafr'),
            'error' => false,
        ];
    }
    return $status;
}

/**
 * Registers the user preferences of this plugin.
 *
 * @return array
 */
function leafr_user_preferences(): array {
    return [
        'mod_leafr_simpleview' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
        ],
    ];
}
