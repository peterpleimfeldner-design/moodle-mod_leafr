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
use mod_leafr\task\recalculate_completion;

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
        $this->setAdminUser();

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
        $events = array_filter($sink->get_events(), function ($e) {
            return $e instanceof \mod_leafr\event\page_viewed;
        });
        $this->assertCount(3, $events);
        $sink->close();

        $result = $this->call(['cmid' => $leafr->cmid, 'pages' => [4, 5, 6], 'currentpage' => 6, 'totalpages' => 12]);
        $this->assertTrue($result['completed']);
        $completion = new \completion_info($course);
        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
    }

    /**
     * A single learner cannot change a known page count, neither down nor up; teachers can.
     */
    public function test_totalpages_only_changed_by_editors(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        $DB->set_field('leafr', 'totalpages', 12, ['id' => $leafr->id]);

        $this->setUser($student);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 1]);
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 100000]);
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));

        $this->setUser($teacher);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 14]);
        $this->assertEquals(14, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
        $this->assertEquals(1, $DB->get_field('leafr', 'totalpagesconfirmed', ['id' => $leafr->id]));
    }

    /**
     * A learner who is the first to report a too low page count cannot complete the activity for
     * everybody: the count most learners report replaces it, and completion is recalculated.
     */
    public function test_too_low_first_report_is_overruled_by_the_majority(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cheater = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $leafr = $this->getDataGenerator()->create_module('leafr', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpageseen' => 1,
            'completiontype' => progress::COMPLETION_LASTPAGE,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $completion = new \completion_info($course);

        // The first report while the page count is unknown is used, whoever sends it.
        $this->setUser($cheater);
        $this->assertTrue($this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 1])['completed']);
        $this->assertEquals(1, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));

        // One other report is a tie, which the higher count wins: a count that is too high only
        // delays completion.
        $this->setUser($student1);
        $allpages = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
        $this->assertTrue($this->call(['cmid' => $leafr->cmid, 'pages' => $allpages, 'totalpages' => 12])['completed']);
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
        $this->assertSame($allpages, progress::get_seen_pages((int)$leafr->id, (int)$student1->id));

        $this->setUser($student2);
        $this->assertFalse($this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 12])['completed']);
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
        $this->assertEquals(0, $DB->get_field('leafr', 'totalpagesconfirmed', ['id' => $leafr->id]));

        // Completion of the other users is recalculated in the background.
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $cheater->id)->completionstate);
        $this->setAdminUser();
        $this->runAdhocTasks(recalculate_completion::class);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $cheater->id)->completionstate);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student1->id)->completionstate);

        // The cheater cannot move the count again, neither down nor up.
        $this->setUser($cheater);
        $this->assertFalse($this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 1])['completed']);
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
        $this->call(['cmid' => $leafr->cmid, 'pages' => [100000], 'totalpages' => 100000]);
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(recalculate_completion::class));
        // Such a page is stored for the cheater alone and does not count for completion.
        $this->assertFalse(progress::is_complete($DB->get_record('leafr', ['id' => $leafr->id]), (int)$cheater->id));
    }

    /**
     * While the page count is not confirmed, a learner's pages up to their own count are kept, even
     * beyond the count that applies (they would be lost otherwise). Once a teacher confirmed the
     * count, pages beyond it are not stored, and one call stores a limited number of pages.
     */
    public function test_pages_beyond_the_page_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $cheater1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cheater2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);

        // Two learners agree on a count that is too low.
        foreach ([$cheater1, $cheater2] as $user) {
            $this->setUser($user);
            $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 2]);
        }
        $this->setUser($student);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1, 2, 3, 4], 'totalpages' => 12]);
        $this->assertSame([1, 2, 3, 4], progress::get_seen_pages((int)$leafr->id, (int)$student->id));

        $this->setUser($teacher);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 12]);
        $this->setUser($student);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [5, 13, 500], 'totalpages' => 500]);
        $this->assertSame([1, 2, 3, 4, 5], progress::get_seen_pages((int)$leafr->id, (int)$student->id));

        $this->setUser($cheater1);
        // Only the first 1000 different pages of a call count: page 7 comes after 1000 others.
        $this->call(['cmid' => $leafr->cmid, 'pages' => array_merge(range(13, 1012), [7]), 'totalpages' => 12]);
        $this->assertSame([1], progress::get_seen_pages((int)$leafr->id, (int)$cheater1->id));
        $this->call(['cmid' => $leafr->cmid, 'pages' => array_merge(array_fill(0, 1500, 3), [7]), 'totalpages' => 12]);
        $this->assertSame([1, 3, 7], progress::get_seen_pages((int)$leafr->id, (int)$cheater1->id));
    }

    /**
     * A count reported by a teacher is confirmed: learners' reports no longer change it, even when
     * most of them report another count.
     */
    public function test_teacher_count_is_not_changed_by_learners(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);

        $this->setUser($teacher);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 12]);
        foreach ([1, 2, 3] as $i) {
            $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
            $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 30]);
        }
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
    }

    /**
     * When a teacher corrects the page count, completion is recalculated for the learners, keeping
     * a completion state a teacher has set by hand.
     */
    public function test_teacher_correction_recalculates_completion(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $overridden = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $leafr = $this->getDataGenerator()->create_module('leafr', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpageseen' => 1,
            'completiontype' => progress::COMPLETION_LASTPAGE,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $completion = new \completion_info($course);
        $allpages = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

        $this->setUser($student);
        $this->call(['cmid' => $leafr->cmid, 'pages' => $allpages, 'totalpages' => 12]);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);

        // No background task is needed when nothing changes.
        $this->call(['cmid' => $leafr->cmid, 'pages' => [12], 'totalpages' => 12]);
        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(recalculate_completion::class));

        $this->setUser($overridden);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 12]);

        // A teacher marks a learner who has not finished reading as complete by hand.
        $this->setUser($teacher);
        $completion->update_state($cm, COMPLETION_COMPLETE, $overridden->id, true);
        $this->assertEquals($teacher->id, $completion->get_data($cm, false, $overridden->id)->overrideby);

        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 14]);
        $this->assertEquals(14, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));

        $this->setAdminUser();
        $this->runAdhocTasks(recalculate_completion::class);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
        $data = $completion->get_data($cm, false, $overridden->id);
        $this->assertEquals(COMPLETION_COMPLETE, $data->completionstate);
        $this->assertEquals($teacher->id, $data->overrideby);

        // A teacher may also lower the page count.
        $this->setUser($teacher);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 12]);
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
        $this->setAdminUser();
        $this->runAdhocTasks(recalculate_completion::class);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
    }

    /**
     * The recalculation leaves out users who are no longer enrolled.
     */
    public function test_recalculation_skips_unenrolled_users(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $former = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $leafr = $this->getDataGenerator()->create_module('leafr', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpageseen' => 1,
            'completiontype' => progress::COMPLETION_LASTPAGE,
        ]);

        $this->setUser($teacher);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 14]);
        $this->setUser($former);
        $this->call(['cmid' => $leafr->cmid, 'pages' => range(1, 12), 'totalpages' => 14]);
        $this->assertFalse($DB->record_exists('course_modules_completion', ['coursemoduleid' => $leafr->cmid,
            'userid' => $former->id, 'completionstate' => COMPLETION_COMPLETE]));

        $this->setAdminUser();
        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $enrol->unenrol_user($instance, $former->id);

        // With 12 pages the former learner would be complete, but is no longer enrolled.
        $this->setUser($teacher);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 12]);
        $this->setAdminUser();
        $this->runAdhocTasks(recalculate_completion::class);
        $this->assertFalse($DB->record_exists('course_modules_completion', ['coursemoduleid' => $leafr->cmid,
            'userid' => $former->id, 'completionstate' => COMPLETION_COMPLETE]));
    }

    /**
     * Replacing the PDF forgets the page count and the learners' reports.
     */
    public function test_new_pdf_resets_the_page_count(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        $this->setUser($student);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 12]);
        $this->setUser($teacher);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 12]);

        \mod_leafr\local\page_count::reset((int)$leafr->id);

        $record = $DB->get_record('leafr', ['id' => $leafr->id]);
        $this->assertEquals(0, $record->totalpages);
        $this->assertEquals(0, $record->totalpagesconfirmed);
        $reported = $DB->get_field('leafr_progress', 'reportedtotal', ['leafrid' => $leafr->id, 'userid' => $student->id]);
        $this->assertEquals(0, $reported);

        // The next report is used again, also from a learner.
        $this->setUser($student);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1], 'totalpages' => 20]);
        $this->assertEquals(20, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
    }

    /**
     * Users without access to the activity are rejected.
     */
    public function test_requires_access(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\require_login_exception::class);
        $this->call(['cmid' => $leafr->cmid, 'pages' => [1]]);
    }
}
