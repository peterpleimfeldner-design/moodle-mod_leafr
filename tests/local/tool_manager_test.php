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
 * Tests for the leafrtool subplugin discovery and dispatch.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_leafr\local\tool_manager
 */
final class tool_manager_test extends \advanced_testcase {
    /**
     * Without any installed leafrtool subplugin, discovery and dispatch must stay a no-op.
     */
    public function test_no_tools_installed(): void {
        $this->resetAfterTest();

        $this->assertSame([], tool_manager::get_enabled_tools());
        $this->assertSame([], tool_manager::get_completion_rules());
        $this->assertFalse(tool_manager::completion_state('leafrtool_doesnotexist', (object)['id' => 1], 2));
    }

    /**
     * Extending the settings form must not fail when no subplugin is installed.
     */
    public function test_extend_settings_form_without_tools(): void {
        $this->resetAfterTest();

        require_once($GLOBALS['CFG']->libdir . '/formslib.php');
        $mform = new \MoodleQuickForm('leafrtooltest', 'post', '');
        tool_manager::extend_settings_form($mform, null);

        // No exception, and the form stays untouched.
        $this->assertSame([], $mform->_elements);
    }
}
