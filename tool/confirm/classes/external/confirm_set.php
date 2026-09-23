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

namespace leafrtool_confirm\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use leafrtool_confirm\local\confirm;

/**
 * Records that the current user has confirmed having read the document.
 *
 * @package   leafrtool_confirm
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class confirm_set extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Records the confirmation and updates completion.
     *
     * @param int $cmid Course module id
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'leafr');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/leafr:view', $context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        $leafrid = (int)$cm->instance;
        $settings = confirm::get_settings($leafrid);
        if (!$settings->requireconfirm) {
            throw new \moodle_exception('confirmnotrequired', 'leafrtool_confirm');
        }

        $time = confirm::record_confirmation($leafrid, (int)$USER->id);

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
        }

        return ['timeconfirmed' => $time];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'timeconfirmed' => new external_value(PARAM_INT, 'Unix timestamp of the confirmation'),
        ]);
    }
}
