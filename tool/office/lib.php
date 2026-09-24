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
 * Callbacks of leafrtool_office, invoked by mod_leafr's tool_manager. See
 * mod/leafr/tool/README.md for the contract.
 *
 * @package   leafrtool_office
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use leafrtool_office\local\conversion;
use leafrtool_office\local\settings;

/**
 * Extension => document format understood by \core_files\converter, for every Office format this
 * tool can offer. Only the ones the site's configured converter can actually turn into a PDF are
 * ever offered to the upload field (see leafrtool_office_get_accepted_file_types()).
 *
 * @return array Extension (with leading dot) => format string
 */
function leafrtool_office_supported_formats(): array {
    return [
        '.docx' => 'docx',
        '.doc' => 'doc',
        '.pptx' => 'pptx',
        '.ppt' => 'ppt',
        '.odt' => 'odt',
        '.odp' => 'odp',
    ];
}

/**
 * The Office file extensions the site's configured document converter can actually turn into a
 * PDF right now. Empty if no converter is configured - the upload field then only accepts PDF,
 * exactly as if this tool were not installed (see ROADMAP.md Paket I).
 *
 * @return string[]
 */
function leafrtool_office_get_accepted_file_types(): array {
    $converter = new \core_files\converter();
    $types = [];
    foreach (leafrtool_office_supported_formats() as $extension => $format) {
        if ($converter->can_convert_format_to($format, 'pdf')) {
            $types[] = $extension;
        }
    }
    return $types;
}

/**
 * Notices when the main uploaded file is not a PDF and queues its background conversion, or
 * clears a stale conversion (and its converted PDF) when the file was replaced by an actual PDF.
 *
 * @param int $leafrid Leafr instance id
 * @param context_module $context Module context
 */
function leafrtool_office_handle_content_saved(int $leafrid, context_module $context): void {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_leafr', 'content', 0, 'id', false);
    $file = $files ? reset($files) : null;

    if (!$file || $file->get_mimetype() === 'application/pdf') {
        leafrtool_office_clear_conversion($leafrid, $context);
        return;
    }

    $extension = '.' . strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
    if (!array_key_exists($extension, leafrtool_office_supported_formats())) {
        // Not a format this tool understands (should not normally happen, since the upload field
        // only accepts formats leafrtool_office_get_accepted_file_types() offered in the first
        // place - defensive only, e.g. a direct webservice upload bypassing the form).
        return;
    }

    $existing = conversion::get($leafrid);
    if ($existing && $existing->sourcecontenthash === $file->get_contenthash() && $existing->status !== conversion::STATUS_FAILED) {
        // Same file, already converted or already queued - nothing new to do.
        return;
    }

    conversion::mark_pending($leafrid, $file->get_contenthash());
    $task = new \leafrtool_office\task\convert_document();
    $task->set_custom_data((object)['leafrid' => $leafrid, 'fileid' => (int)$file->get_id(), 'attempt' => 1]);
    \core\task\manager::queue_adhoc_task($task, true);
}

/**
 * Removes a conversion record and its converted PDF file, e.g. once the source file is no longer
 * an Office file.
 *
 * @param int $leafrid Leafr instance id
 * @param context_module $context Module context
 */
function leafrtool_office_clear_conversion(int $leafrid, context_module $context): void {
    conversion::delete_for_instance($leafrid);
    $fs = get_file_storage();
    foreach ($fs->get_area_files($context->id, 'mod_leafr', 'convertedpdf', 0, 'id', false) as $old) {
        $old->delete();
    }
}

/**
 * Reports the status of the converted PDF for the reader, if the uploaded file is one this tool
 * is converting. Returns null when there is nothing to report (e.g. no conversion was ever queued
 * for this activity, because the uploaded file is not an Office format at all).
 *
 * @param int $leafrid Leafr instance id
 * @param context_module $context Module context
 * @return array|null ['status' => 'pending'|'ready'|'failed', 'file' => stored_file|null]
 */
function leafrtool_office_get_converted_file(int $leafrid, context_module $context): ?array {
    $record = conversion::get($leafrid);
    if (!$record) {
        return null;
    }
    if ($record->status === conversion::STATUS_COMPLETE) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_leafr', 'convertedpdf', 0, 'id', false);
        $file = $files ? reset($files) : null;
        if ($file) {
            return ['status' => 'ready', 'file' => $file];
        }
        // The database says "complete" but the file is missing (e.g. deleted by hand); treat as
        // failed rather than showing a broken reader.
        return ['status' => 'failed', 'file' => null];
    }
    if ($record->status === conversion::STATUS_FAILED) {
        return ['status' => 'failed', 'file' => null];
    }
    return ['status' => 'pending', 'file' => null];
}

/**
 * Offers the original Office file for download instead of the converted PDF, if the activity's
 * settings ask for that.
 *
 * @param int $leafrid Leafr instance id
 * @param context_module $context Module context
 * @param stored_file $default The file that would be offered without this tool's involvement
 * @return stored_file|null
 */
function leafrtool_office_get_download_file(int $leafrid, context_module $context, stored_file $default): ?stored_file {
    if (!settings::get($leafrid)->downloadoriginal) {
        return null;
    }
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_leafr', 'content', 0, 'id', false);
    $original = $files ? reset($files) : null;
    // Only worth switching if the original truly differs from what would be offered (i.e. the
    // default is the converted PDF, not already the original file itself).
    return ($original && $original->get_id() !== $default->get_id()) ? $original : null;
}

/**
 * Adds the "offer original for download" setting, shown only once a conversion has ever been
 * queued for this activity (before that, there is no PDF/original distinction to make).
 *
 * @param MoodleQuickForm $mform The settings form
 * @param stdClass|null $instance The current leafr instance, null when adding a new activity
 */
function leafrtool_office_extend_settings_form(MoodleQuickForm $mform, ?stdClass $instance): void {
    if (empty(leafrtool_office_get_accepted_file_types())) {
        // No document converter is configured on this site: the upload field only accepts PDF
        // (see leafrtool_office_get_accepted_file_types()), so this tool has nothing to offer
        // beyond a note for the person editing the activity.
        $mform->addElement('static', 'leafrtool_office_noconverter', '',
            get_string('noconverternotice', 'leafrtool_office'));
        return;
    }
    $mform->addElement('static', 'leafrtool_office_layoutnotice', '', get_string('layoutnotice', 'leafrtool_office'));
    $mform->addElement('advcheckbox', 'officedownloadoriginal', get_string('downloadoriginal', 'leafrtool_office'));
    $mform->addHelpButton('officedownloadoriginal', 'downloadoriginal', 'leafrtool_office');
    $mform->setDefault('officedownloadoriginal', 0);
}

/**
 * Current settings of an activity, for the settings form's default values.
 *
 * @param int $leafrid Leafr instance id, 0 for a new activity
 * @return array Bare (unsuffixed) form element name => value
 */
function leafrtool_office_get_form_data(int $leafrid): array {
    return ['officedownloadoriginal' => settings::get($leafrid)->downloadoriginal];
}

/**
 * Saves the settings submitted through the activity settings form.
 *
 * @param stdClass $data Submitted form data
 * @param int $leafrid Leafr instance id
 */
function leafrtool_office_save_settings(stdClass $data, int $leafrid): void {
    settings::save($leafrid, !empty($data->officedownloadoriginal));
}

/**
 * Deletes all data of this tool for an activity that is being deleted. The converted PDF file
 * itself lives in mod_leafr's own file area and is removed by the core deletion of that area, not
 * here.
 *
 * @param int $leafrid Leafr instance id
 */
function leafrtool_office_delete_instance(int $leafrid): void {
    conversion::delete_for_instance($leafrid);
    settings::delete_for_instance($leafrid);
}
