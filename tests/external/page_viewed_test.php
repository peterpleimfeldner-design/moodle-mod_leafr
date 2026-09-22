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
use mod_leafr\local\progress;

/**
 * Tests for the web service mod_leafr_page_viewed.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_leafr\external\page_viewed
 */
final class page_viewed_test extends \advanced_testcase {
    /**
     * Calls the web service and cleans the result like the web service layer does.
     *
     * @param array $args Arguments
     * @return array
     */
    protected function call(array $args): array {
        $result = page_viewed::execute(...array_values(array_merge(
            ['cmid' => 0, 'pages' => [], 'currentpage' => 0, 'totalpages' => 0],
            $args
        )));
        return external_api::clean_returnvalue(page_viewed::execute_returns(), $result);
    }

    /**
     * A student's pages are stored, the page count is learnt and completion is updated.
     */
    public function test_records_progress_and_completes(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $leafr = $this->getDataGenerator()->create_module('leafr', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpageseen' => 1,
            'completiontype' => progress::COMPLETION_PERCENT,
            'completionpercent' => 50,
        ]);
        $this->setUser($student);

        $sink = $this->redirectEvents();
        $result = $this->call(['cmid' => $leafr->cmid, 'pages' => [1, 2, 3, 50], 'currentpage' => 3, 'totalpages' => 12]);
        $this->assertFalse($result['completed']);
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
        $this->assertSame([1, 2, 3], progress::get_seen_pages((int)$leafr->id, (int)$student->id));
        $this->assertSame(3, progress::get_last_page((int)$leafr->id, (int)$student->id));
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \mod_leafr\event\page_viewed);
        $this->assertCount(3, $events);
        $sink->close();

        $result = $this->call(['cmid' => $leafr->cmid, 'pages' => [4, 5, 6], 'currentpage' => 6, 'totalpages' => 12]);
        $this->assertTrue($result['completed']);
        $completion = new \completion_info($course);
        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
    }

    /**
     * Students cannot change a page count that is already known, teachers can.
     */
    public function test_totalpages_only_changed_by_editors(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        $DB->set_field('leafr', 'totalpages', 12, ['id' => $leafr->id]);

        $this->setUser($student);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 1]);
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));

        $this->setUser($teacher);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 14]);
        $this->assertEquals(14, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
    }

    /**
     * Users without access to the activity are rejected.
     */
    public function test_requires_access(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\require_login_exception::class);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1]]);
    }
}
