<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * External function: Track page view and trigger completion
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_leafr\external;

use external_api;
use external_function_parameters;
use external_value;
use external_multiple_structure;
use external_single_structure;

/**
 * External function to record seen pages and trigger completion.
 */
class page_viewed extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT,  'Course module ID'),
            'seen_pages' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Page number seen'),
                'Array of seen page numbers'
            ),
            'total_pages' => new external_value(PARAM_INT, 'Total number of pages', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid Course module ID
     * @param array $seenpages Array of seen page numbers
     * @return array Result with completion status
     */
    public static function execute(int $cmid, array $seenpages): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'        => $cmid,
            'seen_pages'  => $seenpages,
            'total_pages' => func_num_args() > 2 ? func_get_arg(2) : 0,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/leafr:view', $context);

        // Validate page numbers.
        $cleanpages = array_filter(
            array_map('intval', $params['seen_pages']),
            fn($p) => $p >= 1
        );

        // Update progress tracking.
        \leafr_update_progress($params['cmid'], array_values($cleanpages));

        // Also save current position as max seen page.
        if (!empty($cleanpages)) {
            $maxpage = max($cleanpages);
            \leafr_save_reading_position($params['cmid'], $maxpage);
        }

        // Fire page_viewed event.
        $cm = get_coursemodule_from_id('leafr', $params['cmid'], 0, false, MUST_EXIST);
        foreach ($cleanpages as $pageno) {
            $event = \mod_leafr\event\page_viewed::create([
                'objectid' => $cm->instance,
                'context'  => $context,
                'other'    => ['pageno' => $pageno],
            ]);
            $event->trigger();
        }

        // Check completion.
        $completed = false;
        $leafr = $DB->get_record('leafr', ['id' => $cm->instance]);
        if ($leafr) {
            // Update totalpages if not set yet, or if it changed.
            if ($params['total_pages'] > 0 && (int)$leafr->totalpages !== $params['total_pages']) {
                $leafr->totalpages = $params['total_pages'];
                $DB->update_record('leafr', $leafr);
            }

            if ((int)$leafr->completiontype > 0) {
                $course = get_course($cm->course);
                $completion = new \completion_info($course);
                if ($completion->is_enabled($cm)) {
                    $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
                    $completed = true;
                }
            }
        }

        return ['status' => 'ok', 'completed' => $completed];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status'    => new external_value(PARAM_TEXT, 'Status message'),
            'completed' => new external_value(PARAM_BOOL, 'Whether completion was triggered'),
        ]);
    }
}
