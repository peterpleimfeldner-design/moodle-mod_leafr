<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Course module viewed event for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_leafr\event;

/**
 * Event fired when a Leafr activity is viewed.
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Initialize the event.
     */
    protected function init(): void {
        $this->data['objecttable'] = 'leafr';
        parent::init();
    }

    /**
     * Returns the description of the event.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_course_module_viewed', 'leafr');
    }

    /**
     * Returns the description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '$this->userid' viewed the leafr activity with " .
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
}
