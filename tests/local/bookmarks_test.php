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
 * Tests for private, per-user bookmarks.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_leafr\local\bookmarks
 */
final class bookmarks_test extends \advanced_testcase {
    /**
     * Setting a bookmark twice for the same page updates it instead of creating a second one.
     */
    public function test_set_creates_and_updates(): void {
        global $DB;
        $this->resetAfterTest();

        $record = bookmarks::set(1, 2, 5, 'First note');
        $this->assertSame(5, $record->pageno);
        $this->assertSame('First note', $record->note);
        $this->assertEquals(1, $DB->count_records('leafr_bookmarks', ['leafrid' => 1, 'userid' => 2]));

        $updated = bookmarks::set(1, 2, 5, 'Updated note');
        $this->assertSame($record->id, $updated->id);
        $this->assertSame('Updated note', $updated->note);
        $this->assertEquals(1, $DB->count_records('leafr_bookmarks', ['leafrid' => 1, 'userid' => 2]));
    }

    /**
     * A note longer than the maximum length is truncated, not rejected.
     */
    public function test_note_is_truncated(): void {
        $this->resetAfterTest();

        $long = str_repeat('a', bookmarks::MAX_NOTE_LENGTH + 50);
        $record = bookmarks::set(1, 2, 3, $long);
        $this->assertSame(bookmarks::MAX_NOTE_LENGTH, strlen($record->note));
    }

    /**
     * Bookmarks of a user are returned ordered by page, and are private per user.
     */
    public function test_get_for_user_is_ordered_and_private(): void {
        $this->resetAfterTest();

        bookmarks::set(1, 2, 8, '');
        bookmarks::set(1, 2, 3, '');
        bookmarks::set(1, 3, 1, 'someone else');

        $pages = array_map(fn($b) => $b->pageno, array_values(bookmarks::get_for_user(1, 2)));
        $this->assertSame([3, 8], $pages);
    }

    /**
     * Deleting a bookmark removes only that page, and reports whether it existed.
     */
    public function test_delete(): void {
        $this->resetAfterTest();

        bookmarks::set(1, 2, 5, '');
        $this->assertTrue(bookmarks::delete(1, 2, 5));
        $this->assertFalse(bookmarks::delete(1, 2, 5));
        $this->assertSame([], bookmarks::get_for_user(1, 2));
    }

    /**
     * Deleting for an instance removes the bookmarks of all users in it, not other instances.
     */
    public function test_delete_for_instance(): void {
        $this->resetAfterTest();

        bookmarks::set(1, 2, 1, '');
        bookmarks::set(1, 3, 2, '');
        bookmarks::set(9, 2, 1, '');

        bookmarks::delete_for_instance(1);
        $this->assertSame([], bookmarks::get_for_user(1, 2));
        $this->assertSame([], bookmarks::get_for_user(1, 3));
        $this->assertCount(1, bookmarks::get_for_user(9, 2));
    }
}
