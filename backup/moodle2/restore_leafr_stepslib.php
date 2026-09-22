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
 * Restore structure step for mod_leafr.
 *
 * @package   mod_leafr
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the restore structure of mod_leafr.
 *
 * @package   mod_leafr
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_leafr_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the elements to restore.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [new restore_path_element('leafr', '/activity/leafr')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('leafr_progress', '/activity/leafr/progresses/progress');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the activity record.
     *
     * @param array $data Backup data
     */
    protected function process_leafr($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('leafr', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restores the reading progress of a user.
     *
     * @param array $data Backup data
     */
    protected function process_leafr_progress($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->leafrid = $this->get_new_parentid('leafr');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid || $DB->record_exists('leafr_progress', ['leafrid' => $data->leafrid, 'userid' => $data->userid])) {
            return;
        }
        $newitemid = $DB->insert_record('leafr_progress', $data);
        $this->set_mapping('leafr_progress', $oldid, $newitemid);
    }

    /**
     * Restores the files after the records.
     */
    protected function after_execute() {
        $this->add_related_files('mod_leafr', 'intro', null);
        $this->add_related_files('mod_leafr', 'content', null);
    }
}
