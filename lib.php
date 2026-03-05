<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library functions for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the information on whether the module supports a feature.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function leafr_supports(string $feature): mixed {
    return match($feature) {
        FEATURE_MOD_INTRO                => true,
        FEATURE_COMPLETION_TRACKS_VIEWS  => true,
        FEATURE_COMPLETION_HAS_RULES     => true,
        FEATURE_BACKUP_MOODLE2           => true,
        FEATURE_SHOW_DESCRIPTION         => true,
        FEATURE_GRADE_HAS_GRADE          => false,
        FEATURE_MOD_PURPOSE              => MOD_PURPOSE_CONTENT,
        default                          => null,
    };
}

/**
 * Saves a new instance of the leafr activity into the database.
 *
 * @param stdClass $data An object from the form in mod_form.php
 * @param mod_leafr_mod_form|null $mform The form
 * @return int The id of the newly inserted leafr record
 */
function leafr_add_instance(stdClass $data, ?mod_leafr_mod_form $mform = null): int {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = time();

    // Default values for optional fields.
    $data->completiontype    = $data->completiontype ?? 0;
    $data->completionpercent = $data->completionpercent ?? 100;
    $data->completionpage    = $data->completionpage ?? 0;
    $data->downloadallowed   = $data->downloadallowed ?? 0;
    $data->showtoc           = $data->showtoc ?? 1;
    $data->initialpage       = $data->initialpage ?? 1;

    $id = $DB->insert_record('leafr', $data);

    // Save the PDF file from draft area to permanent file area.
    if (!empty($data->pdffile)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files(
            $data->pdffile,
            $context->id,
            'mod_leafr',
            'content',
            0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']]
        );
    }

    // Trigger completion update if needed.
    $completiontrigger = $data->completion ?? 0;
    if ($completiontrigger) {
        $completion = new completion_info(get_course($data->course));
        $completion->set_module_viewed(get_coursemodule_from_id('leafr', $id));
    }

    return $id;
}

/**
 * Updates an instance of the leafr activity.
 *
 * @param stdClass $data An object from the form in mod_form.php
 * @param mod_leafr_mod_form|null $mform The form
 * @return bool true if successful
 */
function leafr_update_instance(stdClass $data, ?mod_leafr_mod_form $mform = null): bool {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    // Save the PDF file from draft area.
    if (!empty($data->pdffile)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files(
            $data->pdffile,
            $context->id,
            'mod_leafr',
            'content',
            0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']]
        );
    }

    return $DB->update_record('leafr', $data);
}

/**
 * Removes an instance of the leafr activity from the database.
 *
 * @param int $id Id of the module instance
 * @return bool true if successful
 */
function leafr_delete_instance(int $id): bool {
    global $DB;

    if (!$leafr = $DB->get_record('leafr', ['id' => $id])) {
        return false;
    }

    // Delete all associated files.
    $cm = get_coursemodule_from_instance('leafr', $id);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id);
    }

    // Delete all bookmarks.
    $DB->delete_records('leafr_bookmarks', ['leafrid' => $id]);

    // Delete the main record.
    $DB->delete_records('leafr', ['id' => $id]);

    return true;
}

/**
 * Serves the leafr PDF file.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module object
 * @param context $context Context object
 * @param string $filearea File area
 * @param array $args Extra arguments
 * @param bool $forcedownload Whether to force download
 * @param array $options Additional options
 * @return bool false if file not found, does not return if found - sends file
 */
function leafr_pluginfile(stdClass $course, stdClass $cm, context $context, string $filearea,
                          array $args, bool $forcedownload, array $options = []): bool {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea !== 'content') {
        return false;
    }

    // Check view capability.
    $leafr = $DB->get_record('leafr', ['id' => $cm->instance], '*', MUST_EXIST);
    if (!has_capability('mod/leafr:view', $context)) {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_leafr', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return false;
    }

    // Respect download restriction.
    if ($forcedownload && !$leafr->downloadallowed && !has_capability('mod/leafr:download', $context)) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Returns a list of features that are supported by the activity module.
 *
 * @param string $feature FEATURE_xx constant for the requested feature
 * @return mixed
 */
function leafr_get_coursemodule_info(stdClass $coursemodule): cached_cm_info {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat';
    if (!$leafr = $DB->get_record('leafr', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $leafr->name;

    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('leafr', $leafr, $coursemodule->id, false);
    }

    return $result;
}
