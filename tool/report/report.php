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
 * Data-sparse reading overview for teachers.
 *
 * @package   leafrtool_report
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use leafrtool_report\local\report;

require(__DIR__ . '/../../../../config.php');

$id = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'leafr');
$leafr = $DB->get_record('leafr', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/leafr:viewreport', $context);

$groupmode = groups_get_activity_groupmode($cm);
$groupid = $groupmode ? groups_get_activity_group($cm, true) : 0;
$rows = report::get_rows($cm, $leafr, $groupid);
$showpercent = $leafr->completiontype == \mod_leafr\local\progress::COMPLETION_PERCENT;
$showconfirm = report::confirm_required($leafr);

if ($download === 'csv') {
    $columns = [
        get_string('fullname'),
        get_string('reportcompleted', 'leafrtool_report'),
        get_string('reportrequiredseen', 'leafrtool_report'),
    ];
    if ($showconfirm) {
        $columns[] = get_string('reportconfirmedat', 'leafrtool_report');
    }
    $yes = get_string('yes');
    $no = get_string('no');
    $csvrows = array_map(function($row) use ($yes, $no, $showconfirm) {
        $out = [
            // Neutralise CSV/formula injection (see report::escape_csv_cell()): a name starting
            // with =, +, -, @ or a tab would otherwise run as a formula when the file is opened in
            // a spreadsheet application.
            report::escape_csv_cell($row['fullname']),
            $row['completed'] ? $yes : $no,
            $row['requiredseen'] === null ? '-' : ($row['requiredseen'] ? $yes : $no),
        ];
        if ($showconfirm) {
            $out[] = $row['confirmedat'] ? userdate($row['confirmedat']) : $no;
        }
        return $out;
    }, $rows);
    \core\dataformat::download_data(
        clean_filename(format_string($leafr->name, true, ['context' => $context])),
        'csv',
        $columns,
        $csvrows
    );
    exit;
}

$PAGE->set_url('/mod/leafr/tool/report/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($leafr->name) . ': ' . get_string('pluginname', 'leafrtool_report'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($leafr);
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'leafrtool_report'));
echo html_writer::tag('p', get_string('reportprivacynotice', 'leafrtool_report'), ['class' => 'alert alert-info']);

if ($groupmode) {
    echo groups_print_activity_menu($cm, $PAGE->url, true);
}

if (!$rows) {
    echo $OUTPUT->notification(get_string('reportnoparticipants', 'leafrtool_report'), 'info');
} else {
    $table = new html_table();
    $head = [get_string('fullname'), get_string('reportcompleted', 'leafrtool_report')];
    $head[] = $showpercent ? get_string('reportrequiredpercent', 'leafrtool_report')
        : get_string('reportrequiredseen', 'leafrtool_report');
    if ($showconfirm) {
        $head[] = get_string('reportconfirmedat', 'leafrtool_report');
    }
    $table->head = $head;

    foreach ($rows as $row) {
        $line = [
            $row['fullname'],
            $row['completed'] ? $OUTPUT->pix_icon('i/checkedcircle', get_string('yes')) : get_string('no'),
        ];
        if ($showpercent) {
            $line[] = $row['requiredpercent'] === null ? '-' : $row['requiredpercent'] . ' %';
        } else {
            $line[] = $row['requiredseen'] === null ? '-' :
                ($row['requiredseen'] ? get_string('yes') : get_string('no'));
        }
        if ($showconfirm) {
            $line[] = $row['confirmedat'] ? userdate($row['confirmedat']) : get_string('no');
        }
        $table->data[] = $line;
    }
    echo html_writer::table($table);

    $csvurl = new moodle_url($PAGE->url, ['download' => 'csv']);
    echo html_writer::link($csvurl, get_string('reportdownloadcsv', 'leafrtool_report'), ['class' => 'btn btn-secondary']);
}

echo $OUTPUT->footer();
