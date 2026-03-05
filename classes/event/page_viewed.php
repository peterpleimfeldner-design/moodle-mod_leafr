<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page viewed event for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_leafr\event;

/**
 * Event fired when a specific page in a Leafr flipbook is viewed.
 */
class page_viewed extends \core\event\base {

    /**
     * Initialize the event.
     */
    protected function init(): void {
        $this->data['crud']        = 'r';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'leafr';
    }

    /**
     * Returns the name of the event.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_page_viewed', 'leafr');
    }

    /**
     * Returns the description.
     *
     * @return string
     */
    public function get_description(): string {
        $pageno = $this->other['pageno'] ?? '?';
        return "The user with id '$this->userid' viewed page $pageno in leafr activity with " .
               "course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/leafr/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Custom validation.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (empty($this->other['pageno'])) {
            throw new \coding_exception('pageno must be set in $other.');
        }
    }
}
