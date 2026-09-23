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

namespace leafrtool_confirm\local;

/**
 * Tests for the read confirmation settings and log.
 *
 * @package   leafrtool_confirm
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \leafrtool_confirm\local\confirm
 */
final class confirm_test extends \advanced_testcase {
    /**
     * Settings default to "not required" with the default text when none are stored.
     */
    public function test_get_settings_defaults(): void {
        $this->resetAfterTest();
        $settings = confirm::get_settings(1);
        $this->assertFalse($settings->requireconfirm);
        $this->assertSame(get_string('confirmtext_default', 'leafrtool_confirm'), $settings->confirmtext);
    }

    /**
     * Settings can be saved and read back, and updated in place.
     */
    public function test_save_and_get_settings(): void {
        $this->resetAfterTest();

        confirm::save_settings(5, true, 'Please confirm.');
        $settings = confirm::get_settings(5);
        $this->assertTrue($settings->requireconfirm);
        $this->assertSame('Please confirm.', $settings->confirmtext);

        confirm::save_settings(5, false, 'Updated text.');
        $settings = confirm::get_settings(5);
        $this->assertFalse($settings->requireconfirm);
        $this->assertSame('Updated text.', $settings->confirmtext);

        // A different instance is unaffected.
        $this->assertFalse(confirm::get_settings(6)->requireconfirm);
    }

    /**
     * Confirming is recorded once, is idempotent, and only affects the given instance and user.
     */
    public function test_confirm_and_is_confirmed(): void {
        $this->resetAfterTest();

        $this->assertFalse(confirm::is_confirmed(3, 11));
        $first = confirm::record_confirmation(3, 11);
        $this->assertGreaterThan(0, $first);
        $this->assertTrue(confirm::is_confirmed(3, 11));
        $this->assertSame($first, confirm::get_confirmed_time(3, 11));

        // Confirming again keeps the original timestamp.
        $this->assertSame($first, confirm::record_confirmation(3, 11));

        $this->assertFalse(confirm::is_confirmed(3, 12));
        $this->assertFalse(confirm::is_confirmed(4, 11));
    }

    /**
     * Deleting an instance removes its settings and confirmations, but not those of others.
     */
    public function test_delete_for_instance(): void {
        $this->resetAfterTest();

        confirm::save_settings(7, true, 'Text');
        confirm::record_confirmation(7, 21);
        confirm::save_settings(8, true, 'Other text');
        confirm::record_confirmation(8, 21);

        confirm::delete_for_instance(7);

        $this->assertFalse(confirm::get_settings(7)->requireconfirm);
        $this->assertSame(get_string('confirmtext_default', 'leafrtool_confirm'), confirm::get_settings(7)->confirmtext);
        $this->assertFalse(confirm::is_confirmed(7, 21));

        $this->assertTrue(confirm::get_settings(8)->requireconfirm);
        $this->assertTrue(confirm::is_confirmed(8, 21));
    }

    /**
     * Deleting confirmations for an instance keeps its settings.
     */
    public function test_delete_confirmations_for_instance(): void {
        $this->resetAfterTest();

        confirm::save_settings(9, true, 'Text');
        confirm::record_confirmation(9, 31);

        confirm::delete_confirmations_for_instance(9);

        $this->assertTrue(confirm::get_settings(9)->requireconfirm);
        $this->assertFalse(confirm::is_confirmed(9, 31));
    }
}
