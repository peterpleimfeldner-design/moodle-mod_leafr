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
 * Per-activity settings of the Office document tool.
 *
 * @package   leafrtool_office
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings {

    /**
     * The settings of an activity, with sensible defaults if none have been saved yet.
     *
     * @param int $leafrid Leafr instance id, 0 for a new activity
     * @return \stdClass
     */
    public static function get(int $leafrid): \stdClass {
        global $DB;
        if ($leafrid && $record = $DB->get_record('leafrtool_office_settings', ['leafrid' => $leafrid])) {
            $record->downloadoriginal = (int)$record->downloadoriginal;
            return $record;
        }
        return (object)['leafrid' => $leafrid, 'downloadoriginal' => 0];
    }

    /**
     * Saves the settings of an activity.
     *
     * @param int $leafrid Leafr instance id
     * @param bool $downloadoriginal Whether to offer the original file for download instead of the PDF
     */
    public static function save(int $leafrid, bool $downloadoriginal): void {
        global $DB;
        $existing = $DB->get_record('leafrtool_office_settings', ['leafrid' => $leafrid]);
        if ($existing) {
            $existing->downloadoriginal = (int)$downloadoriginal;
            $DB->update_record('leafrtool_office_settings', $existing);
            return;
        }
        $DB->insert_record('leafrtool_office_settings', (object)[
            'leafrid' => $leafrid,
            'downloadoriginal' => (int)$downloadoriginal,
        ]);
    }

    /**
     * Deletes the settings of an activity.
     *
     * @param int $leafrid Leafr instance id
     */
    public static function delete_for_instance(int $leafrid): void {
        global $DB;
        $DB->delete_records('leafrtool_office_settings', ['leafrid' => $leafrid]);
    }
}
