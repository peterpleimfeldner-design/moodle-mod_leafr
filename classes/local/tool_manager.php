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
     * @return array Form element name => language string key, keyed by owning component
     */
    public static function get_completion_rules(): array {
        $rules = [];
        foreach (self::get_enabled_tools() as $component) {
            $tool = component_callback($component, 'completion_rules', [], []);
            if (is_array($tool)) {
                $rules[$component] = $tool;
            }
        }
        return $rules;
    }

    /**
     * Whether the given leafr instance fulfils a tool's completion rule.
     *
     * @param string $component Frankenstyle name of the owning leafrtool subplugin
     * @param stdClass $leafr Leafr instance record
     * @param int $userid User id
     * @return bool
     */
    public static function completion_state(string $component, stdClass $leafr, int $userid): bool {
        return (bool)component_callback($component, 'completion_state', [$leafr, $userid], false);
    }
}
