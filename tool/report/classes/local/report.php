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

namespace leafrtool_report\local;

use cm_info;
use completion_info;
use mod_leafr\local\progress;
use stdClass;

/**
 * Builds the data-sparse per-person reading overview: only whether the activity is complete,
 * whether the required pages have been read, and (if leafrtool_confirm is installed and required)
 * when the person confirmed. Deliberately not collected or shown: reading times, individual page
 * views per person, bookmarks, notes or highlights.
 *
 * @package   leafrtool_report
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report {
    /**
     * Neutralises CSV/formula injection: if a cell value starts with a character a spreadsheet
     * application (Excel, LibreOffice Calc, Google Sheets) would interpret as the start of a
     * formula (=, +, -, @, tab or carriage return), it is prefixed with a single quote so the
     * value is shown as plain text instead of being executed as a formula when the exported file
     * is opened. Relevant here because a person's full name is otherwise attacker-controllable in
     * many Moodle setups (self-registration allows arbitrary first/last names).
     *
     * @param string $value Cell value
     * @return string
     */
    public static function escape_csv_cell(string $value): string {
        if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Whether leafrtool_confirm is installed and required for the given activity.
     *
     * @param stdClass $leafr Leafr instance record
     * @return bool
     */
    public static function confirm_required(stdClass $leafr): bool {
        if (!class_exists('\leafrtool_confirm\local\confirm')) {
            return false;
        }
        return \leafrtool_confirm\local\confirm::get_settings((int)$leafr->id)->requireconfirm;
    }

    /**
     * One row per person with the mod/leafr:view capability, respecting an optional group filter.
     *
     * @param cm_info $cm Course module
     * @param stdClass $leafr Leafr instance record
     * @param int $groupid Group id, 0 for all groups
     * @return array List of ['userid', 'fullname', 'completed', 'requiredseen', 'requiredpercent',
     *     'confirmedat' (int timestamp or 0)]
     */
    public static function get_rows(cm_info $cm, stdClass $leafr, int $groupid = 0): array {
        $context = \context_module::instance($cm->id);
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;
        $users = \get_enrolled_users($context, 'mod/leafr:view', $groupid, 'u.id' . $namefields);

        $completion = new completion_info($cm->get_course());
        $trackscompletion = $completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC
            || $completion->is_enabled($cm) == COMPLETION_TRACKING_MANUAL;
        $confirmrequired = self::confirm_required($leafr);
        $total = (int)$leafr->totalpages;

        $rows = [];
        foreach ($users as $user) {
            $seen = progress::get_seen_pages((int)$leafr->id, (int)$user->id);
            $required = progress::required_pages($leafr);
            if ($required) {
                $seencount = count(array_intersect($required, $seen));
                $requiredseen = $seencount >= count($required);
                $requiredpercent = (int)round(100 * $seencount / count($required));
            } else if ($leafr->completiontype == progress::COMPLETION_LASTPAGE && $total > 0) {
                $requiredseen = in_array($total, $seen, true);
                $requiredpercent = $requiredseen ? 100 : 0;
            } else if ($leafr->completiontype == progress::COMPLETION_PERCENT && $total > 0) {
                $requiredpercent = (int)round(100 * count($seen) / $total);
                $requiredseen = progress::is_complete($leafr, (int)$user->id);
            } else {
                $requiredseen = null;
                $requiredpercent = null;
            }

            $completed = false;
            if ($trackscompletion) {
                $data = $completion->get_data($cm, false, (int)$user->id);
                $completed = in_array(
                    (int)$data->completionstate,
                    [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS],
                    true
                );
            }

            $confirmedat = 0;
            if ($confirmrequired) {
                $confirmedat = \leafrtool_confirm\local\confirm::get_confirmed_time((int)$leafr->id, (int)$user->id);
            }

            $rows[] = [
                'userid' => (int)$user->id,
                'fullname' => \fullname($user),
                'completed' => $completed,
                'requiredseen' => $requiredseen,
                'requiredpercent' => $requiredpercent,
                'confirmedat' => $confirmedat,
            ];
        }

        usort($rows, function ($a, $b) {
            return strcoll($a['fullname'], $b['fullname']);
        });
        return $rows;
    }

    /**
     * Text for the "required pages read" column, the same on screen and in the CSV file.
     *
     * @param array $row Report row from {@see get_rows()}
     * @param bool $showpercent Whether the activity uses the percentage rule
     * @return string
     */
    public static function required_cell(array $row, bool $showpercent): string {
        if ($showpercent) {
            return $row['requiredpercent'] === null ? '-' : get_string('percents', 'moodle', $row['requiredpercent']);
        }
        return $row['requiredseen'] === null ? '-' : get_string($row['requiredseen'] ? 'yes' : 'no');
    }
}
