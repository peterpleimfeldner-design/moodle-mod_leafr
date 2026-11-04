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

namespace leafrtool_confirm\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use leafrtool_confirm\local\confirm;

/**
 * Tests for the privacy provider of the read confirmation tool.
 *
 * @package   leafrtool_confirm
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \leafrtool_confirm\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass First Leafr instance */
    protected $leafr1;

    /** @var \stdClass Second Leafr instance, in another course */
    protected $leafr2;

    /** @var \context_module Module context of the first instance */
    protected $context1;

    /** @var \context_module Module context of the second instance */
    protected $context2;

    /** @var \stdClass First student, confirmed in both instances */
    protected $student1;

    /** @var \stdClass Second student, confirmed only in the first instance */
    protected $student2;

    /**
     * Creates two activities in two courses with confirmations of two students.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $this->leafr1 = $generator->create_module('leafr', ['course' => $course1->id]);
        $this->leafr2 = $generator->create_module('leafr', ['course' => $course2->id]);
        $this->context1 = \context_module::instance($this->leafr1->cmid);
        $this->context2 = \context_module::instance($this->leafr2->cmid);
        $this->student1 = $generator->create_and_enrol($course1, 'student');
        $this->student2 = $generator->create_and_enrol($course1, 'student');
        $generator->enrol_user($this->student1->id, $course2->id, 'student');
        $generator->enrol_user($this->student2->id, $course2->id, 'student');

        confirm::save_settings((int)$this->leafr1->id, true, 'First text');
        confirm::save_settings((int)$this->leafr2->id, true, 'Second text');
        $confirmations = [
            [$this->leafr1, $this->student1, 1700000000],
            [$this->leafr1, $this->student2, 1700000100],
            [$this->leafr2, $this->student1, 1700000200],
        ];
        foreach ($confirmations as [$leafr, $user, $time]) {
            $DB->insert_record('leafrtool_confirm_log', (object)[
                'leafrid' => $leafr->id,
                'userid' => $user->id,
                'timeconfirmed' => $time,
            ]);
        }
    }

    /**
     * The confirmation log is described in the metadata.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('leafrtool_confirm'));
        $items = $collection->get_collection();
        $this->assertCount(1, $items);
        $table = reset($items);
        $this->assertEquals('leafrtool_confirm_log', $table->get_name());
        $this->assertEqualsCanonicalizing(['userid', 'timeconfirmed'], array_keys($table->get_privacy_fields()));
    }

    /**
     * The contexts of a user and the users of a context are found.
     */
    public function test_contexts_and_users(): void {
        $contextlist = provider::get_contexts_for_userid((int)$this->student1->id);
        $this->assertEqualsCanonicalizing([$this->context1->id, $this->context2->id], $contextlist->get_contextids());
        $contextlist = provider::get_contexts_for_userid((int)$this->student2->id);
        $this->assertEquals([$this->context1->id], $contextlist->get_contextids());
        $other = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid((int)$other->id)->get_contextids());

        $userlist = new userlist($this->context1, 'leafrtool_confirm');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->student1->id, $this->student2->id], $userlist->get_userids());

        $userlist = new userlist($this->context2, 'leafrtool_confirm');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$this->student1->id], $userlist->get_userids());

        $userlist = new userlist(\context_system::instance(), 'leafrtool_confirm');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());
    }

    /**
     * The privacy subsystem reaches the tool as a component of its own.
     */
    public function test_found_by_privacy_manager(): void {
        $manager = new \core_privacy\manager();
        $this->assertTrue($manager->component_is_compliant('leafrtool_confirm'));
        $contextlists = $manager->get_contexts_for_userid((int)$this->student2->id);
        $components = [];
        foreach ($contextlists as $contextlist) {
            $components[$contextlist->get_component()] = $contextlist->get_contextids();
        }
        $this->assertArrayHasKey('leafrtool_confirm', $components);
        $this->assertEquals([$this->context1->id], $components['leafrtool_confirm']);
    }

    /**
     * The confirmation of a user is exported in the right context only.
     */
    public function test_export_user_data(): void {
        $subcontext = [get_string('pluginname', 'leafrtool_confirm')];

        $this->export_context_data_for_user((int)$this->student2->id, $this->context1, 'leafrtool_confirm');
        $data = writer::with_context($this->context1)->get_data($subcontext);
        $this->assertEquals(transform::datetime(1700000100), $data->timeconfirmed);
        $this->assertFalse(writer::with_context($this->context2)->has_any_data());

        writer::reset();
        // The second student has no confirmation in the second instance, so nothing is exported.
        $this->export_context_data_for_user((int)$this->student2->id, $this->context2, 'leafrtool_confirm');
        $this->assertFalse(writer::with_context($this->context2)->has_any_data());

        $this->export_all_data_for_user((int)$this->student1->id, 'leafrtool_confirm');
        $data = writer::with_context($this->context2)->get_data($subcontext);
        $this->assertEquals(transform::datetime(1700000200), $data->timeconfirmed);
        $data = writer::with_context($this->context1)->get_data($subcontext);
        $this->assertEquals(transform::datetime(1700000000), $data->timeconfirmed);
    }

    /**
     * Deleting all data in one context keeps the other instance and the settings.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertEquals(3, $DB->count_records('leafrtool_confirm_log'));

        provider::delete_data_for_all_users_in_context($this->context1);
        $this->assertEquals(0, $DB->count_records('leafrtool_confirm_log', ['leafrid' => $this->leafr1->id]));
        $this->assertTrue(confirm::is_confirmed((int)$this->leafr2->id, (int)$this->student1->id));
        $this->assertSame('First text', confirm::get_settings((int)$this->leafr1->id)->confirmtext);
    }

    /**
     * Deleting the data of a user in one context keeps the user's other confirmation and the other
     * user's confirmation.
     */
    public function test_delete_data_for_user(): void {
        $contextlist = new approved_contextlist($this->student1, 'leafrtool_confirm', [$this->context1->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertFalse(confirm::is_confirmed((int)$this->leafr1->id, (int)$this->student1->id));
        $this->assertSame(1700000200, confirm::get_confirmed_time((int)$this->leafr2->id, (int)$this->student1->id));
        $this->assertSame(1700000100, confirm::get_confirmed_time((int)$this->leafr1->id, (int)$this->student2->id));
    }

    /**
     * Deleting the data of selected users in one context keeps the other users and the other instance.
     */
    public function test_delete_data_for_users(): void {
        $userlist = new approved_userlist($this->context1, 'leafrtool_confirm', [$this->student2->id]);
        provider::delete_data_for_users($userlist);
        $this->assertFalse(confirm::is_confirmed((int)$this->leafr1->id, (int)$this->student2->id));
        $this->assertTrue(confirm::is_confirmed((int)$this->leafr1->id, (int)$this->student1->id));
        $this->assertTrue(confirm::is_confirmed((int)$this->leafr2->id, (int)$this->student1->id));

        $userlist = new approved_userlist($this->context2, 'leafrtool_confirm', [$this->student1->id, $this->student2->id]);
        provider::delete_data_for_users($userlist);
        $this->assertFalse(confirm::is_confirmed((int)$this->leafr2->id, (int)$this->student1->id));
        $this->assertTrue(confirm::is_confirmed((int)$this->leafr1->id, (int)$this->student1->id));
    }
}
