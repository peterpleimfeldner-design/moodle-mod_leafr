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

use leafrtool_confirm\local\confirm;

/**
 * Tests for the leafrtool subplugin discovery and dispatch, using the installed
 * leafrtool_confirm as the reference subplugin.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_leafr\local\tool_manager
 */
final class tool_manager_test extends \advanced_testcase {
    /**
     * leafrtool_confirm is discovered and contributes its "confirmread" completion rule.
     */
    public function test_get_enabled_tools_and_completion_rules(): void {
        $this->resetAfterTest();

        $this->assertContains('leafrtool_confirm', tool_manager::get_enabled_tools());

        $rules = tool_manager::get_completion_rules();
        $this->assertArrayHasKey('confirmread', $rules);
        $this->assertSame('leafrtool_confirm', $rules['confirmread']['component']);
        $this->assertSame('completionconfirmread', $rules['confirmread']['langkey']);
    }

    /**
     * A disabled subplugin is not returned by get_enabled_tools(), but delete_instance() still
     * reaches it so no data is orphaned.
     */
    public function test_disabled_tool_is_excluded_from_enabled_but_not_from_deletion(): void {
        $this->resetAfterTest();

        set_config('disabled', 1, 'leafrtool_confirm');
        $this->assertNotContains('leafrtool_confirm', tool_manager::get_enabled_tools());

        confirm::save_settings(41, true, 'Text');
        confirm::record_confirmation(41, 1);
        tool_manager::delete_instance(41);
        $this->assertFalse(confirm::get_settings(41)->requireconfirm);
        $this->assertFalse(confirm::is_confirmed(41, 1));
    }

    /**
     * save_settings()/get_form_data() round-trip through the subplugin's own table.
     */
    public function test_save_settings_and_get_form_data(): void {
        $this->resetAfterTest();

        tool_manager::save_settings((object)['confirmread' => 1, 'confirmtext' => 'Please confirm.'], 42);

        $data = tool_manager::get_form_data(42);
        $this->assertSame(1, $data['confirmread']);
        $this->assertSame('Please confirm.', $data['confirmtext']);

        $this->assertTrue(tool_manager::rule_enabled('leafrtool_confirm', (object)['id' => 42]));
        $this->assertFalse(tool_manager::completion_state('leafrtool_confirm', (object)['id' => 42], 99));

        confirm::record_confirmation(42, 99);
        $this->assertTrue(tool_manager::completion_state('leafrtool_confirm', (object)['id' => 42], 99));
    }

    /**
     * Extending the settings form renders the tool's own elements without failing.
     */
    public function test_extend_settings_form(): void {
        $this->resetAfterTest();

        require_once($GLOBALS['CFG']->libdir . '/formslib.php');
        $mform = new \MoodleQuickForm('leafrtooltest', 'post', '');
        tool_manager::extend_settings_form($mform, null);

        // Leafrtool_confirm does not implement extend_settings_form() (its settings live in the
        // completion rule area instead), so the form stays untouched; the call must not fail.
        $this->assertSame([], $mform->_elements);
    }

    /**
     * render_reader() only contributes HTML when the tool is actually required for the activity.
     */
    public function test_render_reader(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $context = \context_module::instance($cm->id);
        $record = $DB->get_record('leafr', ['id' => $leafr->id], '*', MUST_EXIST);

        $this->assertSame('', tool_manager::render_reader($cm, $context, $record));

        confirm::save_settings((int)$leafr->id, true, 'Please confirm reading this.');
        $html = tool_manager::render_reader($cm, $context, $record);
        $this->assertStringContainsString('leafrtool-confirm', $html);
        $this->assertStringContainsString('Please confirm reading this.', $html);
    }

    /**
     * extend_navigation() lets leafrtool_report add its "Overview" link, but only for someone
     * with the mod/leafr:viewreport capability.
     */
    public function test_extend_navigation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $context = \context_module::instance($cm->id);

        $node = new \navigation_node('Leafr: letzte Seite');
        tool_manager::extend_navigation($node, $cm, $context);
        $this->assertNotNull($node->get('leafrtoolreport'));

        $this->setUser($student);
        $node = new \navigation_node('Leafr: letzte Seite');
        tool_manager::extend_navigation($node, $cm, $context);
        // Note: navigation_node::get() returns false (not null) when no matching child exists.
        $this->assertFalse($node->get('leafrtoolreport'));
    }
}
