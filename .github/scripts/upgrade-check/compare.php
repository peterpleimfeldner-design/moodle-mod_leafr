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
 * Upgrade check, step 5: compares two snapshots taken before and after an upgrade.
 *
 * Every row that existed before must still exist with the same values. The only allowed changes
 * are listed in expected-changes.php (new columns with the value the upgrade step must give
 * them, and columns that may change, such as plugin versions). Prints row counts and a SHA-256
 * checksum per table and exits with 1 on any unexpected difference. Does not need Moodle.
 *
 * Usage: php compare.php before.json after.json
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (count($argv) < 3) {
    fwrite(STDERR, "Usage: php compare.php before.json after.json\n");
    exit(2);
}
$before = json_decode(file_get_contents($argv[1]), true);
$after = json_decode(file_get_contents($argv[2]), true);
$expected = require(__DIR__ . '/expected-changes.php');

/**
 * Checksum over the given columns of all rows.
 *
 * @param array $rows Rows keyed by id
 * @param array $columns Column names
 * @return string
 */
function upgradecheck_checksum(array $rows, array $columns): string {
    ksort($rows);
    $data = [];
    foreach ($rows as $id => $row) {
        $data[$id] = array_intersect_key($row, array_flip($columns));
        ksort($data[$id]);
    }
    return substr(hash('sha256', json_encode($data)), 0, 16);
}

$errors = [];
printf("%-36s %8s %8s  %-16s %-16s %s\n", 'Table', 'before', 'after', 'checksum before', 'checksum after', 'result');
foreach ($before as $table => $rowsbefore) {
    $rowsafter = $after[$table] ?? null;
    if ($rowsafter === null) {
        $errors[] = "$table: table missing after the upgrade";
        continue;
    }
    $rules = $expected[$table] ?? [];
    $colsbefore = $rowsbefore ? array_keys(reset($rowsbefore)) : [];
    $colsafter = $rowsafter ? array_keys(reset($rowsafter)) : $colsbefore;
    $added = array_diff($colsafter, $colsbefore);
    $removed = $rowsbefore && $rowsafter ? array_diff($colsbefore, $colsafter) : [];
    $maychange = $rules['changed'] ?? [];
    foreach ($removed as $col) {
        $errors[] = "$table: column '$col' removed";
    }
    foreach ($added as $col) {
        if ($rowsbefore && !isset($rules['added'][$col])) {
            $errors[] = "$table: unexpected new column '$col'";
        }
    }
    foreach (array_diff_key($rowsbefore, $rowsafter) as $id => $row) {
        $errors[] = "$table: row $id lost";
    }
    foreach (array_diff_key($rowsafter, $rowsbefore) as $id => $row) {
        $errors[] = "$table: row $id added by the upgrade";
    }
    foreach ($rowsbefore as $id => $row) {
        if (!isset($rowsafter[$id])) {
            continue;
        }
        foreach ($row as $col => $value) {
            if (in_array($col, $maychange, true) || !array_key_exists($col, $rowsafter[$id])) {
                continue;
            }
            if ($rowsafter[$id][$col] !== $value) {
                $errors[] = "$table: row $id column '$col' changed from " . json_encode($value) . ' to '
                    . json_encode($rowsafter[$id][$col]);
            }
        }
        foreach ($rules['added'] ?? [] as $col => $rule) {
            $want = $rule($row);
            if (($rowsafter[$id][$col] ?? null) !== $want) {
                $errors[] = "$table: row $id new column '$col' is " . json_encode($rowsafter[$id][$col] ?? null)
                    . ', expected ' . json_encode($want);
            }
        }
    }
    $common = array_values(array_diff(array_intersect($colsbefore, $colsafter), $maychange));
    $sumbefore = upgradecheck_checksum($rowsbefore, $common);
    $sumafter = upgradecheck_checksum(array_intersect_key($rowsafter, $rowsbefore), $common);
    $note = $added ? ' (new: ' . implode(', ', $added) . ')' : '';
    $note .= $maychange ? ' (may change: ' . implode(', ', $maychange) . ')' : '';
    printf(
        "%-36s %8d %8d  %-16s %-16s %s\n",
        $table,
        count($rowsbefore),
        count($rowsafter),
        $sumbefore,
        $sumafter,
        ($sumbefore === $sumafter ? 'same' : 'DIFFERENT') . $note
    );
}
foreach (array_diff_key($after, $before) as $table => $rows) {
    printf("%-36s %8s %8d  new table\n", $table, '-', count($rows));
}

if ($errors) {
    echo "\nUNEXPECTED DIFFERENCES (" . count($errors) . "):\n - " . implode("\n - ", array_slice($errors, 0, 100)) . "\n";
    exit(1);
}
echo "\nOK: no data lost or changed; only the expected changes from expected-changes.php.\n";
