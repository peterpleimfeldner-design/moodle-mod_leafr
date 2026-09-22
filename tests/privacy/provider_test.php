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

namespace mod_leafr\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_leafr\local\progress;

/**
 * Tests for the privacy provider.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_leafr\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass Course */
    protected $course;

    /** @var \stdClass Leafr instance */
    protected $leafr;

    /** @var \context_module Module context */
    protected $context;

    /** @var \stdClass First student */
    protected $student1;

    /** @var \stdClass Second student */
    protected $student2;

    /**
     * Creates an activity with progress of two students.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $this->course->id]);
        $this->context = \context_module::instance($this->leafr->cmid);
        $this->student1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->student2 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        progress::record((int)$this->leafr->id, (int)$this->student1->id, [1, 2, 3], 3);
        progress::record((int)$this->leafr->id, (int)$this->student2->id, [1], 1);
    }

    /**
     * The contexts and users with data are found.
     */
    public function test_contexts_and_users(): void {
        $contextlist = provider::get_contexts_for_userid((int)$this->student1->id);
        $this->assertEquals([$this->context->id], $contextlist->get_contextids());

        $other = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid((int)$other->id)->get_contextids());

        $userlist = new userlist($this->context, 'mod_leafr');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->student1->id, $this->student2->id], $userlist->get_userids());
    }

    /**
     * The progress of a user is exported.
     */
    public function test_export_user_data(): void {
        $this->export_context_data_for_user((int)$this->student1->id, $this->context, 'mod_leafr');
        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([]);
        $this->assertEquals('1-3', $data->seenpages);
        $this->assertEquals(3, $data->lastpage);
    }

    /**
     * The user preference is exported.
     */
    public function test_export_user_preferences(): void {
        set_user_preference('mod_leafr_simpleview', 1, $this->student1);
        provider::export_user_preferences((int)$this->student1->id);
        $preference = writer::with_context(\context_system::instance())->get_user_preferences('mod_leafr');
        $this->assertNotEmpty($preference->mod_leafr_simpleview);
    }

    /**
     * Data of all users in a context is deleted.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertEquals(0, $DB->count_records('leafr_progress', ['leafrid' => $this->leafr->id]));
    }

    /**
     * Data of one user is deleted.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $contextlist = new approved_contextlist($this->student1, 'mod_leafr', [$this->context->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertFalse($DB->record_exists('leafr_progress', ['userid' => $this->student1->id]));
        $this->assertTrue($DB->record_exists('leafr_progress', ['userid' => $this->student2->id]));
    }

    /**
     * Data of selected users is deleted.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $userlist = new approved_userlist($this->context, 'mod_leafr', [$this->student2->id]);
        provider::delete_data_for_users($userlist);
        $this->assertTrue($DB->record_exists('leafr_progress', ['userid' => $this->student1->id]));
        $this->assertFalse($DB->record_exists('leafr_progress', ['userid' => $this->student2->id]));
    }
}
