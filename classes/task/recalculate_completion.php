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

namespace mod_leafr\task;

/**
 * Recalculates the activity completion of the users of an activity after its page count changed.
 *
 * The page based completion rules depend on the page count, so a corrected count can complete
 * or un-complete users who are not reading at that moment. Only enrolled users who have reading
 * progress or a completion state in the activity can be affected. Each of them is recalculated with
 * completion_info::update_state(), which keeps a completion a teacher has set by hand and updates
 * course completion as usual (unlike completion_info::reset_all_state(), which deletes them).
 * This runs in the background because it can take a while in large courses.
 *
 * Custom data: {cmid: course module id}
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalculate_completion extends \core\task\adhoc_task {
    /**
     * Queues the recalculation for an activity, unless one is already waiting to start.
     *
     * Moodle's own duplicate check is not used: it also matches a task that is already running,
     * which could then miss a change of the page count made while it runs.
     *
     * @param int $cmid Course module id
     */
    public static function queue(int $cmid): void {
        global $DB;
        $task = new self();
        $task->set_custom_data(['cmid' => $cmid]);
        $waiting = $DB->record_exists_select(
            'task_adhoc',
            'classname = :classname AND timestarted IS NULL AND ' . $DB->sql_compare_text('customdata', 255) . ' = :customdata',
            ['classname' => '\\' . self::class, 'customdata' => $task->get_custom_data_as_string()]
        );
        if (!$waiting) {
            \core\task\manager::queue_adhoc_task($task);
        }
    }

    /**
     * Recalculates the completion state of every affected user in the activity.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $cmid = (int)$this->get_custom_data()->cmid;
        try {
            [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'leafr');
        } catch (\moodle_exception $e) {
            // The activity was deleted in the meantime: nothing to do.
            return;
        }
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) != COMPLETION_TRACKING_AUTOMATIC) {
            return;
        }
        $totalpages = $DB->get_field('leafr', 'totalpages', ['id' => $cm->instance]);

        [$enrolledsql, $params] = get_enrolled_sql(\context_module::instance($cm->id), '', 0, true);
        $sql = "SELECT a.userid
                  FROM (SELECT userid
                          FROM {leafr_progress}
                         WHERE leafrid = :leafrid
                         UNION
                        SELECT userid
                          FROM {course_modules_completion}
                         WHERE coursemoduleid = :cmid) a
                  JOIN ($enrolledsql) e ON e.id = a.userid";
        $params += ['leafrid' => $cm->instance, 'cmid' => $cm->id];
        $users = $DB->get_recordset_sql($sql, $params);
        foreach ($users as $user) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, (int)$user->userid);
        }
        $users->close();

        // A count changed while this ran may have been missed for the users done before.
        if ($DB->get_field('leafr', 'totalpages', ['id' => $cm->instance]) != $totalpages) {
            self::queue($cmid);
        }
    }
}
