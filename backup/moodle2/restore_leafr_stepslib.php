<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Restore step definitions for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define all the restore steps that will be used by the restore_leafr_activity_task.
 */
class restore_leafr_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the structure of the restore.
     *
     * @return array of restore_path_element objects
     */
    protected function define_structure(): array {
        $paths = [];

        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('leafr', '/activity/leafr');

        if ($userinfo) {
            $paths[] = new restore_path_element('leafr_bookmark', '/activity/leafr/bookmarks/bookmark');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process a leafr record.
     *
     * @param array $data Record data
     */
    protected function process_leafr(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('leafr', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process a leafr_bookmark record.
     *
     * @param array $data Record data
     */
    protected function process_leafr_bookmark(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->leafrid       = $this->get_new_parentid('leafr');
        $data->userid        = $this->get_mappingid('user', $data->userid);
        $data->timecreated   = $this->apply_date_offset($data->timecreated);
        $data->timemodified  = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('leafr_bookmarks', $data);
        $this->set_mapping('leafr_bookmark', $oldid, $newitemid);
    }

    /**
     * After restore: restore files.
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_leafr', 'content', null);
        $this->add_related_files('mod_leafr', 'intro',   null);
    }
}
