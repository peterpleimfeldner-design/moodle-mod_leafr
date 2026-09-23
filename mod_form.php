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
 * Activity settings form for mod_leafr.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_leafr\local\chapters;
use mod_leafr\local\progress;
use mod_leafr\local\tool_manager;

/**
 * Activity settings form for mod_leafr.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_leafr_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('leafrname', 'leafr'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('filemanager', 'pdffile', get_string('pdffile', 'leafr'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.pdf'],
        ]);
        $mform->addHelpButton('pdffile', 'pdffile', 'leafr');

        $mform->addElement('header', 'displaysettings', get_string('displaysettings', 'leafr'));

        $mform->addElement('advcheckbox', 'showtoc', get_string('showtoc', 'leafr'));
        $mform->addHelpButton('showtoc', 'showtoc', 'leafr');
        $mform->setDefault('showtoc', 1);

        $mform->addElement('advcheckbox', 'downloadallowed', get_string('downloadallowed', 'leafr'));
        $mform->addHelpButton('downloadallowed', 'downloadallowed', 'leafr');
        $mform->setDefault('downloadallowed', 0);

        $mform->addElement('text', 'initialpage', get_string('initialpage', 'leafr'), ['size' => '5']);
        $mform->addHelpButton('initialpage', 'initialpage', 'leafr');
        $mform->setType('initialpage', PARAM_INT);
        $mform->setDefault('initialpage', 1);

        $mform->addElement('header', 'chaptersheader', get_string('chaptersheader', 'leafr'));

        $mform->addElement('advcheckbox', 'usemanualchapters', get_string('usemanualchapters', 'leafr'));
        $mform->addHelpButton('usemanualchapters', 'usemanualchapters', 'leafr');
        $mform->setDefault('usemanualchapters', 0);

        $existingchapters = chapters::decode($this->current->manualchapters ?? null);
        $repeatarray = [
            $mform->createElement('text', 'chaptertitle', get_string('chaptertitle', 'leafr'), ['size' => '40']),
            $mform->createElement('text', 'chapterpage', get_string('chapterpage', 'leafr'), ['size' => '5']),
        ];
        $repeatoptions = [
            'chaptertitle' => ['type' => PARAM_TEXT, 'helpbutton' => ['chaptertitle', 'leafr']],
            'chapterpage' => ['type' => PARAM_INT],
        ];
        $this->repeat_elements(
            $repeatarray,
            max(1, count($existingchapters)),
            $repeatoptions,
            'chapter_repeats',
            'chapter_add_fields',
            1,
            get_string('addchapters', 'leafr'),
            true,
            'chapter_delete'
        );

        tool_manager::extend_settings_form($mform, $this->current);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Returns the suffix of the completion elements on the default completion page.
     *
     * @return string
     */
    protected function leafr_completion_suffix(): string {
        return $this->get_suffix();
    }

    /**
     * Prepares the form data when editing an existing instance.
     *
     * @param array $defaultvalues Form values
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        $draftitemid = file_get_submitted_draft_itemid('pdffile');
        $contextid = !empty($this->current->coursemodule) ? context_module::instance($this->current->coursemodule)->id : null;
        file_prepare_draft_area($draftitemid, $contextid, 'mod_leafr', 'content', 0, ['subdirs' => 0, 'maxfiles' => 1]);
        $defaultvalues['pdffile'] = $draftitemid;

        $existingchapters = chapters::decode($defaultvalues['manualchapters'] ?? null);
        $defaultvalues['chaptertitle'] = array_column($existingchapters, 'title');
        $defaultvalues['chapterpage'] = array_column($existingchapters, 'page');

        $suffix = $this->leafr_completion_suffix();
        $type = (int)($defaultvalues['completiontype' . $suffix] ?? 0);
        $defaultvalues['completionpageseen' . $suffix] = $type > 0 ? 1 : 0;
        if ($type < 1) {
            $defaultvalues['completiontype' . $suffix] = progress::COMPLETION_LASTPAGE;
        }
    }

    /**
     * Validates the form data.
     *
     * @param array $data Submitted data
     * @param array $files Uploaded files
     * @return array Errors
     */
    public function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        if (array_key_exists('pdffile', $data)) {
            $usercontext = context_user::instance($USER->id);
            $pdffiles = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', (int)$data['pdffile'], 'id', false);
            if (!$pdffiles) {
                $errors['pdffile'] = get_string('required');
            }
        }

        if (isset($data['initialpage']) && (int)$data['initialpage'] < 1) {
            $errors['initialpage'] = get_string('error_invalidpage', 'leafr');
        }

        $suffix = $this->leafr_completion_suffix();
        if (!empty($data['completionpageseen' . $suffix])) {
            $type = (int)($data['completiontype' . $suffix] ?? 0);
            if ($type === progress::COMPLETION_PERCENT) {
                $percent = (int)($data['completionpercent' . $suffix] ?? 0);
                if ($percent < 1 || $percent > 100) {
                    $errors['completionpercent' . $suffix] = get_string('error_invalidpercent', 'leafr');
                }
            } else if ($type === progress::COMPLETION_SPECIFICPAGE) {
                if ((int)($data['completionpage' . $suffix] ?? 0) < 1) {
                    $errors['completionpage' . $suffix] = get_string('error_invalidpage', 'leafr');
                }
            } else if ($type === progress::COMPLETION_SPECIFICRANGE) {
                $rangestring = trim((string)($data['completionpages' . $suffix] ?? ''));
                $haschapters = !empty($data['completionchapters' . $suffix]);
                if ($rangestring === '' && !$haschapters) {
                    $errors['completionpages' . $suffix] = get_string('error_invalidpagerange', 'leafr');
                } else if ($rangestring !== '') {
                    if (!preg_match('/^[0-9,\-\s]+$/', $rangestring) || !progress::decode_pages($rangestring)) {
                        $errors['completionpages' . $suffix] = get_string('error_invalidpagerange', 'leafr');
                    }
                }
            }
        }

        if (array_key_exists('chaptertitle', $data)) {
            foreach ((array)$data['chaptertitle'] as $index => $title) {
                $page = (int)($data['chapterpage'][$index] ?? 0);
                if (trim((string)$title) !== '' && $page < 1) {
                    $errors["chapterpage[$index]"] = get_string('error_invalidpage', 'leafr');
                }
            }
        }
        return $errors;
    }

    /**
     * Adds the custom completion rule elements.
     *
     * @return string[] Names of the added elements
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->leafr_completion_suffix();
        $enabledel = 'completionpageseen' . $suffix;
        $typeel = 'completiontype' . $suffix;
        $percentel = 'completionpercent' . $suffix;
        $pageel = 'completionpage' . $suffix;
        $pagesel = 'completionpages' . $suffix;
        $chaptersel = 'completionchapters' . $suffix;

        $mform->addElement(
            'advcheckbox',
            $enabledel,
            get_string('completionpageseen', 'leafr'),
            get_string('completionpageseen_desc', 'leafr')
        );

        $mform->addElement('select', $typeel, get_string('completiontype', 'leafr'), [
            progress::COMPLETION_LASTPAGE => get_string('completion_lastpage', 'leafr'),
            progress::COMPLETION_PERCENT => get_string('completion_percent', 'leafr'),
            progress::COMPLETION_SPECIFICPAGE => get_string('completion_specificpage', 'leafr'),
            progress::COMPLETION_SPECIFICRANGE => get_string('completion_specificrange', 'leafr'),
        ]);
        $mform->setDefault($typeel, progress::COMPLETION_LASTPAGE);
        $mform->hideIf($typeel, $enabledel, 'notchecked');

        $mform->addElement('text', $percentel, get_string('completionpercent', 'leafr'), ['size' => '5']);
        $mform->setType($percentel, PARAM_INT);
        $mform->setDefault($percentel, 100);
        $mform->hideIf($percentel, $enabledel, 'notchecked');
        $mform->hideIf($percentel, $typeel, 'neq', progress::COMPLETION_PERCENT);

        $mform->addElement('text', $pageel, get_string('completionpage', 'leafr'), ['size' => '5']);
        $mform->setType($pageel, PARAM_INT);
        $mform->setDefault($pageel, 1);
        $mform->hideIf($pageel, $enabledel, 'notchecked');
        $mform->hideIf($pageel, $typeel, 'neq', progress::COMPLETION_SPECIFICPAGE);

        $mform->addElement('text', $pagesel, get_string('completionpages', 'leafr'), ['size' => '30']);
        $mform->setType($pagesel, PARAM_RAW_TRIMMED);
        $mform->addHelpButton($pagesel, 'completionpages', 'leafr');
        $mform->hideIf($pagesel, $enabledel, 'notchecked');
        $mform->hideIf($pagesel, $typeel, 'neq', progress::COMPLETION_SPECIFICRANGE);

        $elements = [$enabledel, $typeel, $percentel, $pageel, $pagesel];

        $existingchapters = chapters::decode($this->current->manualchapters ?? null);
        if ($existingchapters) {
            $options = [];
            foreach ($existingchapters as $index => $chapter) {
                $options[$index] = $chapter['title'] . ' (' . get_string('pagelabel', 'leafr', $chapter['page']) . ')';
            }
            $mform->addElement(
                'select',
                $chaptersel,
                get_string('completionchapters', 'leafr'),
                $options,
                ['multiple' => 'multiple', 'size' => min(6, count($options))]
            );
            $mform->addHelpButton($chaptersel, 'completionchapters', 'leafr');
            $mform->hideIf($chaptersel, $enabledel, 'notchecked');
            $mform->hideIf($chaptersel, $typeel, 'neq', progress::COMPLETION_SPECIFICRANGE);
            $elements[] = $chaptersel;
        }

        return $elements;
    }

    /**
     * Whether the custom completion rule is enabled in the submitted data.
     *
     * @param array $data Submitted data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionpageseen' . $this->leafr_completion_suffix()]);
    }

    /**
     * Stores the completion type 0 when the rule is switched off.
     *
     * @param stdClass $data Submitted data
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            $suffix = $this->leafr_completion_suffix();
            $completion = $data->{'completion' . $suffix} ?? COMPLETION_TRACKING_NONE;
            if (empty($data->{'completionpageseen' . $suffix}) || $completion != COMPLETION_TRACKING_AUTOMATIC) {
                $data->{'completiontype' . $suffix} = progress::COMPLETION_NONE;
            }
        }
    }
}
