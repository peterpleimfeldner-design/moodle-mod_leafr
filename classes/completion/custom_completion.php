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

namespace mod_leafr\completion;

use core_completion\activity_custom_completion;
use mod_leafr\local\progress;
use mod_leafr\local\tool_manager;

/**
 * Custom completion rules for mod_leafr: the core page-based rule "completionpageseen", plus
 * whatever rules the installed leafrtool subplugins contribute (see {@see tool_manager}).
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Returns the completion state of a rule.
     *
     * @param string $rule Rule name
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        if ($rule === 'completionpageseen') {
            $leafr = $DB->get_record(
                'leafr',
                ['id' => $this->cm->instance],
                'id, completiontype, completionpercent, completionpage, completionpages, totalpages',
                MUST_EXIST
            );
            return progress::is_complete($leafr, $this->userid) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        $rules = tool_manager::get_completion_rules();
        if (isset($rules[$rule])) {
            $leafr = $DB->get_record('leafr', ['id' => $this->cm->instance], '*', MUST_EXIST);
            $complete = tool_manager::completion_state($rules[$rule]['component'], $leafr, $this->userid);
            return $complete ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }
        return COMPLETION_INCOMPLETE;
    }

    /**
     * Returns the names of the custom rules of this module: the core rule plus every rule
     * contributed by an installed leafrtool subplugin.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return array_merge(['completionpageseen'], array_keys(tool_manager::get_completion_rules()));
    }

    /**
     * Returns the descriptions of the active custom rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;

        $leafr = $DB->get_record(
            'leafr',
            ['id' => $this->cm->instance],
            'id, completiontype, completionpercent, completionpage, completionpages, totalpages'
        );
        $description = '';
        if ($leafr) {
            switch ((int)$leafr->completiontype) {
                case progress::COMPLETION_LASTPAGE:
                    $description = get_string('completiondetail:lastpage', 'leafr');
                    break;
                case progress::COMPLETION_PERCENT:
                    $description = get_string('completiondetail:percent', 'leafr', (int)$leafr->completionpercent);
                    break;
                case progress::COMPLETION_SPECIFICPAGE:
                    $description = get_string('completiondetail:page', 'leafr', (int)$leafr->completionpage);
                    break;
                case progress::COMPLETION_SPECIFICRANGE:
                    $range = progress::encode_pages(progress::required_pages($leafr));
                    $description = get_string('completiondetail:range', 'leafr', $range !== '' ? $range : '-');
                    break;
            }
        }

        $descriptions = ['completionpageseen' => $description];
        foreach (tool_manager::get_completion_rules() as $rulename => $info) {
            $descriptions[$rulename] = get_string('completiondetail:' . $rulename, $info['component']);
        }
        return $descriptions;
    }

    /**
     * Returns the order in which the rules are displayed.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return array_merge(['completionview', 'completionpageseen'], array_keys(tool_manager::get_completion_rules()));
    }
}
