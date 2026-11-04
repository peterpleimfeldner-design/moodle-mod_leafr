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
 * Upgrade check, step 1: creates realistic Leafr data with the INSTALLED (previous) release.
 *
 * Creates a course "Leafr upgrade check" with two groups, three students and a teacher, and one
 * activity per completion rule, with settings, manual chapters, a read confirmation, reading
 * progress, bookmarks with notes, completion states (one set by hand) and user preferences.
 * Only uses functions that exist since Leafr 1.2.0, so it runs against the previous release.
 * Does nothing if the course already exists.
 *
 * Usage: php seed.php /path/to/moodle/config.php
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require($argv[1] ?? die("Usage: php seed.php /path/to/moodle/config.php\n"));
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

use mod_leafr\local\bookmarks;
use mod_leafr\local\progress;

global $DB;
\core\session\manager::set_user(get_admin());

if ($DB->record_exists('course', ['shortname' => 'LEAFRUPG'])) {
    echo "Course LEAFRUPG exists already, nothing to do.\n";
    exit(0);
}

$gen = new testing_data_generator();
$course = $gen->create_course([
    'fullname' => 'Leafr upgrade check',
    'shortname' => 'LEAFRUPG',
    'enablecompletion' => 1,
    'groupmode' => SEPARATEGROUPS,
]);
$teacher = $gen->create_user(['username' => 'upg_teacher', 'firstname' => 'Tina', 'lastname' => 'Teacher']);
$gen->enrol_user($teacher->id, $course->id, 'editingteacher');
$students = [];
foreach (['Ana', 'Ben', 'Cem'] as $i => $name) {
    $students[$i] = $gen->create_user(['username' => 'upg_student' . ($i + 1), 'firstname' => $name, 'lastname' => 'Student']);
    $gen->enrol_user($students[$i]->id, $course->id, 'student');
}
$groupa = $gen->create_group(['courseid' => $course->id, 'name' => 'Group A']);
$groupb = $gen->create_group(['courseid' => $course->id, 'name' => 'Group B']);
groups_add_member($groupa, $students[0]);
groups_add_member($groupa, $students[1]);
groups_add_member($groupb, $students[2]);

$auto = ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionpageseen' => 1];
$activities = [
    'lastpage' => $auto + ['name' => 'Rule: last page', 'completiontype' => progress::COMPLETION_LASTPAGE],
    'percent' => $auto + ['name' => 'Rule: 50 percent', 'completiontype' => progress::COMPLETION_PERCENT,
        'completionpercent' => 50],
    'page' => $auto + ['name' => 'Rule: page 5', 'completiontype' => progress::COMPLETION_SPECIFICPAGE, 'completionpage' => 5],
    'range' => $auto + ['name' => 'Rule: pages and chapters', 'completiontype' => progress::COMPLETION_SPECIFICRANGE],
    'confirm' => ['name' => 'Read confirmation, settings', 'completion' => COMPLETION_TRACKING_MANUAL, 'showtoc' => 0,
        'downloadallowed' => 1, 'initialpage' => 3],
];
$leafrs = [];
foreach ($activities as $key => $record) {
    $leafrs[$key] = $gen->create_module('leafr', $record + ['course' => $course->id, 'intro' => 'Upgrade check: ' . $key]);
    $DB->set_field('leafr', 'totalpages', 12, ['id' => $leafrs[$key]->id]);
}
$DB->update_record('leafr', (object)[
    'id' => $leafrs['range']->id,
    'completionpages' => progress::encode_pages([1, 2, 3, 8]),
    'manualchapters' => json_encode([['title' => 'Introduction', 'page' => 1], ['title' => 'Übung „Ä“ & <b>', 'page' => 8]]),
    'usemanualchapters' => 1,
]);
\leafrtool_confirm\local\confirm::save_settings((int)$leafrs['confirm']->id, true, 'I have read the "rules" – ÄÖÜ.');

// Reading progress (seen pages and position), different per student and activity.
$pages = [[range(1, 12), 12], [[1, 2, 3, 8, 9], 9], [[1], 1]];
foreach ($leafrs as $leafr) {
    foreach ($students as $i => $student) {
        progress::record((int)$leafr->id, (int)$student->id, $pages[$i][0], $pages[$i][1]);
    }
}
// Bookmarks with notes, including characters that must survive unchanged.
bookmarks::set((int)$leafrs['lastpage']->id, (int)$students[0]->id, 3, 'Remember: "quotes", <tags> & Umlaute äöüß');
bookmarks::set((int)$leafrs['lastpage']->id, (int)$students[0]->id, 7, '');
bookmarks::set((int)$leafrs['percent']->id, (int)$students[1]->id, 2, str_repeat('Long note. ', 40));
bookmarks::set((int)$leafrs['range']->id, (int)$students[2]->id, 1, 'Nota en español: ¿qué?');
// Read confirmation.
\leafrtool_confirm\local\confirm::record_confirmation((int)$leafrs['confirm']->id, (int)$students[0]->id);

// Completion states as the web service would set them, plus one set by hand.
rebuild_course_cache($course->id, true);
$completion = new completion_info($course);
$modinfo = get_fast_modinfo($course);
foreach ($leafrs as $key => $leafr) {
    $cm = $modinfo->get_cm($leafr->cmid);
    foreach ($students as $student) {
        if ($key === 'confirm') {
            continue;
        }
        $completion->update_state($cm, COMPLETION_UNKNOWN, $student->id);
    }
}
$completion->update_state($modinfo->get_cm($leafrs['lastpage']->cmid), COMPLETION_COMPLETE, $students[2]->id, true);
$completion->update_state($modinfo->get_cm($leafrs['confirm']->cmid), COMPLETION_COMPLETE, $students[0]->id);

// User preferences.
set_user_preference('mod_leafr_spreadmode', 'single', $students[0]);
set_user_preference('mod_leafr_spreadmode', 'double', $students[1]);
set_user_preference('mod_leafr_simpleview', 1, $students[1]);

echo "Created course LEAFRUPG (id {$course->id}) with " . count($leafrs) . " Leafr activities.\n";
