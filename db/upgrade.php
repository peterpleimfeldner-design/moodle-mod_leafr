<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade script for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute mod_leafr upgrade from the given old version.
 *
 * @param int $oldversion Old plugin version
 * @return bool true if upgrade succeeded
 */
function xmldb_leafr_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Future upgrade steps will be added here.
    if ($oldversion < 2026030501) {
        $table = new xmldb_table('leafr');
        $field = new xmldb_field('totalpages', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'initialpage');

        // Conditionally launch add field totalpages.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Leafr savepoint reached.
        upgrade_mod_savepoint(true, 2026030501, 'leafr');
    }

    return true;
}
