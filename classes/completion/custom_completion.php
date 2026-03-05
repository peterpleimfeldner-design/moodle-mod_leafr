<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Custom completion rules for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_leafr\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for mod_leafr.
 * Handles the custom "completionpageseen" rule.
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule
     * @return int The completion state (COMPLETION_COMPLETE or COMPLETION_INCOMPLETE)
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $leafr = $DB->get_record('leafr', ['id' => $this->cm->instance], '*', MUST_EXIST);

        if ($rule === 'completionpageseen') {
            // Load the seen pages progress from user preferences.
            $prefkey   = 'leafr_progress_' . $this->cm->id;
            $seenjson  = get_user_preferences($prefkey, '[]', $this->userid);
            $seen      = json_decode($seenjson, true) ?? [];
            $totalpages = (int)($leafr->totalpages ?? 0);

            // If total pages not yet stored (old record), treat as incomplete.
            if ($totalpages === 0) {
                return COMPLETION_INCOMPLETE;
            }

            $complete = match((int)$leafr->completiontype) {
                1 => in_array($totalpages, $seen),
                2 => count($seen) > 0 && (count($seen) / $totalpages * 100) >= (int)$leafr->completionpercent,
                3 => in_array((int)$leafr->completionpage, $seen),
                default => false,
            };

            return $complete ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Defines the custom completion rules this module supports.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionpageseen'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;

        $leafr = $DB->get_record('leafr', ['id' => $this->cm->instance]);

        $desc = '';
        if ($leafr) {
            switch ((int)$leafr->completiontype) {
                case 1:
                    $desc = get_string('completion_lastpage', 'leafr');
                    break;
                case 2:
                    $desc = get_string('completion_percent_desc', 'leafr', $leafr->completionpercent);
                    break;
                case 3:
                    $desc = get_string('completion_specificpage_desc', 'leafr', $leafr->completionpage);
                    break;
                default:
                    $desc = get_string('completion_none', 'leafr');
            }
        }

        return ['completionpageseen' => $desc];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionpageseen'];
    }
}
