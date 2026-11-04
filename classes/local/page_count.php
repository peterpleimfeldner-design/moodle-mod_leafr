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

namespace mod_leafr\local;

use stdClass;

/**
 * The page count of the PDF of a Leafr activity.
 *
 * The PDF can only be read in the browser, so the viewer reports how many pages it has. A count
 * reported by a user who can edit the activity is trusted and confirmed. Until then, the count
 * that most learners reported is used, so a single learner who sends a wrong count (too low to
 * complete "last page" early, or too high to block everybody) is overruled by the real count of
 * the others. On a tie the current count stays. Each learner's report is kept in their progress
 * record (`leafr_progress.reportedtotal`). A learner can still claim to have seen every page (the
 * server cannot check reading), but cannot change the count that applies to everybody else.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_count {
    /**
     * Processes the page count reported by a user's viewer and updates the activity's count.
     *
     * @param stdClass $leafr Leafr instance record; totalpages and totalpagesconfirmed are updated
     * @param int $userid User who reported
     * @param int $reported Reported page count, 0 if none
     * @param bool $trusted Whether the user may set the count (can edit the activity)
     * @return bool Whether the page count changed
     */
    public static function report(stdClass $leafr, int $userid, int $reported, bool $trusted): bool {
        global $DB;

        $reported = min($reported, progress::MAX_PAGES);
        if ($reported < 1) {
            return false;
        }
        $previous = (int)$leafr->totalpages;

        if ($trusted) {
            if ($reported == $previous && !empty($leafr->totalpagesconfirmed)) {
                return false;
            }
            $leafr->totalpages = $reported;
            $leafr->totalpagesconfirmed = 1;
            $DB->update_record('leafr', (object)['id' => $leafr->id, 'totalpages' => $reported, 'totalpagesconfirmed' => 1]);
            return $reported != $previous;
        }

        // The majority can only change when somebody's report changes.
        if (!self::store_report((int)$leafr->id, $userid, $reported) || !empty($leafr->totalpagesconfirmed)) {
            return false;
        }
        $count = self::most_reported((int)$leafr->id, $previous);
        if ($count == $previous) {
            return false;
        }
        // Only if nobody changed or confirmed the count in the meantime (a teacher in a parallel request).
        $DB->execute(
            "UPDATE {leafr} SET totalpages = :count WHERE id = :id AND totalpagesconfirmed = 0 AND totalpages = :previous",
            ['count' => $count, 'id' => $leafr->id, 'previous' => $previous]
        );
        $current = $DB->get_record('leafr', ['id' => $leafr->id], 'totalpages, totalpagesconfirmed', MUST_EXIST);
        $leafr->totalpages = (int)$current->totalpages;
        $leafr->totalpagesconfirmed = (int)$current->totalpagesconfirmed;
        return $leafr->totalpages == $count && $count != $previous && !$leafr->totalpagesconfirmed;
    }

    /**
     * Returns the page count reported by most learners. On a tie the current count wins if it is
     * one of the most reported; otherwise the highest of them (a count that is too high only
     * delays completion, one that is too low completes users who have not read everything).
     *
     * @param int $leafrid Leafr instance id
     * @param int $current Current page count
     * @return int The page count, $current if nobody reported one
     */
    public static function most_reported(int $leafrid, int $current): int {
        global $DB;

        $sql = "SELECT reportedtotal, COUNT(1) AS reports
                  FROM {leafr_progress}
                 WHERE leafrid = :leafrid AND reportedtotal > 0
              GROUP BY reportedtotal";
        $counts = $DB->get_records_sql_menu($sql, ['leafrid' => $leafrid]);
        if ($current > 0 && !isset($counts[$current])) {
            // A count nobody reported yet (from an older version or a restore without user data)
            // counts as one report, so a single learner cannot replace it.
            $counts[$current] = 1;
        }
        if (!$counts) {
            return $current;
        }
        $max = max($counts);
        $top = array_keys(array_filter($counts, function ($reports) use ($max) {
            return (int)$reports === (int)$max;
        }));
        $top = array_map('intval', $top);
        return in_array($current, $top, true) ? $current : max($top);
    }

    /**
     * Forgets the page count and all reports, e.g. after the PDF was replaced.
     *
     * @param int $leafrid Leafr instance id
     */
    public static function reset(int $leafrid): void {
        global $DB;
        $DB->update_record('leafr', (object)['id' => $leafrid, 'totalpages' => 0, 'totalpagesconfirmed' => 0]);
        $DB->set_field('leafr_progress', 'reportedtotal', 0, ['leafrid' => $leafrid]);
    }

    /**
     * Stores a learner's reported page count in their progress record.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @param int $reported Reported page count
     * @return bool Whether the user's report changed
     */
    protected static function store_report(int $leafrid, int $userid, int $reported): bool {
        global $DB;

        $record = $DB->get_record('leafr_progress', ['leafrid' => $leafrid, 'userid' => $userid], 'id, reportedtotal');
        if (!$record) {
            $DB->insert_record('leafr_progress', (object)[
                'leafrid' => $leafrid,
                'userid' => $userid,
                'seenpages' => '',
                'lastpage' => 0,
                'reportedtotal' => $reported,
                'timemodified' => time(),
            ]);
            return true;
        }
        if ((int)$record->reportedtotal === $reported) {
            return false;
        }
        $DB->set_field('leafr_progress', 'reportedtotal', $reported, ['id' => $record->id]);
        return true;
    }
}
