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
 * Tests for manually defined chapters.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_leafr\local\chapters
 */
final class chapters_test extends \basic_testcase {
    /**
     * Rows with an empty title or an invalid page are dropped, the rest is sorted by page.
     */
    public function test_from_submitted(): void {
        $chapters = chapters::from_submitted(
            ['Chapter Two', '', 'Chapter One', 'No page'],
            [10, 5, 1, 0]
        );
        $this->assertSame([
            ['title' => 'Chapter One', 'page' => 1],
            ['title' => 'Chapter Two', 'page' => 10],
        ], $chapters);
    }

    /**
     * Encoding and decoding a chapter list round-trips, malformed entries are ignored.
     */
    public function test_encode_decode(): void {
        $chapters = [['title' => 'Intro', 'page' => 1], ['title' => 'Details', 'page' => 5]];
        $this->assertSame($chapters, chapters::decode(chapters::encode($chapters)));
        $this->assertSame('', chapters::encode([]));
        $this->assertSame([], chapters::decode(''));
        $this->assertSame([], chapters::decode(null));
        $this->assertSame([], chapters::decode('not json'));
        $this->assertSame([], chapters::decode('[{"title":"No page"}]'));
    }

    /**
     * Selecting chapters expands them into the pages they cover, up to the next chapter or the
     * end of the document.
     */
    public function test_pages_for_selection(): void {
        $chapters = [
            ['title' => 'Intro', 'page' => 1],
            ['title' => 'Middle', 'page' => 4],
            ['title' => 'End', 'page' => 8],
        ];
        $this->assertSame([1, 2, 3], chapters::pages_for_selection($chapters, [0], 10));
        $this->assertSame([8, 9, 10], chapters::pages_for_selection($chapters, [2], 10));
        $this->assertSame([8], chapters::pages_for_selection($chapters, [2], 0));
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], chapters::pages_for_selection($chapters, [0, 1], 10));
        $this->assertSame([], chapters::pages_for_selection($chapters, [99], 10));
    }
}
