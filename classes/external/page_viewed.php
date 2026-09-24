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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_leafr\local\progress;

/**
 * Records the pages a user has seen and the reading position, and updates completion.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_viewed extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'pages' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Page number (1-based)'),
                'Pages the user has seen since the last call',
                VALUE_DEFAULT,
                []
            ),
            'currentpage' => new external_value(
                PARAM_INT,
                'Current reading position, 0 to leave it unchanged',
                VALUE_DEFAULT,
                0
            ),
            'totalpages' => new external_value(
                PARAM_INT,
                'Number of pages of the PDF as reported by the viewer',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Records the progress.
     *
     * @param int $cmid Course module id
     * @param int[] $pages Seen pages
     * @param int $currentpage Current reading position
     * @param int $totalpages Number of pages in the PDF
     * @return array
     */
    public static function execute(int $cmid, array $pages = [], int $currentpage = 0, int $totalpages = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'pages' => $pages,
            'currentpage' => $currentpage,
            'totalpages' => $totalpages,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'leafr');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/leafr:view', $context);

        // Guests and visitors who are not logged in neither store progress nor report the page count.
        if (isguestuser() || !isloggedin()) {
            return ['completed' => false];
        }

        $leafr = $DB->get_record('leafr', ['id' => $cm->instance], '*', MUST_EXIST);

        // The PDF itself is only available in the browser, so the viewer reports its page count.
        // It is stored once per PDF and corrected by users who can edit the activity.
        $reported = min((int)$params['totalpages'], progress::MAX_PAGES);
        $totalchanged = false;
        if (
            $reported > 0 && $reported != $leafr->totalpages
                && (empty($leafr->totalpages) || has_capability('moodle/course:manageactivities', $context))
        ) {
            $leafr->totalpages = $reported;
            $DB->set_field('leafr', 'totalpages', $reported, ['id' => $leafr->id]);
            $totalchanged = true;
        }

        $limit = (int)$leafr->totalpages ?: progress::MAX_PAGES;
        $valid = array_filter($params['pages'], function ($p) use ($limit) {
            return $p >= 1 && $p <= $limit;
        });
        $position = ($params['currentpage'] >= 1 && $params['currentpage'] <= $limit) ? $params['currentpage'] : 0;
        $newpages = progress::record((int)$leafr->id, (int)$USER->id, $valid, $position);

        foreach ($newpages as $pageno) {
            \mod_leafr\event\page_viewed::create([
                'objectid' => $leafr->id,
                'context' => $context,
                'other' => ['pageno' => $pageno],
            ])->trigger();
        }

        $completed = false;
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC && $leafr->completiontype > 0) {
            if ($newpages || $totalchanged) {
                $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
            }
            $completed = progress::is_complete($leafr, (int)$USER->id);
        }

        return ['completed' => $completed];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'completed' => new external_value(PARAM_BOOL, 'Whether the page based completion rule is fulfilled'),
        ]);
    }
}
