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
 * Reading progress (seen pages and reading position) of users in a Leafr activity.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {
    /** @var int Completion type: rule disabled. */
    public const COMPLETION_NONE = 0;

    /** @var int Completion type: the last page must be seen. */
    public const COMPLETION_LASTPAGE = 1;

    /** @var int Completion type: a percentage of all pages must be seen. */
    public const COMPLETION_PERCENT = 2;

    /** @var int Completion type: a specific page must be seen. */
    public const COMPLETION_SPECIFICPAGE = 3;

    /** @var int Completion type: specific pages/chapters must all be seen. */
    public const COMPLETION_SPECIFICRANGE = 4;

    /** @var int Upper limit for page numbers accepted from clients. */
    public const MAX_PAGES = 100000;

    /**
     * Encode a list of page numbers as compact ranges, e.g. [1, 2, 3, 7] => "1-3,7".
     *
     * @param int[] $pages Page numbers
     * @return string
     */
    public static function encode_pages(array $pages): string {
        $pages = array_values(array_unique(array_filter(array_map('intval', $pages), function ($p) {
            return $p >= 1;
        })));
        sort($pages);
        $ranges = [];
        $count = count($pages);
        for ($i = 0; $i < $count; $i++) {
            $start = $pages[$i];
            while ($i + 1 < $count && $pages[$i + 1] === $pages[$i] + 1) {
                $i++;
            }
            $ranges[] = ($start === $pages[$i]) ? (string)$start : $start . '-' . $pages[$i];
        }
        return implode(',', $ranges);
    }

    /**
     * Decode compact ranges (see {@see encode_pages()}) into a sorted list of page numbers.
     *
     * @param string|null $encoded Encoded ranges
     * @return int[]
     */
    public static function decode_pages(?string $encoded): array {
        $pages = [];
        foreach (explode(',', (string)$encoded) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (strpos($part, '-') !== false) {
                [$from, $to] = array_map('intval', explode('-', $part, 2));
                $to = min($to, self::MAX_PAGES);
                for ($p = max(1, $from); $p <= $to; $p++) {
                    $pages[$p] = $p;
                }
            } else if ((int)$part >= 1) {
                $pages[(int)$part] = (int)$part;
            }
        }
        ksort($pages);
        return array_values($pages);
    }

    /**
     * Get the progress record of a user.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @return stdClass|null
     */
    public static function get_record(int $leafrid, int $userid): ?stdClass {
        global $DB;
        return $DB->get_record('leafr_progress', ['leafrid' => $leafrid, 'userid' => $userid]) ?: null;
    }

    /**
     * Get the pages a user has seen.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @return int[]
     */
    public static function get_seen_pages(int $leafrid, int $userid): array {
        $record = self::get_record($leafrid, $userid);
        return $record ? self::decode_pages($record->seenpages) : [];
    }

    /**
     * Get the last reading position of a user.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @return int Page number, 0 if the user never opened the document
     */
    public static function get_last_page(int $leafrid, int $userid): int {
        $record = self::get_record($leafrid, $userid);
        return $record ? (int)$record->lastpage : 0;
    }

    /**
     * Store newly seen pages and the current reading position.
     *
     * @param int $leafrid Leafr instance id
     * @param int $userid User id
     * @param int[] $pages Seen page numbers
     * @param int $currentpage Current reading position (0 = unchanged)
     * @return int[] Pages that were not seen before
     */
    public static function record(int $leafrid, int $userid, array $pages, int $currentpage = 0): array {
        global $DB;

        $pages = array_filter(array_map('intval', $pages), function ($p) {
            return $p >= 1 && $p <= self::MAX_PAGES;
        });
        $record = self::get_record($leafrid, $userid);
        $seen = $record ? self::decode_pages($record->seenpages) : [];
        $newpages = array_values(array_diff(array_unique($pages), $seen));
        sort($newpages);

        $currentpage = ($currentpage >= 1 && $currentpage <= self::MAX_PAGES) ? $currentpage : 0;
        if (!$record) {
            $DB->insert_record('leafr_progress', (object)[
                'leafrid' => $leafrid,
                'userid' => $userid,
                'seenpages' => self::encode_pages($newpages),
                'lastpage' => $currentpage,
                'timemodified' => time(),
            ]);
        } else if ($newpages || ($currentpage && $currentpage != $record->lastpage)) {
            $record->seenpages = self::encode_pages(array_merge($seen, $newpages));
            if ($currentpage) {
                $record->lastpage = $currentpage;
            }
            $record->timemodified = time();
            $DB->update_record('leafr_progress', $record);
        }
        return $newpages;
    }

    /**
     * The pages required by the "specific pages/chapters" completion rule, limited to the
     * document's actual page count.
     *
     * @param stdClass $leafr Leafr instance record (needs completionpages and totalpages)
     * @return int[]
     */
    public static function required_pages(stdClass $leafr): array {
        $total = (int)$leafr->totalpages;
        $pages = self::decode_pages($leafr->completionpages ?? '');
        return $total >= 1 ? array_values(array_filter($pages, function ($p) use ($total) {
            return $p <= $total;
        })) : $pages;
    }

    /**
     * Whether a user fulfils the page based completion rule of an activity.
     *
     * @param stdClass $leafr Leafr instance record
     * @param int $userid User id
     * @return bool
     */
    public static function is_complete(stdClass $leafr, int $userid): bool {
        $total = (int)$leafr->totalpages;
        if ($total < 1) {
            return false;
        }
        $seen = array_filter(self::get_seen_pages((int)$leafr->id, $userid), function ($p) use ($total) {
            return $p <= $total;
        });

        switch ((int)$leafr->completiontype) {
            case self::COMPLETION_LASTPAGE:
                return in_array($total, $seen, true);
            case self::COMPLETION_PERCENT:
                $required = max(1, min(100, (int)$leafr->completionpercent));
                return count($seen) * 100 >= $required * $total;
            case self::COMPLETION_SPECIFICPAGE:
                $page = max(1, min($total, (int)$leafr->completionpage));
                return in_array($page, $seen, true);
            case self::COMPLETION_SPECIFICRANGE:
                $required = self::required_pages($leafr);
                return !empty($required) && !array_diff($required, $seen);
            default:
                return false;
        }
    }

    /**
     * Delete the progress of all users in an activity.
     *
     * @param int $leafrid Leafr instance id
     */
    public static function delete_for_instance(int $leafrid): void {
        global $DB;
        $DB->delete_records('leafr_progress', ['leafrid' => $leafrid]);
    }
}
