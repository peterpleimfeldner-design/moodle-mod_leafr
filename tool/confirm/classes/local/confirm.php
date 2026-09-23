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

namespace leafrtool_confirm\local;

use stdClass;

/**
 * Settings and per-user log of the read confirmation tool.
 *
 * @package   leafrtool_confirm
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class confirm {
    /**
     * The settings of an activity, with defaults if none are stored yet.
     *
     * @param int $leafrid Leafr instance id
     * @return stdClass Object with requireconfirm (bool) and confirmtext (string)
     */
    public static function get_settings(int $leafrid): stdClass {
        global $DB;
        $record = $DB->get_record('leafrtool_confirm', ['leafrid' => $leafrid]);
        return (object)[
            'requireconfirm' => $record ? (bool)$record->requireconfirm : false,
            'confirmtext' => $record && $record->confirmtext !== null && $record->confirmtext !== ''
                ? $record->confirmtext
                : get_string('confirmtext_default', 'leafrtool_confirm'),
        ];
    }

    /**
     * Creates or updates the settings of an activity.
     *
     * @param int $leafrid Leafr instance id
     * @param bool $requireconfirm Whether a confirmation is required
     * @param string $confirmtext Text shown next to the confirmation checkbox
     */
    public static function save_settings(int $leafrid, bool $requireconfirm, string $confirmtext): void {
        global $DB;
        $confirmtext = trim($confirmtext);
        $record = $DB->get_record('leafrtool_confirm', ['leafrid' => $leafrid]);
        if ($record) {
            $record->requireconfirm = $requireconfirm ? 1 : 0;
            $record->confirmtext = $confirmtext;
            $DB->update_record('leafrtool_confirm', $record);
        } else {
            $DB->insert_record('leafrtool_confirm', (object)[
                'leafrid' => $leafrid,
                'requireconfirm' => $requireconfirm ? 1 : 0,
                'confirmtext' => $confirmtext,
            ]);
        }
    }

    /**
     * Whether a user has confirmed having read an activity.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @return bool
     */
    public static function is_confirmed(int $leafrid, int $userid): bool {
        global $DB;
        return $DB->record_exists('leafrtool_confirm_log', ['leafrid' => $leafrid, 'userid' => $userid]);
    }

    /**
     * Returns the time a user confirmed having read an activity.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @return int Unix timestamp, 0 if not confirmed
     */
    public static function get_confirmed_time(int $leafrid, int $userid): int {
        global $DB;
        $time = $DB->get_field('leafrtool_confirm_log', 'timeconfirmed', ['leafrid' => $leafrid, 'userid' => $userid]);
        return $time ? (int)$time : 0;
    }

    /**
     * Records that a user has confirmed having read an activity. Confirming again (e.g. after a
     * course reset re-opens the requirement) is idempotent and keeps the original timestamp.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @return int Unix timestamp of the confirmation
     */
    public static function record_confirmation(int $leafrid, int $userid): int {
        global $DB;
        $existing = self::get_confirmed_time($leafrid, $userid);
        if ($existing) {
            return $existing;
        }
        $now = time();
        $DB->insert_record('leafrtool_confirm_log', [
            'leafrid' => $leafrid,
            'userid' => $userid,
            'timeconfirmed' => $now,
        ]);
        return $now;
    }

    /**
     * Deletes the settings and all confirmations of an activity.
     *
     * @param int $leafrid Leafr instance id
     */
    public static function delete_for_instance(int $leafrid): void {
        global $DB;
        $DB->delete_records('leafrtool_confirm', ['leafrid' => $leafrid]);
        $DB->delete_records('leafrtool_confirm_log', ['leafrid' => $leafrid]);
    }

    /**
     * Deletes the confirmations of all users in an activity, keeping its settings.
     *
     * @param int $leafrid Leafr instance id
     */
    public static function delete_confirmations_for_instance(int $leafrid): void {
        global $DB;
        $DB->delete_records('leafrtool_confirm_log', ['leafrid' => $leafrid]);
    }
}
