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
 * The changes an upgrade from the previous release may make to existing data (see compare.php).
 *
 * Update this file with every release that changes the database: list each new column with the
 * value db/upgrade.php gives existing rows, and explain why.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

return [
    // 1.4.0: a page count known before the upgrade stays fixed for learners, as in 1.3.0.
    'leafr' => [
        'added' => [
            'totalpagesconfirmed' => function (array $row): string {
                return (int)$row['totalpages'] > 0 ? '1' : '0';
            },
        ],
    ],
    // 1.4.0: no learner has reported a page count with the new rules yet.
    'leafr_progress' => [
        'added' => [
            'reportedtotal' => function (array $row): string {
                return '0';
            },
        ],
    ],
    // Plugin versions are what an upgrade changes.
    'config_plugins (versions)' => [
        'changed' => ['value'],
    ],
];
