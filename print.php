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
 * Printable list of the current user's bookmarks in a Leafr flipbook.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_leafr\local\bookmarks;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'leafr');
$leafr = $DB->get_record('leafr', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/leafr:view', $context);

$PAGE->set_url('/mod/leafr/print.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('bookmark_print', 'leafr') . ': ' . format_string($leafr->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('popup');
$PAGE->set_activity_record($leafr);

// Bookmarks are private and only ever stored for real, logged-in users (see view.php).
$records = (isloggedin() && !isguestuser()) ? bookmarks::get_for_user((int)$leafr->id, (int)$USER->id) : [];

$bookmarklist = [];
foreach (array_values($records) as $bookmark) {
    $bookmarklist[] = [
        'pagelabel' => get_string('pagelabel', 'leafr', $bookmark->pageno),
        'note' => $bookmark->note,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_leafr/print', [
    'activityname' => format_string($leafr->name, true, ['context' => $context, 'escape' => false]),
    'username' => fullname($USER),
    'exportdate' => userdate(time(), get_string('strftimedatetime', 'langconfig')),
    'empty' => empty($records),
    'bookmarks' => $bookmarklist,
]);
echo $OUTPUT->footer();
