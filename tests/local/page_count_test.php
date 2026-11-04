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
 * Tests for the page count of a Leafr activity.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_leafr\local\page_count
 */
final class page_count_test extends \advanced_testcase {
    /**
     * Creates an activity and returns its record.
     *
     * @return \stdClass
     */
    protected function create_leafr(): \stdClass {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        return $DB->get_record('leafr', ['id' => $leafr->id], '*', MUST_EXIST);
    }

    /**
     * The count most learners reported wins; a tie keeps the current count or, if the current
     * count is not among the most reported, takes the highest of them.
     */
    public function test_most_reported(): void {
        global $DB;
        $leafr = $this->create_leafr();
        $id = (int)$leafr->id;

        $this->assertSame(0, page_count::most_reported($id, 0));

        page_count::report($leafr, 101, 10, false);
        $this->assertSame(10, page_count::most_reported($id, 0));
        page_count::report($leafr, 102, 12, false);
        $this->assertSame(10, page_count::most_reported($id, 10), 'A tie keeps the current count');
        $this->assertSame(12, page_count::most_reported($id, 0), 'A tie without the current count takes the highest');
        page_count::report($leafr, 103, 12, false);
        $this->assertSame(12, page_count::most_reported($id, 10));

        // A changed report replaces the user's earlier one instead of adding another: 10 now has
        // no report left, 30 has one.
        page_count::report($leafr, 101, 30, false);
        $this->assertEquals(30, $DB->get_field('leafr_progress', 'reportedtotal', ['leafrid' => $id, 'userid' => 101]));
        $this->assertSame(1, $DB->count_records('leafr_progress', ['leafrid' => $id, 'userid' => 101]));
        $this->assertSame(12, page_count::most_reported($id, 30), '12 has two reports, 30 one');
        page_count::report($leafr, 103, 30, false);
        $this->assertSame(30, page_count::most_reported($id, 12), '30 has two reports now, 12 one');
    }

    /**
     * A count that nobody reported yet counts as one report.
     */
    public function test_unreported_current_count_counts_once(): void {
        $leafr = $this->create_leafr();
        $id = (int)$leafr->id;

        page_count::report($leafr, 101, 5, false);
        $this->assertSame(16, page_count::most_reported($id, 16));
        page_count::report($leafr, 102, 5, false);
        $this->assertSame(5, page_count::most_reported($id, 16));
    }

    /**
     * report() updates the record and the database and says whether the count changed.
     */
    public function test_report(): void {
        global $DB;
        $leafr = $this->create_leafr();

        $this->assertFalse(page_count::report($leafr, 101, 0, false), 'No count reported');
        $this->assertTrue(page_count::report($leafr, 101, 12, false));
        $this->assertEquals(12, $leafr->totalpages);
        $this->assertEquals(12, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));
        $this->assertFalse(page_count::report($leafr, 102, 12, false));

        // A trusted report confirms the count, even if it does not change it.
        $this->assertFalse(page_count::report($leafr, 103, 12, true));
        $this->assertEquals(1, $DB->get_field('leafr', 'totalpagesconfirmed', ['id' => $leafr->id]));
        $this->assertTrue(page_count::report($leafr, 103, 13, true));
        $this->assertFalse(page_count::report($leafr, 104, 20, false));
        $this->assertFalse(page_count::report($leafr, 105, 20, false));
        $this->assertEquals(13, $DB->get_field('leafr', 'totalpages', ['id' => $leafr->id]));

        // Reports are kept in the progress record and capped like page numbers.
        page_count::report($leafr, 106, 999999, false);
        $this->assertEquals(
            progress::MAX_PAGES,
            $DB->get_field('leafr_progress', 'reportedtotal', ['leafrid' => $leafr->id, 'userid' => 106])
        );
        $this->assertSame('', (string)$DB->get_field('leafr_progress', 'seenpages', ['leafrid' => $leafr->id, 'userid' => 106]));
    }
}
