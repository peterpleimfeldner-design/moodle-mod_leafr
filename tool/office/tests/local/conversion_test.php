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

namespace leafrtool_office\local;

/**
 * Tests for the conversion status tracking.
 *
 * @package   leafrtool_office
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \leafrtool_office\local\conversion
 */
final class conversion_test extends \advanced_testcase {
    /**
     * No record exists until one is created.
     */
    public function test_get_returns_false_when_none(): void {
        $this->resetAfterTest();
        $this->assertFalse(conversion::get(1));
    }

    /**
     * Marking pending creates a record; marking pending again for a different hash updates it in
     * place rather than creating a second one.
     */
    public function test_mark_pending_creates_and_updates(): void {
        $this->resetAfterTest();

        conversion::mark_pending(1, 'hash-a');
        $record = conversion::get(1);
        $this->assertSame('hash-a', $record->sourcecontenthash);
        $this->assertSame(conversion::STATUS_PENDING, $record->status);

        conversion::mark_pending(1, 'hash-b');
        $records = $this->count_records(1);
        $this->assertSame(1, $records);
        $this->assertSame('hash-b', conversion::get(1)->sourcecontenthash);
    }

    /**
     * Completing and failing update the status (and, for failures, the error message) in place.
     */
    public function test_mark_complete_and_failed(): void {
        $this->resetAfterTest();

        conversion::mark_pending(2, 'hash');
        conversion::mark_complete(2);
        $this->assertSame(conversion::STATUS_COMPLETE, conversion::get(2)->status);

        conversion::mark_pending(2, 'hash-again');
        conversion::mark_failed(2, 'Something went wrong.');
        $record = conversion::get(2);
        $this->assertSame(conversion::STATUS_FAILED, $record->status);
        $this->assertSame('Something went wrong.', $record->errormessage);
    }

    /**
     * Deleting an instance removes only its own record.
     */
    public function test_delete_for_instance(): void {
        $this->resetAfterTest();

        conversion::mark_pending(3, 'hash');
        conversion::mark_pending(4, 'other-hash');

        conversion::delete_for_instance(3);

        $this->assertFalse(conversion::get(3));
        $this->assertNotFalse(conversion::get(4));
    }

    /**
     * Counts how many conversion records exist for a leafr instance (helper for this test only).
     *
     * @param int $leafrid Leafr instance id
     * @return int
     */
    private function count_records(int $leafrid): int {
        global $DB;
        return $DB->count_records('leafrtool_office_conversion', ['leafrid' => $leafrid]);
    }
}
