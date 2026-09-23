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

namespace mod_leafr\local;

use stdClass;

/**
 * Private, per-user bookmarks in a Leafr flipbook.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bookmarks {
    /** @var int Maximum length of a bookmark note. */
    public const MAX_NOTE_LENGTH = 500;

    /**
     * Returns all bookmarks of a user in an activity, ordered by page.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @return stdClass[] Records keyed by page number
     */
    public static function get_for_user(int $leafrid, int $userid): array {
        global $DB;
        return $DB->get_records(
            'leafr_bookmarks',
            ['leafrid' => $leafrid, 'userid' => $userid],
            'pageno ASC',
            'id, pageno, note, timecreated, timemodified'
        );
    }

    /**
     * Creates or updates the bookmark of a user for a page.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @param int $pageno 1-based page number
     * @param string $note Optional note, truncated to {@see MAX_NOTE_LENGTH}
     * @return stdClass The bookmark record
     */
    public static function set(int $leafrid, int $userid, int $pageno, string $note = ''): stdClass {
        global $DB;

        $note = \core_text::substr(trim($note), 0, self::MAX_NOTE_LENGTH);
        $existing = $DB->get_record('leafr_bookmarks', ['leafrid' => $leafrid, 'userid' => $userid, 'pageno' => $pageno]);
        $now = time();
        if ($existing) {
            $existing->note = $note;
            $existing->timemodified = $now;
            $DB->update_record('leafr_bookmarks', $existing);
            return $existing;
        }
        $record = (object)[
            'leafrid' => $leafrid,
            'userid' => $userid,
            'pageno' => $pageno,
            'note' => $note,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('leafr_bookmarks', $record);
        return $record;
    }

    /**
     * Deletes the bookmark of a user for a page.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @param int $pageno 1-based page number
     * @return bool Whether a bookmark was deleted
     */
    public static function delete(int $leafrid, int $userid, int $pageno): bool {
        global $DB;
        return $DB->delete_records('leafr_bookmarks', ['leafrid' => $leafrid, 'userid' => $userid, 'pageno' => $pageno]);
    }

    /**
     * Deletes all bookmarks of all users in an activity.
     *
     * @param int $leafrid Leafr instance id
     */
    public static function delete_for_instance(int $leafrid): void {
        global $DB;
        $DB->delete_records('leafr_bookmarks', ['leafrid' => $leafrid]);
    }
}
