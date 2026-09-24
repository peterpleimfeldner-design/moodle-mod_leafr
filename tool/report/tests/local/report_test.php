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

namespace leafrtool_report\local;

use mod_leafr\local\progress;

/**
 * Tests for the data-sparse reading overview.
 *
 * @package   leafrtool_report
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \leafrtool_report\local\report
 */
final class report_test extends \advanced_testcase {
    /**
     * Data for {@see test_escape_csv_cell()}.
     *
     * @return array
     */
    public static function csv_cell_provider(): array {
        return [
            'plain name' => ['Anna Muster', 'Anna Muster'],
            'empty' => ['', ''],
            'formula equals' => ['=cmd|"/c calc"!A1', "'=cmd|\"/c calc\"!A1"],
            'formula plus' => ['+1+1', "'+1+1"],
            'formula minus' => ['-1+1', "'-1+1"],
            'formula at' => ['@SUM(1+1)', "'@SUM(1+1)"],
            'leading tab' => ["\tHidden", "'\tHidden"],
            'minus in the middle is not a formula start' => ['Anna-Lena Muster', 'Anna-Lena Muster'],
        ];
    }

    /**
     * Cell values that would be interpreted as spreadsheet formulas are neutralised.
     *
     * @dataProvider csv_cell_provider
     * @param string $value Input value
     * @param string $expected Expected, safe value
     */
    public function test_escape_csv_cell(string $value, string $expected): void {
        $this->assertSame($expected, report::escape_csv_cell($value));
    }

    /**
     * Rows show whether the required pages were read, and whether the activity is complete.
     */
    public function test_get_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student', ['lastname' => 'Aaa']);
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student', ['lastname' => 'Bbb']);
        $leafr = $this->getDataGenerator()->create_module('leafr', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpageseen' => 1,
            'completiontype' => progress::COMPLETION_SPECIFICRANGE,
            'completionpages' => '1-3',
        ]);
        $DB->set_field('leafr', 'totalpages', 10, ['id' => $leafr->id]);
        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $record = $DB->get_record('leafr', ['id' => $leafr->id], '*', MUST_EXIST);

        progress::record((int)$leafr->id, (int)$student1->id, [1, 2, 3]);
        progress::record((int)$leafr->id, (int)$student2->id, [1]);

        $rows = report::get_rows($cm, $record);
        $this->assertCount(2, $rows);

        $byid = [];
        foreach ($rows as $row) {
            $byid[$row['userid']] = $row;
        }
        $this->assertTrue($byid[$student1->id]['requiredseen']);
        $this->assertFalse($byid[$student2->id]['requiredseen']);
    }

    /**
     * A larger cohort of students with individually varied reading progress: each row must show
     * that person's own percentage and completion state, not one that leaked from another user
     * (a realistic risk given the per-user queries in get_rows() run in a loop), and rows come
     * back sorted by name regardless of enrolment or creation order.
     */
    public function test_get_rows_with_many_students_keeps_data_per_person(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $leafr = $this->getDataGenerator()->create_module('leafr', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpageseen' => 1,
            'completiontype' => progress::COMPLETION_PERCENT,
            'completionpercent' => 100,
        ]);
        $DB->set_field('leafr', 'totalpages', 20, ['id' => $leafr->id]);
        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $record = $DB->get_record('leafr', ['id' => $leafr->id], '*', MUST_EXIST);

        // Ten students, enrolled in a shuffled (non-alphabetical) order, each reading a different,
        // deterministic number of the 20 pages (2, 4, ..., 20), so every expected percentage is
        // distinct (including the 100%/"completed" boundary for the last one) and any mix-up
        // between rows is easy to spot.
        $students = [];
        $lastnames = ['Juh', 'Ana', 'Zoe', 'Ben', 'Ida', 'Max', 'Eva', 'Tom', 'Uwe', 'Cai'];
        $completion = new \completion_info($course);
        foreach ($lastnames as $index => $lastname) {
            $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['lastname' => $lastname]);
            $students[$lastname] = $student;
            progress::record((int)$leafr->id, (int)$student->id, range(1, ($index + 1) * 2));
            // Mirrors what the page_viewed webservice does after recording progress: recording
            // alone does not update Moodle's own (cached) completion state, so 'completed' would
            // otherwise stay false for everyone regardless of actual progress.
            $completion->update_state($cm, COMPLETION_UNKNOWN, (int)$student->id);
        }

        $rows = report::get_rows($cm, $record);
        $this->assertCount(10, $rows);

        // Sorted by full name (which starts with the fixed firstname "Student" here, so the
        // deciding part is the lastname), independent of enrolment order.
        $sortednames = array_column($rows, 'fullname');
        $expectednames = $sortednames;
        sort($expectednames, SORT_STRING | SORT_FLAG_CASE);
        $this->assertSame($expectednames, $sortednames);

        $byuserid = array_column($rows, null, 'userid');
        foreach ($lastnames as $index => $lastname) {
            // Pages seen is (index + 1) * 2 out of 20 total, expressed as a percent.
            $expectedpercent = ($index + 1) * 10;
            $row = $byuserid[(int)$students[$lastname]->id];
            $this->assertSame($expectedpercent, $row['requiredpercent'], "Wrong percentage for $lastname");
            $this->assertSame($expectedpercent >= 100, $row['requiredseen'], "Wrong requiredseen for $lastname");
            $this->assertSame($expectedpercent >= 100, $row['completed'], "Wrong completed for $lastname");
        }
    }

    /**
     * The group filter only returns members of the given group.
     */
    public function test_get_rows_respects_group_filter(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $student1->id]);
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $record = $DB->get_record('leafr', ['id' => $leafr->id], '*', MUST_EXIST);

        $rows = report::get_rows($cm, $record, $group->id);
        $this->assertCount(1, $rows);
        $this->assertSame((int)$student1->id, $rows[0]['userid']);
    }

    /**
     * confirm_required() is false when leafrtool_confirm is not required, and reflects its
     * setting when it is.
     */
    public function test_confirm_required(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        $record = $DB->get_record('leafr', ['id' => $leafr->id], '*', MUST_EXIST);

        $this->assertFalse(report::confirm_required($record));

        \leafrtool_confirm\local\confirm::save_settings((int)$leafr->id, true, 'Please confirm.');
        $this->assertTrue(report::confirm_required($record));
    }
}
