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
 * Backup structure step for mod_leafr.
 *
 * @package   mod_leafr
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup structure of mod_leafr.
 *
 * @package   mod_leafr
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_leafr_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the structure of the backup.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $leafr = new backup_nested_element('leafr', ['id'], [
            'name', 'intro', 'introformat', 'filearea', 'completiontype', 'completionpercent', 'completionpage',
            'downloadallowed', 'showtoc', 'initialpage', 'totalpages', 'timecreated', 'timemodified',
        ]);
        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], ['userid', 'seenpages', 'lastpage', 'timemodified']);
        $bookmarks = new backup_nested_element('bookmarks');
        $bookmark = new backup_nested_element('bookmark', ['id'], [
            'userid', 'pageno', 'note', 'timecreated', 'timemodified',
        ]);

        $leafr->add_child($progresses);
        $progresses->add_child($progress);
        $leafr->add_child($bookmarks);
        $bookmarks->add_child($bookmark);

        $leafr->set_source_table('leafr', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $progress->set_source_table('leafr_progress', ['leafrid' => backup::VAR_PARENTID]);
            $bookmark->set_source_table('leafr_bookmarks', ['leafrid' => backup::VAR_PARENTID]);
        }

        $progress->annotate_ids('user', 'userid');
        $bookmark->annotate_ids('user', 'userid');
        $leafr->annotate_files('mod_leafr', 'intro', null);
        $leafr->annotate_files('mod_leafr', 'content', null);

        $this->add_subplugin_structure('leafrtool', $leafr, true);

        return $this->prepare_activity_structure($leafr);
    }
}
