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
 * Deletes a bookmark of the current user.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bookmark_delete extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'pageno' => new external_value(PARAM_INT, 'Page number (1-based)'),
        ]);
    }

    /**
     * Deletes the bookmark.
     *
     * @param int $cmid Course module id
     * @param int $pageno Page number
     * @return array
     */
    public static function execute(int $cmid, int $pageno): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'pageno' => $pageno]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'leafr');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/leafr:view', $context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        $deleted = bookmarks::delete((int)$cm->instance, (int)$USER->id, $params['pageno']);
        return ['deleted' => $deleted];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'Whether a bookmark was deleted'),
        ]);
    }
}
