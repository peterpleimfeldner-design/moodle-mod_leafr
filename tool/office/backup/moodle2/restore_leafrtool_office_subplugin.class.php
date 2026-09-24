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

/**
 * Restore structure step for leafrtool_office.
 *
 * @package   leafrtool_office
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores the settings of leafrtool_office.
 *
 * @package   leafrtool_office
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_leafrtool_office_subplugin extends restore_subplugin {
    /**
     * Returns the paths to be handled by the subplugin.
     *
     * @return restore_path_element[]
     */
    protected function define_leafr_subplugin_structure() {
        return [
            new restore_path_element($this->get_namefor('settings'), $this->get_pathfor('/settings')),
        ];
    }

    /**
     * Restores the settings row.
     *
     * @param array $data Backup data
     */
    public function process_leafrtool_office_settings($data) {
        global $DB;

        $data = (object)$data;
        $data->leafrid = $this->get_new_parentid('leafr');
        if (!$DB->record_exists('leafrtool_office_settings', ['leafrid' => $data->leafrid])) {
            $DB->insert_record('leafrtool_office_settings', $data);
        }
    }
}
