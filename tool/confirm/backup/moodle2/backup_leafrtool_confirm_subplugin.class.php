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
 * Backup structure step for leafrtool_confirm.
 *
 * @package   leafrtool_confirm
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Attaches the settings and, if user data is included, the confirmation log to the leafr element.
 *
 * @package   leafrtool_confirm
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_leafrtool_confirm_subplugin extends backup_subplugin {
    /**
     * Returns the subplugin information to attach to the leafr element.
     *
     * @return backup_subplugin_element
     */
    protected function define_leafr_subplugin_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $settings = new backup_nested_element('settings', null, ['requireconfirm', 'confirmtext']);
        $logs = new backup_nested_element('logs');
        $log = new backup_nested_element('log', ['id'], ['userid', 'timeconfirmed']);

        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($settings);
        $subpluginwrapper->add_child($logs);
        $logs->add_child($log);

        $settings->set_source_table('leafrtool_confirm', ['leafrid' => backup::VAR_PARENTID]);
        if ($userinfo) {
            $log->set_source_table('leafrtool_confirm_log', ['leafrid' => backup::VAR_PARENTID]);
        }

        $log->annotate_ids('user', 'userid');

        return $subplugin;
    }
}
