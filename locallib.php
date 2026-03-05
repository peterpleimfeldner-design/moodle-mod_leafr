<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Internal helper functions for mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Pro-Feature-Check: Returns true if Pro is installed and licensed.
 * Uses late static binding - Pro plugin can override this function.
 *
 * @return bool
 */
function leafr_pro_is_available(): bool {
    if (class_exists('\\mod_leafr_pro\\manager')) {
        return \mod_leafr_pro\manager::is_licensed();
    }
    return false;
}

/**
 * Get the current reading position for a user.
 *
 * @param int $cmid Course module ID
 * @param int|null $userid User ID (current user if null)
 * @return int Page number (1-based)
 */
function leafr_get_reading_position(int $cmid, ?int $userid = null): int {
    $key = 'leafr_pos_' . $cmid;
    return (int) get_user_preferences($key, 1, $userid);
}

/**
 * Save the reading position for the current user.
 *
 * @param int $cmid Course module ID
 * @param int $pageno Page number (1-based)
 */
function leafr_save_reading_position(int $cmid, int $pageno): void {
    set_user_preference('leafr_pos_' . $cmid, max(1, (int)$pageno));
}

/**
 * Update the progress tracking (seen pages) for the current user.
 *
 * @param int $cmid Course module ID
 * @param array $seenpages Array of seen page numbers
 */
function leafr_update_progress(int $cmid, array $seenpages): void {
    $key = 'leafr_progress_' . $cmid;
    $current = json_decode(get_user_preferences($key, '[]'), true) ?? [];
    $merged  = array_unique(array_merge($current, $seenpages));
    sort($merged);
    set_user_preference($key, json_encode(array_values($merged)));
}

/**
 * Get progress (seen pages array) for a user.
 *
 * @param int $cmid Course module ID
 * @param int|null $userid User ID (current user if null)
 * @return array Array of seen page numbers
 */
function leafr_get_progress(int $cmid, ?int $userid = null): array {
    $key = 'leafr_progress_' . $cmid;
    return json_decode(get_user_preferences($key, '[]', $userid), true) ?? [];
}

/**
 * Get the file URL for the PDF stored in this Leafr activity.
 *
 * @param stdClass $context The module context
 * @param int $cmid Course module ID
 * @return string|null The file URL or null if no file
 */
function leafr_get_file_url(context $context, int $cmid): ?string {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_leafr', 'content', 0, 'sortorder', false);

    if (empty($files)) {
        return null;
    }

    $file = reset($files);
    return moodle_url::make_pluginfile_url(
        $context->id,
        'mod_leafr',
        'content',
        0,
        $file->get_filepath(),
        $file->get_filename()
    )->out();
}

/**
 * Get file info for the stored PDF.
 *
 * @param context $context The module context
 * @return stored_file|null
 */
function leafr_get_pdf_file(context $context): ?stored_file {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_leafr', 'content', 0, 'sortorder', false);
    if (empty($files)) {
        return null;
    }
    return reset($files);
}

/**
 * Check if a user has seen enough of the PDF to trigger completion.
 *
 * @param stdClass $leafr The leafr record
 * @param int $cmid The course module ID
 * @param int|null $userid User ID
 * @return bool
 */
function leafr_check_completion(stdClass $leafr, int $cmid, ?int $userid = null): bool {
    $seen = leafr_get_progress($cmid, $userid);
    $totalpages = (int)($leafr->totalpages ?? 0);

    if ($totalpages === 0) {
        return false;
    }

    return match((int)$leafr->completiontype) {
        1 => in_array($totalpages, $seen),
        2 => ($totalpages > 0) && ((count($seen) / $totalpages * 100) >= (int)$leafr->completionpercent),
        3 => in_array((int)$leafr->completionpage, $seen),
        default => false,
    };
}
