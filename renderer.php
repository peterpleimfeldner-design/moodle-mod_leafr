<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Renderer class for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Leafr module renderer class.
 */
class mod_leafr_renderer extends plugin_renderer_base {

    /**
     * Renders the main flipbook reader container.
     *
     * @param stdClass $cm Course module
     * @param stdClass $leafr Leafr instance
     * @param context $context Module context
     * @param string $fileurl PDF file URL
     * @param bool $proavailable Whether Pro features are available
     * @return string HTML output
     */
    public function render_reader(stdClass $cm, stdClass $leafr, context $context, string $fileurl, bool $proavailable): string {
        $data = [
            'cmid'           => $cm->id,
            'fileurl'        => $fileurl,
            'showtoc'        => (bool)$leafr->showtoc,
            'downloadallowed' => (bool)$leafr->downloadallowed && has_capability('mod/leafr:download', $context),
            'proavailable'   => $proavailable,
            'name'           => format_string($leafr->name),
        ];

        return $this->render_from_template('mod_leafr/reader', $data);
    }

    /**
     * Renders an error when no PDF file is uploaded.
     *
     * @return string HTML output
     */
    public function render_no_file(): string {
        return $this->render_from_template('mod_leafr/error', [
            'message' => get_string('nopdfuploaded', 'leafr'),
        ]);
    }

    /**
     * Renders a subtle Pro upgrade teaser.
     *
     * @return string HTML output
     */
    public function render_pro_teaser(): string {
        return html_writer::div(
            get_string('pro_teaser', 'leafr'),
            'leafr-pro-teaser'
        );
    }
}
