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
 * Tests for the Office tool's per-activity settings.
 *
 * @package   leafrtool_office
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \leafrtool_office\local\settings
 */
final class settings_test extends \advanced_testcase {
    /**
     * Settings default to "download the PDF" when none are stored, including for a new activity
     * (leafrid 0).
     */
    public function test_get_defaults(): void {
        $this->resetAfterTest();
        $this->assertSame(0, settings::get(0)->downloadoriginal);
        $this->assertSame(0, settings::get(1)->downloadoriginal);
    }

    /**
     * Settings can be saved and read back, updated in place, and only affect their own instance.
     */
    public function test_save_and_get(): void {
        $this->resetAfterTest();

        settings::save(1, true);
        $this->assertSame(1, settings::get(1)->downloadoriginal);
        $this->assertSame(0, settings::get(2)->downloadoriginal);

        settings::save(1, false);
        $this->assertSame(0, settings::get(1)->downloadoriginal);
    }

    /**
     * Deleting an instance removes only its own settings.
     */
    public function test_delete_for_instance(): void {
        $this->resetAfterTest();

        settings::save(1, true);
        settings::save(2, true);

        settings::delete_for_instance(1);

        $this->assertSame(0, settings::get(1)->downloadoriginal);
        $this->assertSame(1, settings::get(2)->downloadoriginal);
    }
}
