<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Restore task for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/leafr/backup/moodle2/restore_leafr_stepslib.php');

/**
 * Defines the restore structure for mod_leafr.
 */
class restore_leafr_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have.
     */
    protected function define_my_settings(): void {
        // No specific settings.
    }

    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_leafr_activity_structure_step('leafr_structure', 'leafr.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return array
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('leafr', ['intro'], 'leafr'),
        ];
    }

    /**
     * Define the decoding rules for links belonging to this activity.
     *
     * @return array
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('LEAFRVIEWBYID', '/mod/leafr/view.php?id=$1', 'course_module'),
            new restore_decode_rule('LEAFRINDEX',    '/mod/leafr/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Define the restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules(): array {
        return [
            new restore_log_rule('leafr', 'view',     'view.php?id={course_module}', '{leafr}'),
            new restore_log_rule('leafr', 'view all', 'index.php?id={course}',       '{leafr}'),
        ];
    }

    /**
     * Define the restore log rules for course.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course(): array {
        return [
            new restore_log_rule('leafr', 'view all', 'index.php?id={course}', null),
        ];
    }
}
