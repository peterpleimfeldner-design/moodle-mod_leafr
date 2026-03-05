<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Backup step definitions for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define all the backup steps that will be used by the backup_leafr_activity_task.
 */
class backup_leafr_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure of the backup for leafr activities.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {

        // Get data for userinfo based on backup setting.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element in the backup.
        $leafr = new backup_nested_element('leafr', ['id'], [
            'course',
            'name',
            'intro',
            'introformat',
            'filearea',
            'completiontype',
            'completionpercent',
            'completionpage',
            'downloadallowed',
            'showtoc',
            'initialpage',
            'timecreated',
            'timemodified',
        ]);

        $bookmarks = new backup_nested_element('bookmarks');
        $bookmark  = new backup_nested_element('bookmark', ['id'], [
            'leafrid',
            'userid',
            'pageno',
            'label',
            'note',
            'timecreated',
            'timemodified',
        ]);

        // Build the tree.
        $leafr->add_child($bookmarks);
        $bookmarks->add_child($bookmark);

        // Define data sources.
        $leafr->set_source_table('leafr', ['id' => backup::VAR_ACTIVITYID]);

        // User-specific data only if userinfo is included.
        if ($userinfo) {
            $bookmark->set_source_table('leafr_bookmarks', ['leafrid' => backup::VAR_PARENTID]);
        }

        // Annotate user IDs for proper restoring.
        $bookmark->annotate_ids('user', 'userid');

        // Include the PDF file.
        $leafr->annotate_files('mod_leafr', 'content', null);

        return $this->prepare_activity_structure($leafr);
    }
}
