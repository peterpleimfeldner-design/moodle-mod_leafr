<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * External function: Create a bookmark (Pro)
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_leafr\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

/**
 * External function to create a named bookmark.
 */
class bookmark_create extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'   => new external_value(PARAM_INT,  'Course module ID'),
            'pageno' => new external_value(PARAM_INT,  'Page number'),
            'label'  => new external_value(PARAM_TEXT, 'Bookmark label'),
            'note'   => new external_value(PARAM_TEXT, 'Optional note', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $cmid, int $pageno, string $label, string $note = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'   => $cmid,
            'pageno' => $pageno,
            'label'  => $label,
            'note'   => $note,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/leafr:view', $context);

        // Pro check.
        if (!\leafr_pro_is_available()) {
            throw new \moodle_exception('requirespro', 'leafr');
        }

        $cm = get_coursemodule_from_id('leafr', $params['cmid'], 0, false, MUST_EXIST);

        // Max 20 bookmarks per user per activity.
        $count = $DB->count_records('leafr_bookmarks', [
            'leafrid' => $cm->instance,
            'userid'  => $USER->id,
        ]);
        if ($count >= 20) {
            throw new \moodle_exception('bookmark_limit', 'leafr');
        }

        $record = (object)[
            'leafrid'      => $cm->instance,
            'userid'       => $USER->id,
            'pageno'       => max(1, (int)$params['pageno']),
            'label'        => clean_param($params['label'], PARAM_TEXT),
            'note'         => clean_param(substr($params['note'], 0, 500), PARAM_TEXT),
            'timecreated'  => time(),
            'timemodified' => time(),
        ];

        $id = $DB->insert_record('leafr_bookmarks', $record);

        return ['id' => $id, 'status' => 'created'];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'     => new external_value(PARAM_INT,  'New bookmark ID'),
            'status' => new external_value(PARAM_TEXT, 'Status'),
        ]);
    }
}
