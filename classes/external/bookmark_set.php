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

namespace mod_leafr\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_leafr\local\bookmarks;

/**
 * Creates or updates a bookmark of the current user.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bookmark_set extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'pageno' => new external_value(PARAM_INT, 'Page number (1-based)'),
            'note' => new external_value(PARAM_RAW, 'Optional note', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Creates or updates the bookmark.
     *
     * @param int $cmid Course module id
     * @param int $pageno Page number
     * @param string $note Optional note
     * @return array
     */
    public static function execute(int $cmid, int $pageno, string $note = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'pageno' => $pageno,
            'note' => $note,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'leafr');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/leafr:view', $context);

        if ($params['pageno'] < 1) {
            throw new \invalid_parameter_exception('pageno must be 1 or higher');
        }

        $record = bookmarks::set((int)$cm->instance, (int)$USER->id, $params['pageno'], $params['note']);
        return ['pageno' => (int)$record->pageno, 'note' => (string)$record->note];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pageno' => new external_value(PARAM_INT, 'Page number'),
            'note' => new external_value(PARAM_RAW, 'Stored note, possibly truncated'),
        ]);
    }
}
