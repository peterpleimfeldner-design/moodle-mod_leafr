<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Backup task for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/leafr/backup/moodle2/backup_leafr_stepslib.php');

/**
 * Defines the backup structure for mod_leafr.
 */
class backup_leafr_activity_task extends backup_activity_task {

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
        $this->add_step(new backup_leafr_activity_structure_step('leafr_structure', 'leafr.xml'));
    }

    /**
     * Code the transformations to perform in the activity to get transportable (encoded) links.
     *
     * @param string $content Content to encode
     * @return string Encoded content
     */
    public static function encode_content_links(string $content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to module view.
        $search  = "/($base\/mod\/leafr\/view\.php\?id=)([0-9]+)/";
        $replace = '$@LEAFRVIEWBYID*$2@$';
        $content = preg_replace($search, $replace, $content);

        // Link to module index.
        $search  = "/($base\/mod\/leafr\/index\.php\?id=)([0-9]+)/";
        $replace = '$@LEAFRINDEX*$2@$';
        $content = preg_replace($search, $replace, $content);

        return $content;
    }
}
