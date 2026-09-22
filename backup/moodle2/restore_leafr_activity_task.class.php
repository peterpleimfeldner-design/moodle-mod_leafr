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
 * Restore task for mod_leafr.
 *
 * @package   mod_leafr
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/leafr/backup/moodle2/restore_leafr_stepslib.php');

/**
 * Restore task for mod_leafr.
 *
 * @package   mod_leafr
 * @category  backup
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_leafr_activity_task extends restore_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the restore steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_leafr_activity_structure_step('leafr_structure', 'leafr.xml'));
    }

    /**
     * Defines the contents whose links must be decoded.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('leafr', ['intro'], 'leafr')];
    }

    /**
     * Defines the link decoding rules.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('LEAFRVIEWBYID', '/mod/leafr/view.php?id=$1', 'course_module'),
            new restore_decode_rule('LEAFRINDEX', '/mod/leafr/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Defines the restore log rules of the activity.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('leafr', 'add', 'view.php?id={course_module}', '{leafr}'),
            new restore_log_rule('leafr', 'update', 'view.php?id={course_module}', '{leafr}'),
            new restore_log_rule('leafr', 'view', 'view.php?id={course_module}', '{leafr}'),
        ];
    }

    /**
     * Defines the restore log rules of the course.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [new restore_log_rule('leafr', 'view all', 'index.php?id={course}', null)];
    }
}
