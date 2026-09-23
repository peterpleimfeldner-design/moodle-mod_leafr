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

/**
 * Callbacks of leafrtool_report, invoked by mod_leafr's tool_manager. See
 * mod/leafr/tool/README.md for the contract.
 *
 * @package   leafrtool_report
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the "Overview" link to the activity's "More" navigation, for people who may see it.
 *
 * @param navigation_node $node The node for this activity
 * @param cm_info $cm Course module
 * @param context_module $context Module context
 */
function leafrtool_report_extend_navigation(navigation_node $node, cm_info $cm, context_module $context): void {
    if (!has_capability('mod/leafr:viewreport', $context)) {
        return;
    }
    $url = new moodle_url('/mod/leafr/tool/report/report.php', ['id' => $cm->id]);
    $node->add(
        get_string('pluginname', 'leafrtool_report'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'leafrtoolreport',
        new pix_icon('i/report', '')
    );
}
