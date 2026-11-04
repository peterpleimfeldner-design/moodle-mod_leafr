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
 * Upgrade check, step 2 and 4: writes every Leafr related row of the site to a JSON file.
 *
 * Covers all Leafr tables (leafr*, leafrtool_*), the course modules of Leafr activities, their
 * completion states, the Leafr user preferences, the stored PDF files and the plugin versions.
 * Rows are keyed by id, so compare.php can show exactly what an upgrade changed.
 *
 * Usage: php snapshot.php /path/to/moodle/config.php /path/to/output.json
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
if (count($argv) < 3) {
    die("Usage: php snapshot.php /path/to/moodle/config.php /path/to/output.json\n");
}
// Moodle may change the working directory while it starts, so the output path is fixed first.
$outfile = preg_match('~^([a-zA-Z]:)?[/\\\\]~', $argv[2]) ? $argv[2] : getcwd() . '/' . $argv[2];
require($argv[1]);

global $DB;
$tables = [];
$dbtables = $DB->get_tables(false);
foreach ($dbtables as $table) {
    if ($table === 'leafr' || strpos($table, 'leafr_') === 0 || strpos($table, 'leafrtool_') === 0) {
        $tables[$table] = $DB->get_records($table, null, 'id');
    }
}
$moduleid = $DB->get_field('modules', 'id', ['name' => 'leafr'], MUST_EXIST);
$tables['course_modules (leafr)'] = $DB->get_records(
    'course_modules',
    ['module' => $moduleid],
    'id',
    'id, course, instance, section, visible, groupmode, groupingid, completion, completionview, completionexpected'
);
$tables['course_modules_completion (leafr)'] = $DB->get_records_sql(
    "SELECT cmc.id, cmc.coursemoduleid, cmc.userid, cmc.completionstate, cmc.overrideby, cmc.timemodified
       FROM {course_modules_completion} cmc
       JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
      WHERE cm.module = :moduleid
   ORDER BY cmc.id",
    ['moduleid' => $moduleid]
);
$tables['user_preferences (mod_leafr_*)'] = $DB->get_records_select(
    'user_preferences',
    $DB->sql_like('name', ':name'),
    ['name' => 'mod\\_leafr\\_%'],
    'id'
);
$tables['files (mod_leafr)'] = $DB->get_records_select(
    'files',
    "component = 'mod_leafr' AND filename <> '.'",
    null,
    'id',
    'id, contextid, filearea, itemid, filepath, filename, contenthash, filesize'
);
$tables['config_plugins (versions)'] = $DB->get_records_select(
    'config_plugins',
    "name = 'version' AND (plugin = 'mod_leafr' OR plugin LIKE 'leafrtool\\_%')",
    null,
    'id'
);

$out = [];
foreach ($tables as $name => $rows) {
    $clean = [];
    foreach ($rows as $id => $row) {
        $clean[(string)$id] = array_map(function ($value) {
            return $value === null ? null : (string)$value;
        }, (array)$row);
    }
    $out[$name] = $clean;
}
file_put_contents($outfile, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
foreach ($out as $name => $rows) {
    printf("%-36s %6d rows\n", $name, count($rows));
}
