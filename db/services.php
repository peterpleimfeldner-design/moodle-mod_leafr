<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * External function declarations for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_leafr_save_position' => [
        'classname'    => 'mod_leafr\\external\\save_position',
        'description'  => 'Save the current reading position for a user.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/leafr:view',
    ],

    'mod_leafr_get_position' => [
        'classname'    => 'mod_leafr\\external\\get_position',
        'description'  => 'Get the saved reading position for a user.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'mod/leafr:view',
    ],

    'mod_leafr_page_viewed' => [
        'classname'    => 'mod_leafr\\external\\page_viewed',
        'description'  => 'Track page views and trigger completion if criteria are met.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/leafr:view',
    ],

    'mod_leafr_bookmark_create' => [
        'classname'    => 'mod_leafr\\external\\bookmark_create',
        'description'  => 'Create a named bookmark (Pro).',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/leafr:view',
    ],

    'mod_leafr_bookmark_delete' => [
        'classname'    => 'mod_leafr\\external\\bookmark_delete',
        'description'  => 'Delete a bookmark (Pro).',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/leafr:view',
    ],

    'mod_leafr_bookmark_list' => [
        'classname'    => 'mod_leafr\\external\\bookmark_list',
        'description'  => 'List all bookmarks for a user in an activity (Pro).',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'mod/leafr:view',
    ],
];
