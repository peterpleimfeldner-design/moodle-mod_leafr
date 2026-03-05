<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * External function: Save reading position
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
 * External function to save a user's reading position.
 */
class save_position extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'   => new external_value(PARAM_INT,  'Course module ID'),
            'pageno' => new external_value(PARAM_INT,  'Current page number (1-based)'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid Course module ID
     * @param int $pageno Current page number
     * @return array Result
     */
    public static function execute(int $cmid, int $pageno): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'   => $cmid,
            'pageno' => $pageno,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/leafr:view', $context);

        if ($params['pageno'] < 1) {
            throw new \invalid_parameter_exception('pageno must be >= 1');
        }

        \leafr_save_reading_position($params['cmid'], $params['pageno']);

        return ['status' => 'ok', 'pageno' => $params['pageno']];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status'),
            'pageno' => new external_value(PARAM_INT,  'Saved page number'),
        ]);
    }
}
