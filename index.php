<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Lists all Leafr instances in a course.
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/leafr/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'leafr'), 2);

$leafrs = get_all_instances_in_course('leafr', $course);

if (empty($leafrs)) {
    notice(get_string('nonewmodules', 'leafr'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';
$table->head  = [get_string('name'), get_string('description')];
$table->align = ['left', 'left'];

foreach ($leafrs as $leafr) {
    $link = html_writer::link(
        new moodle_url('/mod/leafr/view.php', ['id' => $leafr->coursemodule]),
        format_string($leafr->name, true)
    );

    if (!$leafr->visible) {
        $link = html_writer::tag('span', $link, ['class' => 'dimmed_text']);
    }

    $intro = format_module_intro('leafr', $leafr, $leafr->coursemodule);
    $table->data[] = [$link, $intro];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
