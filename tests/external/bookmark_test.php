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

namespace mod_leafr\external;

use core_external\external_api;
use mod_leafr\local\bookmarks;

/**
 * Tests for the mod_leafr_bookmark_* web services.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_leafr\external\bookmark_set
 * @covers    \mod_leafr\external\bookmark_delete
 * @covers    \mod_leafr\external\bookmark_list
 */
final class bookmark_test extends \advanced_testcase {
    /** @var \stdClass Course */
    protected $course;

    /** @var \stdClass Leafr instance */
    protected $leafr;

    /** @var \stdClass Student */
    protected $student;

    /**
     * Creates a course with a Leafr activity and an enrolled student.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($this->student);
    }

    /**
     * Creates a bookmark, then updates its note, through the web service.
     */
    public function test_set_creates_and_updates(): void {
        $result = bookmark_set::execute($this->leafr->cmid, 5, 'A note');
        $result = external_api::clean_returnvalue(bookmark_set::execute_returns(), $result);
        $this->assertSame(5, $result['pageno']);
        $this->assertSame('A note', $result['note']);

        $result = bookmark_set::execute($this->leafr->cmid, 5, 'Updated');
        $result = external_api::clean_returnvalue(bookmark_set::execute_returns(), $result);
        $this->assertSame('Updated', $result['note']);
        $this->assertCount(1, bookmarks::get_for_user((int)$this->leafr->id, (int)$this->student->id));
    }

    /**
     * A page number below 1 is rejected.
     */
    public function test_set_rejects_invalid_page(): void {
        $this->expectException(\invalid_parameter_exception::class);
        bookmark_set::execute($this->leafr->cmid, 0, '');
    }

    /**
     * Deleting reports whether a bookmark existed.
     */
    public function test_delete(): void {
        bookmark_set::execute($this->leafr->cmid, 5, '');

        $result = bookmark_delete::execute($this->leafr->cmid, 5);
        $result = external_api::clean_returnvalue(bookmark_delete::execute_returns(), $result);
        $this->assertTrue($result['deleted']);

        $result = bookmark_delete::execute($this->leafr->cmid, 5);
        $result = external_api::clean_returnvalue(bookmark_delete::execute_returns(), $result);
        $this->assertFalse($result['deleted']);
    }

    /**
     * Listing returns only the bookmarks of the current user, ordered by page.
     */
    public function test_list_is_private_and_ordered(): void {
        bookmark_set::execute($this->leafr->cmid, 8, '');
        bookmark_set::execute($this->leafr->cmid, 3, 'note');

        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($other);
        bookmark_set::execute($this->leafr->cmid, 1, 'someone else');

        $this->setUser($this->student);
        $result = bookmark_list::execute($this->leafr->cmid);
        $result = external_api::clean_returnvalue(bookmark_list::execute_returns(), $result);
        $this->assertSame([['pageno' => 3, 'note' => 'note'], ['pageno' => 8, 'note' => '']], $result['bookmarks']);
    }

    /**
     * Users without access to the activity are rejected.
     */
    public function test_requires_access(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\require_login_exception::class);
        bookmark_set::execute($this->leafr->cmid, 1, '');
    }
}
