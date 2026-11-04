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
use mod_leafr\local\bookmarks;
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

    /** @var \stdClass Second Leafr instance, in another course */
    protected $leafr2;

    /** @var \context_module Module context of the second instance */
    protected $context2;

    /** @var \stdClass Third student, only enrolled in the second course */
    protected $student3;

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
        bookmarks::set((int)$this->leafr->id, (int)$this->student1->id, 2, 'Remember this');
        bookmarks::set((int)$this->leafr->id, (int)$this->student2->id, 1, '');
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
     * A user is found via bookmarks alone, even without reading progress.
     */
    public function test_contexts_for_bookmarks_only_user(): void {
        $bookmarker = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        bookmarks::set((int)$this->leafr->id, (int)$bookmarker->id, 5, '');

        $contextlist = provider::get_contexts_for_userid((int)$bookmarker->id);
        $this->assertEquals([$this->context->id], $contextlist->get_contextids());

        $userlist = new userlist($this->context, 'mod_leafr');
        provider::get_users_in_context($userlist);
        $this->assertContains((int)$bookmarker->id, $userlist->get_userids());
    }

    /**
     * The progress and the bookmarks of a user are exported.
     */
    public function test_export_user_data(): void {
        $this->export_context_data_for_user((int)$this->student1->id, $this->context, 'mod_leafr');
        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([]);
        $this->assertEquals('1-3', $data->seenpages);
        $this->assertEquals(3, $data->lastpage);

        $bookmarkdata = $writer->get_data([get_string('privacy:bookmarkssubcontext', 'leafr'), 2]);
        $this->assertEquals(2, $bookmarkdata->pageno);
        $this->assertEquals('Remember this', $bookmarkdata->note);
    }

    /**
     * The user preference is exported.
     */
    public function test_export_user_preferences(): void {
        set_user_preference('mod_leafr_simpleview', 1, $this->student1);
        set_user_preference('mod_leafr_spreadmode', 'double', $this->student1);
        provider::export_user_preferences((int)$this->student1->id);
        $preference = writer::with_context(\context_system::instance())->get_user_preferences('mod_leafr');
        $this->assertNotEmpty($preference->mod_leafr_simpleview);
        $this->assertNotEmpty($preference->mod_leafr_spreadmode);
    }

    /**
     * Data of all users in a context is deleted.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertEquals(0, $DB->count_records('leafr_progress', ['leafrid' => $this->leafr->id]));
        $this->assertEquals(0, $DB->count_records('leafr_bookmarks', ['leafrid' => $this->leafr->id]));
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
        $this->assertFalse($DB->record_exists('leafr_bookmarks', ['userid' => $this->student1->id]));
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
        $this->assertTrue($DB->record_exists('leafr_bookmarks', ['userid' => $this->student1->id]));
        $this->assertFalse($DB->record_exists('leafr_bookmarks', ['userid' => $this->student2->id]));
    }

    /**
     * Creates a second activity in another course, with data of the first two students and of a
     * third student who has no data in the first activity.
     */
    protected function create_second_instance(): void {
        $course2 = $this->getDataGenerator()->create_course();
        $this->leafr2 = $this->getDataGenerator()->create_module('leafr', ['course' => $course2->id]);
        $this->context2 = \context_module::instance($this->leafr2->cmid);
        $this->getDataGenerator()->enrol_user($this->student1->id, $course2->id, 'student');
        $this->getDataGenerator()->enrol_user($this->student2->id, $course2->id, 'student');
        $this->student3 = $this->getDataGenerator()->create_and_enrol($course2, 'student');
        progress::record((int)$this->leafr2->id, (int)$this->student1->id, [5, 6], 6);
        progress::record((int)$this->leafr2->id, (int)$this->student2->id, [2], 2);
        progress::record((int)$this->leafr2->id, (int)$this->student3->id, [9], 9);
        bookmarks::set((int)$this->leafr2->id, (int)$this->student1->id, 5, 'Other note');
    }

    /**
     * Counts the progress and bookmark records of a user in an instance.
     *
     * @param \stdClass $leafr Leafr instance
     * @param \stdClass $user User
     * @return int[] Number of progress records and number of bookmarks
     */
    protected function count_records(\stdClass $leafr, \stdClass $user): array {
        global $DB;
        $conditions = ['leafrid' => $leafr->id, 'userid' => $user->id];
        return [$DB->count_records('leafr_progress', $conditions), $DB->count_records('leafr_bookmarks', $conditions)];
    }

    /**
     * With two instances, each context lists exactly its own users and each user exactly the
     * contexts with their data.
     */
    public function test_contexts_and_users_with_two_instances(): void {
        $this->create_second_instance();

        $contextlist = provider::get_contexts_for_userid((int)$this->student1->id);
        $this->assertEqualsCanonicalizing([$this->context->id, $this->context2->id], $contextlist->get_contextids());
        $contextlist = provider::get_contexts_for_userid((int)$this->student3->id);
        $this->assertEquals([$this->context2->id], $contextlist->get_contextids());

        $userlist = new userlist($this->context, 'mod_leafr');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->student1->id, $this->student2->id], $userlist->get_userids());

        $userlist = new userlist($this->context2, 'mod_leafr');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing(
            [$this->student1->id, $this->student2->id, $this->student3->id],
            $userlist->get_userids()
        );
    }

    /**
     * Exporting one context writes only the data of that instance.
     */
    public function test_export_user_data_with_two_instances(): void {
        $this->create_second_instance();
        $subcontext = get_string('privacy:bookmarkssubcontext', 'leafr');

        $this->export_context_data_for_user((int)$this->student1->id, $this->context, 'mod_leafr');
        $this->assertTrue(writer::with_context($this->context)->has_any_data());
        $this->assertFalse(writer::with_context($this->context2)->has_any_data());
        $this->assertEmpty(writer::with_context($this->context)->get_data([$subcontext, 5]));

        $this->export_context_data_for_user((int)$this->student1->id, $this->context2, 'mod_leafr');
        $writer = writer::with_context($this->context2);
        $data = $writer->get_data([]);
        $this->assertEquals('5-6', $data->seenpages);
        $this->assertEquals(6, $data->lastpage);
        $this->assertEquals('Other note', $writer->get_data([$subcontext, 5])->note);
        $this->assertEmpty($writer->get_data([$subcontext, 2]));

        // The first context still holds its own values.
        $this->assertEquals('1-3', writer::with_context($this->context)->get_data([])->seenpages);
    }

    /**
     * Deleting the data of a user in one context keeps the data of the user in the other instance.
     */
    public function test_delete_data_for_user_with_two_instances(): void {
        $this->create_second_instance();

        $contextlist = new approved_contextlist($this->student1, 'mod_leafr', [$this->context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals([0, 0], $this->count_records($this->leafr, $this->student1));
        $this->assertEquals([1, 1], $this->count_records($this->leafr2, $this->student1));
        $this->assertEquals([5, 6], progress::get_seen_pages((int)$this->leafr2->id, (int)$this->student1->id));
        $this->assertEquals([1, 1], $this->count_records($this->leafr, $this->student2));
        $this->assertEquals([1, 0], $this->count_records($this->leafr2, $this->student2));
    }

    /**
     * Deleting the data of selected users in one context keeps their data in the other instance and
     * the data of the other users in the same context.
     */
    public function test_delete_data_for_users_with_two_instances(): void {
        $this->create_second_instance();

        $userlist = new approved_userlist($this->context2, 'mod_leafr', [$this->student1->id, $this->student3->id]);
        provider::delete_data_for_users($userlist);

        $this->assertEquals([0, 0], $this->count_records($this->leafr2, $this->student1));
        $this->assertEquals([0, 0], $this->count_records($this->leafr2, $this->student3));
        $this->assertEquals([1, 0], $this->count_records($this->leafr2, $this->student2));
        $this->assertEquals([1, 1], $this->count_records($this->leafr, $this->student1));
        $this->assertEquals([1, 1], $this->count_records($this->leafr, $this->student2));
    }

    /**
     * Deleting all data in one context keeps the data of the other instance; a context that is
     * not a Leafr activity deletes nothing.
     */
    public function test_delete_data_for_all_users_in_context_with_two_instances(): void {
        global $DB;
        $this->create_second_instance();

        provider::delete_data_for_all_users_in_context(\context_course::instance($this->course->id));
        $this->assertEquals(2, $DB->count_records('leafr_progress', ['leafrid' => $this->leafr->id]));
        $this->assertEquals(2, $DB->count_records('leafr_bookmarks', ['leafrid' => $this->leafr->id]));

        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertEquals(0, $DB->count_records('leafr_progress', ['leafrid' => $this->leafr->id]));
        $this->assertEquals(0, $DB->count_records('leafr_bookmarks', ['leafrid' => $this->leafr->id]));
        $this->assertEquals(3, $DB->count_records('leafr_progress', ['leafrid' => $this->leafr2->id]));
        $this->assertEquals(1, $DB->count_records('leafr_bookmarks', ['leafrid' => $this->leafr2->id]));
    }
}
