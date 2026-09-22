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
 * Backup task for mod_leafr.
 *
 * @package   mod_leafr
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/leafr/backup/moodle2/backup_leafr_stepslib.php');

/**
 * Backup task for mod_leafr.
 *
 * @package   mod_leafr
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_leafr_activity_task extends backup_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the backup steps.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_leafr_activity_structure_step('leafr_structure', 'leafr.xml'));
    }

    /**
     * Encodes links to the activity so they can be restored.
     *
     * @param string $content Content with links
     * @return string Content with encoded links
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace("/({$base}\/mod\/leafr\/index\.php\?id=)([0-9]+)/", '$@LEAFRINDEX*$2@$', $content);
        $content = preg_replace("/({$base}\/mod\/leafr\/view\.php\?id=)([0-9]+)/", '$@LEAFRVIEWBYID*$2@$', $content);
        return $content;
    }
}
