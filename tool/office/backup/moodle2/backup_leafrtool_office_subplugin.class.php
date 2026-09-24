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
 * Backup structure step for leafrtool_office.
 *
 * @package   leafrtool_office
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Attaches this tool's settings to the leafr element. The conversion status/cache table is
 * deliberately not backed up: it is derived data that view.php re-queues on its own once it
 * notices a restored activity's Office file has no (or a stale) conversion record.
 *
 * @package   leafrtool_office
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_leafrtool_office_subplugin extends backup_subplugin {
    /**
     * Returns the subplugin information to attach to the leafr element.
     *
     * @return backup_subplugin_element
     */
    protected function define_leafr_subplugin_structure() {
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $settings = new backup_nested_element('settings', null, ['downloadoriginal']);

        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($settings);

        $settings->set_source_table('leafrtool_office_settings', ['leafrid' => backup::VAR_PARENTID]);

        return $subplugin;
    }
}
