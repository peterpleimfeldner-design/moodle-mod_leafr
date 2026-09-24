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
 * External functions of mod_leafr.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_leafr_page_viewed' => [
        'classname' => 'mod_leafr\external\page_viewed',
        'description' => 'Records seen pages and the reading position, and updates the completion state.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/leafr:view',
    ],
    'mod_leafr_bookmark_set' => [
        'classname' => 'mod_leafr\external\bookmark_set',
        'description' => 'Creates or updates a bookmark of the current user.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/leafr:view',
    ],
    'mod_leafr_bookmark_delete' => [
        'classname' => 'mod_leafr\external\bookmark_delete',
        'description' => 'Deletes a bookmark of the current user.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/leafr:view',
    ],
    'mod_leafr_bookmark_list' => [
        'classname' => 'mod_leafr\external\bookmark_list',
        'description' => 'Lists the bookmarks of the current user in an activity.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/leafr:view',
    ],
];
