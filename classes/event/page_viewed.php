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

namespace mod_leafr\event;

/**
 * Event triggered when a user sees a page of a Leafr document for the first time.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int pageno: The page number.
 * }
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_viewed extends \core\event\base {
    /**
     * Initialises the event data.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'leafr';
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpageviewed', 'leafr');
    }

    /**
     * Returns the non-localised event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' viewed page {$this->other['pageno']} of the leafr activity " .
            "with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Returns the URL related to the event.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/leafr/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Validates the custom data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (empty($this->other['pageno'])) {
            throw new \coding_exception('The \'pageno\' value must be set in other.');
        }
    }

    /**
     * Returns the mapping of the object id for restoring logs.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'leafr', 'restore' => 'leafr'];
    }

    /**
     * Returns the mapping of the other data for restoring logs.
     *
     * @return bool False, no ids to map
     */
    public static function get_other_mapping() {
        return false;
    }
}
