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

namespace leafrtool_office\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Tracks the background conversion of an uploaded Office file to PDF for one activity.
 *
 * @package   leafrtool_office
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversion {
    /** @var string Waiting for the background task to run or finish. */
    const STATUS_PENDING = 'pending';

    /** @var string The converted PDF is ready. */
    const STATUS_COMPLETE = 'complete';

    /** @var string The conversion failed; see the record's errormessage. */
    const STATUS_FAILED = 'failed';

    /**
     * The current conversion record for an activity, if any.
     *
     * @param int $leafrid Leafr instance id
     * @return \stdClass|false
     */
    public static function get(int $leafrid) {
        global $DB;
        return $DB->get_record('leafrtool_office_conversion', ['leafrid' => $leafrid]);
    }

    /**
     * Records that a source file is now waiting to be converted (or re-converted, if the source
     * file changed since a previous attempt).
     *
     * @param int $leafrid Leafr instance id
     * @param string $sourcecontenthash Content hash of the source file
     */
    public static function mark_pending(int $leafrid, string $sourcecontenthash): void {
        global $DB;
        $now = time();
        $existing = self::get($leafrid);
        if ($existing) {
            $existing->sourcecontenthash = $sourcecontenthash;
            $existing->status = self::STATUS_PENDING;
            $existing->errormessage = null;
            $existing->timemodified = $now;
            $DB->update_record('leafrtool_office_conversion', $existing);
            return;
        }
        $DB->insert_record('leafrtool_office_conversion', (object)[
            'leafrid' => $leafrid,
            'sourcecontenthash' => $sourcecontenthash,
            'status' => self::STATUS_PENDING,
            'errormessage' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Records that the conversion finished successfully.
     *
     * @param int $leafrid Leafr instance id
     */
    public static function mark_complete(int $leafrid): void {
        global $DB;
        $DB->set_field('leafrtool_office_conversion', 'status', self::STATUS_COMPLETE, ['leafrid' => $leafrid]);
        $DB->set_field('leafrtool_office_conversion', 'timemodified', time(), ['leafrid' => $leafrid]);
    }

    /**
     * Records that the conversion failed.
     *
     * @param int $leafrid Leafr instance id
     * @param string $errormessage What went wrong, for the teacher/admin
     */
    public static function mark_failed(int $leafrid, string $errormessage): void {
        global $DB;
        $DB->set_field('leafrtool_office_conversion', 'status', self::STATUS_FAILED, ['leafrid' => $leafrid]);
        $DB->set_field('leafrtool_office_conversion', 'errormessage', $errormessage, ['leafrid' => $leafrid]);
        $DB->set_field('leafrtool_office_conversion', 'timemodified', time(), ['leafrid' => $leafrid]);
    }

    /**
     * Deletes the conversion record of an activity (its converted PDF file is deleted separately,
     * by lib.php, which owns that file area).
     *
     * @param int $leafrid Leafr instance id
     */
    public static function delete_for_instance(int $leafrid): void {
        global $DB;
        $DB->delete_records('leafrtool_office_conversion', ['leafrid' => $leafrid]);
    }
}
