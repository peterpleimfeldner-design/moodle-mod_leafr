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

use core_component;
use MoodleQuickForm;
use stdClass;

/**
 * Discovers installed leafrtool subplugins and dispatches the core extension points to them.
 *
 * See tool/README.md for the callbacks a leafrtool subplugin can implement.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_manager {
    /**
     * Frankenstyle names of all installed and enabled leafrtool subplugins.
     *
     * @return string[]
     */
    public static function get_enabled_tools(): array {
        $tools = [];
        foreach (array_keys(core_component::get_plugin_list('leafrtool')) as $name) {
            if (!get_config('leafrtool_' . $name, 'disabled')) {
                $tools[] = 'leafrtool_' . $name;
            }
        }
        return $tools;
    }

    /**
     * Lets every enabled tool add its own elements to the activity settings form.
     *
     * @param MoodleQuickForm $mform The form to extend
     * @param stdClass|null $instance The current leafr instance, null when adding a new activity
     */
    public static function extend_settings_form(MoodleQuickForm $mform, ?stdClass $instance): void {
        foreach (self::get_enabled_tools() as $component) {
            component_callback($component, 'extend_settings_form', [$mform, $instance]);
        }
    }

    /**
     * Collects the additional automatic completion rules offered by enabled tools.
     *
     * @return array Rule name => ['component' => string, 'langkey' => string]
     */
    public static function get_completion_rules(): array {
        $rules = [];
        foreach (self::get_enabled_tools() as $component) {
            $tool = component_callback($component, 'completion_rules', [], []);
            if (is_array($tool)) {
                foreach ($tool as $rulename => $langkey) {
                    $rules[$rulename] = ['component' => $component, 'langkey' => $langkey];
                }
            }
        }
        return $rules;
    }

    /**
     * Whether a tool's completion rule is switched on for an activity.
     *
     * @param string $component Frankenstyle name of the owning leafrtool subplugin
     * @param stdClass $leafr Leafr instance record
     * @return bool
     */
    public static function rule_enabled(string $component, stdClass $leafr): bool {
        return (bool)component_callback($component, 'completion_rule_enabled', [$leafr], false);
    }

    /**
     * Whether the given leafr instance fulfils a tool's completion rule for a user.
     *
     * @param string $component Frankenstyle name of the owning leafrtool subplugin
     * @param stdClass $leafr Leafr instance record
     * @param int $userid User id
     * @return bool
     */
    public static function completion_state(string $component, stdClass $leafr, int $userid): bool {
        return (bool)component_callback($component, 'completion_state', [$leafr, $userid], false);
    }

    /**
     * Lets every enabled tool add elements next to its own completion rule checkbox (e.g. a text
     * field), if it implements one.
     *
     * @param MoodleQuickForm $mform The settings form
     * @param string $rulename Bare rule name, as returned by {@see get_completion_rules()}
     * @param string $formname Name of the rule's checkbox element (already includes any suffix)
     * @param string $suffix Suffix used on the "activity completion defaults" admin form
     */
    public static function extend_completion_rule(
        MoodleQuickForm $mform,
        string $rulename,
        string $formname,
        string $suffix
    ): void {
        $rules = self::get_completion_rules();
        if (isset($rules[$rulename])) {
            component_callback($rules[$rulename]['component'], 'completion_rule_elements', [$mform, $formname, $suffix]);
        }
    }

    /**
     * Collects the default form values every enabled tool wants to contribute (e.g. its own
     * settings when editing an existing activity).
     *
     * @param int $leafrid Leafr instance id, 0 for a new activity
     * @return array Bare (unsuffixed) form element name => value
     */
    public static function get_form_data(int $leafrid): array {
        $data = [];
        foreach (self::get_enabled_tools() as $component) {
            $tool = component_callback($component, 'get_form_data', [$leafrid], []);
            if (is_array($tool)) {
                $data = array_merge($data, $tool);
            }
        }
        return $data;
    }

    /**
     * Lets every enabled tool save the settings it contributed to the activity settings form.
     *
     * @param stdClass $data Submitted form data
     * @param int $leafrid Leafr instance id
     */
    public static function save_settings(stdClass $data, int $leafrid): void {
        foreach (self::get_enabled_tools() as $component) {
            component_callback($component, 'save_settings', [$data, $leafrid]);
        }
    }

    /**
     * Lets every enabled tool delete its data for an activity that is being deleted. Tools that
     * are currently disabled but were enabled before are intentionally not asked: `leafrtool` uses
     * {@see core_component::get_plugin_list()}, which lists every installed tool regardless of
     * whether it is enabled, so their data is cleaned up too.
     *
     * @param int $leafrid Leafr instance id
     */
    public static function delete_instance(int $leafrid): void {
        foreach (array_keys(core_component::get_plugin_list('leafrtool')) as $name) {
            component_callback('leafrtool_' . $name, 'delete_instance', [$leafrid]);
        }
    }

    /**
     * Adds the reset options of every installed tool to the course reset form.
     *
     * @param MoodleQuickForm $mform Course reset form
     */
    public static function extend_reset_form(MoodleQuickForm $mform): void {
        foreach (array_keys(core_component::get_plugin_list('leafrtool')) as $name) {
            component_callback('leafrtool_' . $name, 'reset_course_form_definition', [$mform]);
        }
    }

    /**
     * Collects the reset form default values of every installed tool.
     *
     * @return array
     */
    public static function get_reset_form_defaults(): array {
        $defaults = [];
        foreach (array_keys(core_component::get_plugin_list('leafrtool')) as $name) {
            $tool = component_callback('leafrtool_' . $name, 'reset_course_form_defaults', [], []);
            if (is_array($tool)) {
                $defaults = array_merge($defaults, $tool);
            }
        }
        return $defaults;
    }

    /**
     * Lets every installed tool remove its user data when a course is reset.
     *
     * @param stdClass $data Data submitted by the reset form
     * @return array Status messages, in the format expected by a module's reset_userdata()
     */
    public static function reset_userdata(stdClass $data): array {
        $status = [];
        foreach (array_keys(core_component::get_plugin_list('leafrtool')) as $name) {
            $tool = component_callback('leafrtool_' . $name, 'reset_userdata', [$data], []);
            if (is_array($tool)) {
                $status = array_merge($status, $tool);
            }
        }
        return $status;
    }

    /**
     * Collects the HTML every enabled tool wants to show below the reader (e.g. a read
     * confirmation card), and lets each queue its own AMD module while doing so.
     *
     * @param \cm_info $cm Course module
     * @param \context_module $context Module context
     * @param stdClass $leafr Leafr instance record
     * @return string Rendered HTML, empty if no enabled tool contributes anything
     */
    public static function render_reader(\cm_info $cm, \context_module $context, stdClass $leafr): string {
        $html = '';
        foreach (self::get_enabled_tools() as $component) {
            $piece = component_callback($component, 'render_reader', [$cm, $context, $leafr]);
            if ($piece) {
                $html .= $piece;
            }
        }
        return $html;
    }

    /**
     * Lets every enabled tool add its own links to the activity's "More" navigation (e.g. a
     * report page).
     *
     * @param \navigation_node $node The node for this activity
     * @param \cm_info $cm Course module
     * @param \context_module $context Module context
     */
    public static function extend_navigation(\navigation_node $node, \cm_info $cm, \context_module $context): void {
        foreach (self::get_enabled_tools() as $component) {
            component_callback($component, 'extend_navigation', [$node, $cm, $context]);
        }
    }
}
