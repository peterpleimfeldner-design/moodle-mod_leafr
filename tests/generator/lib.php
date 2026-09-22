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
 * Data generator for mod_leafr.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_leafr_generator extends testing_module_generator {
    /**
     * Creates a Leafr activity. Unless a draft area is given, the sample PDF from tests/fixtures is added.
     *
     * @param array|stdClass|null $record Instance data, requires 'course'
     * @param array|null $options Course module options
     * @return stdClass Instance record with the additional field cmid
     */
    public function create_instance($record = null, ?array $options = null) {
        global $CFG, $USER;

        $record = (object)(array)$record;
        $defaults = [
            'showtoc' => 1,
            'downloadallowed' => 0,
            'initialpage' => 1,
            'completiontype' => 0,
            'completionpercent' => 100,
            'completionpage' => 1,
        ];
        foreach ($defaults as $name => $value) {
            if (!isset($record->$name)) {
                $record->$name = $value;
            }
        }

        if (!isset($record->pdffile)) {
            if (empty($USER->id) || isguestuser()) {
                throw new coding_exception('The leafr generator requires a current user to add the PDF file.');
            }
            $record->pdffile = file_get_unused_draft_itemid();
            get_file_storage()->create_file_from_pathname([
                'contextid' => context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $record->pdffile,
                'filepath' => '/',
                'filename' => 'sample.pdf',
            ], $CFG->dirroot . '/mod/leafr/tests/fixtures/sample.pdf');
        }

        return parent::create_instance($record, $options);
    }
}
