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
 * Callbacks of leafrtool_confirm, invoked by mod_leafr's tool_manager. See
 * mod/leafr/tool/README.md for the contract.
 *
 * @package   leafrtool_confirm
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use leafrtool_confirm\local\confirm;

/**
 * The completion rule this tool contributes.
 *
 * @return array Form/rule name => language string key
 */
function leafrtool_confirm_completion_rules(): array {
    return ['confirmread' => 'completionconfirmread'];
}

/**
 * Whether the rule is switched on for an activity.
 *
 * @param stdClass $leafr Leafr instance record
 * @return bool
 */
function leafrtool_confirm_completion_rule_enabled(stdClass $leafr): bool {
    return confirm::get_settings((int)$leafr->id)->requireconfirm;
}

/**
 * Whether a user has fulfilled the rule.
 *
 * @param stdClass $leafr Leafr instance record
 * @param int $userid User id
 * @return bool
 */
function leafrtool_confirm_completion_state(stdClass $leafr, int $userid): bool {
    return confirm::is_confirmed((int)$leafr->id, $userid);
}

/**
 * Adds the confirmation text field next to the rule's checkbox in the activity settings form.
 *
 * @param MoodleQuickForm $mform The settings form
 * @param string $formname Name of the rule's own checkbox element (already includes any suffix)
 * @param string $suffix Suffix used on the "activity completion defaults" admin form, empty otherwise
 */
function leafrtool_confirm_completion_rule_elements(MoodleQuickForm $mform, string $formname, string $suffix): void {
    $textname = 'confirmtext' . $suffix;
    $mform->addElement(
        'textarea',
        $textname,
        get_string('confirmtext', 'leafrtool_confirm'),
        ['rows' => 2, 'cols' => 60]
    );
    $mform->setType($textname, PARAM_TEXT);
    $mform->addHelpButton($textname, 'confirmtext', 'leafrtool_confirm');
    $mform->setDefault($textname, get_string('confirmtext_default', 'leafrtool_confirm'));
    $mform->hideIf($textname, $formname, 'notchecked');
}

/**
 * Current settings of an activity, for the settings form's default values.
 *
 * @param int $leafrid Leafr instance id, 0 for a new activity
 * @return array Bare (unsuffixed) form element name => value
 */
function leafrtool_confirm_get_form_data(int $leafrid): array {
    $settings = confirm::get_settings($leafrid);
    return [
        'confirmread' => $settings->requireconfirm ? 1 : 0,
        'confirmtext' => $settings->confirmtext,
    ];
}

/**
 * Saves the settings submitted through the activity settings form.
 *
 * @param stdClass $data Submitted form data
 * @param int $leafrid Leafr instance id
 */
function leafrtool_confirm_save_settings(stdClass $data, int $leafrid): void {
    confirm::save_settings(
        $leafrid,
        !empty($data->confirmread),
        (string)($data->confirmtext ?? '')
    );
}

/**
 * Deletes all data of this tool for an activity that is being deleted.
 *
 * @param int $leafrid Leafr instance id
 */
function leafrtool_confirm_delete_instance(int $leafrid): void {
    confirm::delete_for_instance($leafrid);
}

/**
 * Adds the reset option for this tool to the course reset form.
 *
 * @param MoodleQuickForm $mform Course reset form
 */
function leafrtool_confirm_reset_course_form_definition(MoodleQuickForm $mform): void {
    $mform->addElement('advcheckbox', 'reset_leafrtool_confirm', get_string('resetconfirmations', 'leafrtool_confirm'));
}

/**
 * Default values for the course reset form.
 *
 * @return array
 */
function leafrtool_confirm_reset_course_form_defaults(): array {
    return ['reset_leafrtool_confirm' => 1];
}

/**
 * Removes confirmations when a course is reset.
 *
 * @param stdClass $data Data submitted by the reset form, including courseid
 * @return array Status messages, in the format expected by a module's reset_userdata()
 */
function leafrtool_confirm_reset_userdata(stdClass $data): array {
    global $DB;

    if (empty($data->reset_leafrtool_confirm)) {
        return [];
    }
    $leafrids = $DB->get_fieldset_select('leafr', 'id', 'course = :courseid', ['courseid' => $data->courseid]);
    foreach ($leafrids as $leafrid) {
        confirm::delete_confirmations_for_instance((int)$leafrid);
    }
    return [[
        'component' => get_string('pluginname', 'leafrtool_confirm'),
        'item' => get_string('resetconfirmations', 'leafrtool_confirm'),
        'error' => false,
    ]];
}

/**
 * Renders the confirmation card shown once the reading requirement is met, and wires up its
 * behaviour. Returns null (nothing to add) when the tool is not required for this activity.
 *
 * @param cm_info $cm Course module
 * @param context_module $context Module context
 * @param stdClass $leafr Leafr instance record
 * @return string|null Rendered HTML, appended after the reader
 */
function leafrtool_confirm_render_reader(cm_info $cm, context_module $context, stdClass $leafr): ?string {
    global $OUTPUT, $PAGE, $USER;

    $settings = confirm::get_settings((int)$leafr->id);
    if (!$settings->requireconfirm || !isloggedin() || isguestuser()) {
        return null;
    }

    $confirmed = confirm::is_confirmed((int)$leafr->id, (int)$USER->id);
    $PAGE->requires->js_call_amd('leafrtool_confirm/confirm', 'init', [[
        'cmid' => (int)$cm->id,
        'confirmed' => $confirmed,
    ]]);

    $donetext = '';
    if ($confirmed) {
        $confirmedtime = confirm::get_confirmed_time((int)$leafr->id, (int)$USER->id);
        $donetext = get_string('confirm_confirmedat', 'leafrtool_confirm', userdate($confirmedtime));
    }

    return $OUTPUT->render_from_template('leafrtool_confirm/confirm', [
        'cmid' => (int)$cm->id,
        'text' => format_string($settings->confirmtext, true, ['context' => $context, 'escape' => false]),
        'confirmed' => $confirmed,
        'donetext' => $donetext,
    ]);
}
