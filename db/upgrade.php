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
 * Upgrade steps for mod_leafr.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the plugin from an older version.
 *
 * @param int $oldversion Version installed before the upgrade
 * @return bool
 */
function xmldb_leafr_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026030501) {
        $table = new xmldb_table('leafr');
        $field = new xmldb_field('totalpages', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'initialpage');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026030501, 'leafr');
    }

    if ($oldversion < 2026092200) {
        // Unreleased development builds removed the table of contents setting. Restore it if necessary.
        $table = new xmldb_table('leafr');
        $field = new xmldb_field('showtoc', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'downloadallowed');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Bookmarks have been removed.
        $table = new xmldb_table('leafr_bookmarks');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Reading progress moves from user preferences into its own table.
        $table = new xmldb_table('leafr_progress');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('leafrid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('seenpages', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('lastpage', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('leafrid', XMLDB_KEY_FOREIGN, ['leafrid'], 'leafr', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('leafrid-userid', XMLDB_INDEX_UNIQUE, ['leafrid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Migrate the old preferences leafr_pos_{cmid} and leafr_progress_{cmid}.
        $sql = "SELECT cm.id, cm.instance
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name = :modname";
        $instances = $DB->get_records_sql_menu($sql, ['modname' => 'leafr']);
        $progress = [];
        $select = $DB->sql_like('name', ':pos') . ' OR ' . $DB->sql_like('name', ':progress');
        $rs = $DB->get_recordset_select(
            'user_preferences',
            $select,
            ['pos' => 'leafr\_pos\_%', 'progress' => 'leafr\_progress\_%'],
            '',
            'id, userid, name, value'
        );
        foreach ($rs as $pref) {
            if (!preg_match('/^leafr_(pos|progress)_(\d+)$/', $pref->name, $matches) || empty($instances[$matches[2]])) {
                continue;
            }
            $key = $instances[$matches[2]] . '-' . $pref->userid;
            $progress[$key] = $progress[$key] ?? [
                'leafrid' => $instances[$matches[2]],
                'userid' => $pref->userid,
                'pages' => [],
                'lastpage' => 0,
            ];
            if ($matches[1] === 'pos') {
                $progress[$key]['lastpage'] = max(0, (int)$pref->value);
            } else {
                $pages = json_decode($pref->value, true);
                $progress[$key]['pages'] = is_array($pages) ? $pages : [];
            }
        }
        $rs->close();

        // Encodes page numbers as compact ranges (same format as \mod_leafr\local\progress::encode_pages()).
        $encode = function (array $pages): string {
            $pages = array_values(array_unique(array_filter(array_map('intval', $pages), function ($p) {
                return $p >= 1;
            })));
            sort($pages);
            $ranges = [];
            for ($i = 0, $count = count($pages); $i < $count; $i++) {
                $start = $pages[$i];
                while ($i + 1 < $count && $pages[$i + 1] === $pages[$i] + 1) {
                    $i++;
                }
                $ranges[] = ($start === $pages[$i]) ? (string)$start : $start . '-' . $pages[$i];
            }
            return implode(',', $ranges);
        };
        foreach ($progress as $item) {
            if ($DB->record_exists('leafr_progress', ['leafrid' => $item['leafrid'], 'userid' => $item['userid']])) {
                continue;
            }
            $DB->insert_record('leafr_progress', (object)[
                'leafrid' => $item['leafrid'],
                'userid' => $item['userid'],
                'seenpages' => $encode($item['pages']),
                'lastpage' => $item['lastpage'],
                'timemodified' => time(),
            ]);
        }

        $select = $DB->sql_like('name', ':pos') . ' OR ' . $DB->sql_like('name', ':progress') . ' OR ' .
            $DB->sql_like('name', ':simpleview');
        $DB->delete_records_select('user_preferences', $select, [
            'pos' => 'leafr\_pos\_%',
            'progress' => 'leafr\_progress\_%',
            'simpleview' => 'leafr\_simpleview\_%',
        ]);

        upgrade_mod_savepoint(true, 2026092200, 'leafr');
    }

    if ($oldversion < 2026092301) {
        // Bookmarks are back, this time with their own table (see also 2026092200 above).
        $table = new xmldb_table('leafr_bookmarks');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('leafrid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('pageno', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('note', XMLDB_TYPE_CHAR, '500', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('leafrid', XMLDB_KEY_FOREIGN, ['leafrid'], 'leafr', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('leafrid-userid-pageno', XMLDB_INDEX_UNIQUE, ['leafrid', 'userid', 'pageno']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026092301, 'leafr');
    }

    if ($oldversion < 2026092400) {
        // Package D: manually defined chapters and the page/chapter based completion rule.
        $table = new xmldb_table('leafr');

        $field = new xmldb_field('completionpages', XMLDB_TYPE_TEXT, null, null, null, null, null, 'completionpage');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('manualchapters', XMLDB_TYPE_TEXT, null, null, null, null, null, 'totalpages');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('usemanualchapters', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'manualchapters');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026092400, 'leafr');
    }

    return true;
}
