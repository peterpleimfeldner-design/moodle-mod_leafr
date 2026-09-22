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
 * Tests for the reading progress and the completion rules.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_leafr\local\progress
 * @covers    \mod_leafr\completion\custom_completion
 */
final class progress_test extends \advanced_testcase {
    /**
     * Data for {@see test_encode_decode()}.
     *
     * @return array
     */
    public static function pages_provider(): array {
        return [
            'empty' => [[], ''],
            'single' => [[4], '4'],
            'range' => [[1, 2, 3], '1-3'],
            'mixed and unsorted' => [[7, 1, 2, 3, 9, 8, 12], '1-3,7-9,12'],
            'duplicates and invalid' => [[2, 2, 0, -3, 1], '1-2'],
        ];
    }

    /**
     * Page lists are stored as compact ranges and restored unchanged.
     *
     * @dataProvider pages_provider
     * @param array $pages Page numbers
     * @param string $encoded Expected encoding
     */
    public function test_encode_decode(array $pages, string $encoded): void {
        $this->assertSame($encoded, progress::encode_pages($pages));
        $expected = array_values(array_unique(array_filter($pages, fn($p) => $p >= 1)));
        sort($expected);
        $this->assertSame($expected, progress::decode_pages($encoded));
    }

    /**
     * Recording returns only new pages and keeps the reading position.
     */
    public function test_record(): void {
        $this->resetAfterTest();

        $this->assertSame([1, 2], progress::record(5, 7, [2, 1], 2));
        $this->assertSame([3], progress::record(5, 7, [1, 2, 3], 3));
        $this->assertSame([], progress::record(5, 7, [3], 1));

        $this->assertSame([1, 2, 3], progress::get_seen_pages(5, 7));
        $this->assertSame(1, progress::get_last_page(5, 7));
        $this->assertSame(0, progress::get_last_page(5, 8));
        $this->assertSame([], progress::get_seen_pages(6, 7));
    }

    /**
     * Data for {@see test_is_complete()}.
     *
     * @return array
     */
    public static function completion_provider(): array {
        return [
            'last page seen' => [progress::COMPLETION_LASTPAGE, 0, 0, [10], true],
            'last page not seen' => [progress::COMPLETION_LASTPAGE, 0, 0, [1, 2, 9], false],
            'percent reached' => [progress::COMPLETION_PERCENT, 50, 0, [1, 2, 3, 4, 5], true],
            'percent not reached' => [progress::COMPLETION_PERCENT, 50, 0, [1, 2, 3, 4], false],
            'specific page seen' => [progress::COMPLETION_SPECIFICPAGE, 0, 4, [4], true],
            'specific page not seen' => [progress::COMPLETION_SPECIFICPAGE, 0, 4, [5], false],
            'specific page beyond the end' => [progress::COMPLETION_SPECIFICPAGE, 0, 25, [10], true],
            'rule disabled' => [progress::COMPLETION_NONE, 0, 0, [1, 10], false],
        ];
    }

    /**
     * The completion types are evaluated correctly.
     *
     * @dataProvider completion_provider
     * @param int $type Completion type
     * @param int $percent Required percentage
     * @param int $page Required page
     * @param array $seen Seen pages
     * @param bool $expected Expected result
     */
    public function test_is_complete(int $type, int $percent, int $page, array $seen, bool $expected): void {
        $this->resetAfterTest();
        $leafr = (object)[
            'id' => 3,
            'completiontype' => $type,
            'completionpercent' => $percent,
            'completionpage' => $page,
            'totalpages' => 10,
        ];
        progress::record(3, 11, $seen);
        $this->assertSame($expected, progress::is_complete($leafr, 11));
    }

    /**
     * Without a known page count the activity is never complete.
     */
    public function test_is_complete_without_pagecount(): void {
        $this->resetAfterTest();
        progress::record(3, 11, [1]);
        $leafr = (object)['id' => 3, 'completiontype' => 1, 'completionpercent' => 100, 'completionpage' => 1,
            'totalpages' => 0];
        $this->assertFalse(progress::is_complete($leafr, 11));
    }

    /**
     * The custom completion rule is registered and evaluated by Moodle.
     */
    public function test_custom_completion(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $leafr = $this->getDataGenerator()->create_module('leafr', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpageseen' => 1,
            'completiontype' => progress::COMPLETION_LASTPAGE,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $this->assertEquals(['completionpageseen' => 1], $cm->customdata['customcompletionrules']);

        $customcompletion = new \mod_leafr\completion\custom_completion($cm, (int)$student->id);
        $this->assertSame(COMPLETION_INCOMPLETE, $customcompletion->get_state('completionpageseen'));

        $DB->set_field('leafr', 'totalpages', 12, ['id' => $leafr->id]);
        progress::record((int)$leafr->id, (int)$student->id, [12]);
        $this->assertSame(COMPLETION_COMPLETE, $customcompletion->get_state('completionpageseen'));
        $this->assertNotEmpty($customcompletion->get_custom_rule_descriptions()['completionpageseen']);
    }
}
