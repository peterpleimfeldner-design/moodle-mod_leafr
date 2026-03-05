<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Activity settings form for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 */
class mod_leafr_mod_form extends moodleform_mod {

    /**
     * Defines the form fields.
     */
    public function definition(): void {
        global $CFG;

        $mform = $this->_form;

        // ── General section ───────────────────────────────────────────
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Activity name.
        $mform->addElement('text', 'name', get_string('leafrname', 'leafr'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Standard intro / description.
        $this->standard_intro_elements();

        // ── PDF File ──────────────────────────────────────────────────
        $mform->addElement('header', 'pdfheader', get_string('pdffile', 'leafr'));

        $mform->addElement('filemanager', 'pdffile', get_string('pdffile', 'leafr'), null, [
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'accepted_types' => ['.pdf'],
        ]);
        $mform->addHelpButton('pdffile', 'pdffile', 'leafr');

        // ── Display settings ──────────────────────────────────────────
        $mform->addElement('header', 'displaysettings', get_string('displaysettings', 'leafr'));

        // Show Table of Contents.
        $mform->addElement('advcheckbox', 'showtoc', get_string('showtoc', 'leafr'));
        $mform->setDefault('showtoc', 1);

        // Allow PDF download.
        $mform->addElement('advcheckbox', 'downloadallowed', get_string('downloadallowed', 'leafr'));
        $mform->setDefault('downloadallowed', 0);
        $mform->addHelpButton('downloadallowed', 'downloadallowed', 'leafr');

        // Initial page.
        $mform->addElement('text', 'initialpage', get_string('initialpage', 'leafr'), ['size' => '5']);
        $mform->setType('initialpage', PARAM_INT);
        $mform->setDefault('initialpage', 1);

        // Standard course module elements (Completion, Restrict access, etc.).
        $this->standard_coursemodule_elements();

        // Standard submit buttons.
        $this->add_action_buttons();
    }

    /**
     * Prepares the form fields when editing an existing instance.
     *
     * @param array $defaultvalues Form values to be set
     */
    public function data_preprocessing(&$defaultvalues): void {
        parent::data_preprocessing($defaultvalues);

        // Prepare file manager with existing files.
        $draftitemid = file_get_submitted_draft_itemid('pdffile');

        if ($this->current && $this->current->instance) {
            $context = context_module::instance($this->current->coursemodule);
            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                'mod_leafr',
                'content',
                0,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
        } else {
            file_prepare_draft_area($draftitemid, null, 'mod_leafr', 'content', 0);
        }

        $defaultvalues['pdffile'] = $draftitemid;
    }

    /**
     * Custom validation.
     *
     * @param array $data Form data
     * @param array $files Uploaded files
     * @return array Errors
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Only validate completion sub-fields when the rule is active.
        if (!empty($data['completionpageseen'])) {
            if ((int)$data['completiontype'] === 2) {
                $pct = (int)$data['completionpercent'];
                if ($pct < 1 || $pct > 100) {
                    $errors['completionpercent'] = get_string('error_invalidpercent', 'leafr');
                }
            }
            if ((int)$data['completiontype'] === 3) {
                if ((int)$data['completionpage'] < 1) {
                    $errors['completionpage'] = get_string('error_invalidpage', 'leafr');
                }
            }
        }

        return $errors;
    }

    /**
     * Completion rule types this module supports.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;

        // Master rule checkbox — enables leafr's custom completion tracking.
        $mform->addElement('advcheckbox', 'completionpageseen',
            get_string('completionpageseen', 'leafr'),
            get_string('completionpageseen_desc', 'leafr')
        );

        // Completion type — only shown when the rule is enabled.
        $completiontypes = [
            1 => get_string('completion_lastpage', 'leafr'),
            2 => get_string('completion_percent', 'leafr'),
            3 => get_string('completion_specificpage', 'leafr'),
        ];
        $mform->addElement('select', 'completiontype', get_string('completiontype', 'leafr'), $completiontypes);
        $mform->setDefault('completiontype', 1);
        $mform->hideIf('completiontype', 'completionpageseen', 'eq', 0);

        // Percent threshold — shown only when type = percent.
        $mform->addElement('text', 'completionpercent', get_string('completionpercent', 'leafr'), ['size' => '5']);
        $mform->setType('completionpercent', PARAM_INT);
        $mform->setDefault('completionpercent', 100);
        $mform->addRule('completionpercent', null, 'numeric', null, 'client');
        $mform->hideIf('completionpercent', 'completionpageseen', 'eq', 0);
        $mform->hideIf('completionpercent', 'completiontype', 'neq', 2);

        // Specific page — shown only when type = specific page.
        $mform->addElement('text', 'completionpage', get_string('completionpage', 'leafr'), ['size' => '5']);
        $mform->setType('completionpage', PARAM_INT);
        $mform->setDefault('completionpage', 1);
        $mform->addRule('completionpage', null, 'numeric', null, 'client');
        $mform->hideIf('completionpage', 'completionpageseen', 'eq', 0);
        $mform->hideIf('completionpage', 'completiontype', 'neq', 3);

        return ['completionpageseen'];
    }

    /**
     * Check if completion rules are enabled.
     *
     * @param array $data Form data
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completionpageseen']);
    }
}
