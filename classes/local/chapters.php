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

/**
 * Manually defined chapters (title + start page), used when a PDF has no outline of its own,
 * and as an optional way to select required pages for the completion rule.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chapters {
    /** @var int Maximum length of a chapter title. */
    public const MAX_TITLE_LENGTH = 255;

    /**
     * Builds a clean, sorted chapter list from the parallel arrays submitted by the repeated
     * settings form elements. Rows with an empty title or an invalid page are dropped.
     *
     * @param string[] $titles Submitted chapter titles, indexed like the form's repeat elements
     * @param int[] $pages Submitted chapter start pages, same indices as $titles
     * @return array List of ['title' => string, 'page' => int], sorted by page
     */
    public static function from_submitted(array $titles, array $pages): array {
        $chapters = [];
        foreach ($titles as $index => $title) {
            $title = trim((string)$title);
            $page = (int)($pages[$index] ?? 0);
            if ($title === '' || $page < 1) {
                continue;
            }
            $chapters[] = ['title' => \core_text::substr($title, 0, self::MAX_TITLE_LENGTH), 'page' => $page];
        }
        usort($chapters, fn($a, $b) => $a['page'] <=> $b['page']);
        return $chapters;
    }

    /**
     * Encodes a chapter list for storage.
     *
     * @param array $chapters List of ['title' => string, 'page' => int]
     * @return string
     */
    public static function encode(array $chapters): string {
        return $chapters ? json_encode(array_values($chapters)) : '';
    }

    /**
     * Decodes a stored chapter list, ignoring malformed entries.
     *
     * @param string|null $encoded Encoded chapter list
     * @return array List of ['title' => string, 'page' => int], sorted by page
     */
    public static function decode(?string $encoded): array {
        $decoded = json_decode((string)$encoded, true);
        if (!is_array($decoded)) {
            return [];
        }
        $chapters = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry) || !isset($entry['title'], $entry['page'])) {
                continue;
            }
            $page = (int)$entry['page'];
            if ($page < 1) {
                continue;
            }
            $chapters[] = ['title' => (string)$entry['title'], 'page' => $page];
        }
        usort($chapters, fn($a, $b) => $a['page'] <=> $b['page']);
        return $chapters;
    }

    /**
     * Turns a selection of chapters into the page numbers they cover, so the selection can be
     * merged into a "specific pages" completion rule. Each chapter spans from its own start page
     * up to (but excluding) the next chapter's start page, or to the last page of the document if
     * it is the final chapter and the page count is known.
     *
     * @param array $chapters Full chapter list, as returned by {@see decode()} (sorted by page)
     * @param int[] $selected Indexes into $chapters (matching the order shown in the form)
     * @param int $totalpages Number of pages in the document, 0 if unknown
     * @return int[] Page numbers covered by the selected chapters
     */
    public static function pages_for_selection(array $chapters, array $selected, int $totalpages): array {
        $pages = [];
        foreach ($selected as $index) {
            if (!isset($chapters[$index])) {
                continue;
            }
            $start = (int)$chapters[$index]['page'];
            $next = $chapters[$index + 1]['page'] ?? null;
            $end = $next !== null ? $next - 1 : ($totalpages >= $start ? $totalpages : $start);
            for ($page = $start; $page <= $end; $page++) {
                $pages[$page] = $page;
            }
        }
        ksort($pages);
        return array_values($pages);
    }
}
