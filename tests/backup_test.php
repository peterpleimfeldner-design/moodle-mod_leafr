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

namespace mod_leafr;

use leafrtool_confirm\local\confirm;
use mod_leafr\local\bookmarks;
use mod_leafr\local\progress;

/**
 * Tests for backup and restore with user data.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_test extends \advanced_testcase {
    /**
     * Loads the backup and restore libraries and lib.php.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/mod/leafr/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * A course backup with user data, restored with user data into a new course, keeps the
     * settings, the PDF, the reading progress, the bookmarks and the read confirmations of each user.
     *
     * @covers \backup_leafr_activity_task
     * @covers \backup_leafr_activity_structure_step
     * @covers \restore_leafr_activity_task
     * @covers \restore_leafr_activity_structure_step
     * @covers \backup_leafrtool_confirm_subplugin
     * @covers \restore_leafrtool_confirm_subplugin
     */
    public function test_backup_and_restore_with_user_data(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student1 = $generator->create_and_enrol($course, 'student');
        $student2 = $generator->create_and_enrol($course, 'student');
        $leafr = $generator->create_module('leafr', [
            'course' => $course->id,
            'name' => 'Handbook',
            'showtoc' => 0,
            'downloadallowed' => 1,
            'initialpage' => 3,
        ]);
        $DB->set_field('leafr', 'totalpages', 12, ['id' => $leafr->id]);
        $DB->set_field('leafr', 'totalpagesconfirmed', 1, ['id' => $leafr->id]);
        $leafrid = (int)$leafr->id;

        progress::record($leafrid, (int)$student1->id, [1, 2, 3, 4, 7], 7);
        progress::record($leafrid, (int)$student2->id, [1, 2], 2);
        $DB->set_field('leafr_progress', 'reportedtotal', 12, ['leafrid' => $leafrid, 'userid' => $student1->id]);
        bookmarks::set($leafrid, (int)$student1->id, 4, 'Student one note');
        bookmarks::set($leafrid, (int)$student1->id, 7, '');
        bookmarks::set($leafrid, (int)$student2->id, 2, 'Student two note');
        confirm::save_settings($leafrid, true, 'I have read the handbook.');
        $DB->insert_record('leafrtool_confirm_log', (object)[
            'leafrid' => $leafrid,
            'userid' => $student1->id,
            'timeconfirmed' => 1700000000,
        ]);

        // Back up the whole course including user data.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $backupid = 'leafr_backup_test';
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $backupid);
        $bc->destroy();

        // Restore it into a new course, including user data.
        $newcourseid = \restore_dbops::create_new_course('Restored course', 'RESTORED', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Activity settings and PDF.
        $copy = $DB->get_record('leafr', ['course' => $newcourseid], '*', MUST_EXIST);
        $newid = (int)$copy->id;
        $this->assertNotEquals($leafrid, $newid);
        $this->assertEquals('Handbook', $copy->name);
        $this->assertEquals(0, $copy->showtoc);
        $this->assertEquals(1, $copy->downloadallowed);
        $this->assertEquals(3, $copy->initialpage);
        $this->assertEquals(12, $copy->totalpages);
        $this->assertEquals(1, $copy->totalpagesconfirmed);
        $newcm = get_coursemodule_from_instance('leafr', $newid, $newcourseid, false, MUST_EXIST);
        $pdf = leafr_get_pdf_file(\context_module::instance($newcm->id));
        $this->assertNotNull($pdf);
        $this->assertEquals('sample.pdf', $pdf->get_filename());

        // Reading progress, per user.
        $this->assertEquals(2, $DB->count_records('leafr_progress', ['leafrid' => $newid]));
        $this->assertEquals([1, 2, 3, 4, 7], progress::get_seen_pages($newid, (int)$student1->id));
        $this->assertEquals(7, progress::get_last_page($newid, (int)$student1->id));
        $this->assertEquals([1, 2], progress::get_seen_pages($newid, (int)$student2->id));
        $this->assertEquals(2, progress::get_last_page($newid, (int)$student2->id));
        $reported = $DB->get_field('leafr_progress', 'reportedtotal', ['leafrid' => $newid, 'userid' => $student1->id]);
        $this->assertEquals(12, $reported);
        $this->assertEquals(0, $DB->get_field('leafr_progress', 'reportedtotal', ['leafrid' => $newid, 'userid' => $student2->id]));

        // Bookmarks with notes, per user.
        $this->assertEquals(3, $DB->count_records('leafr_bookmarks', ['leafrid' => $newid]));
        $marks1 = $DB->get_records_menu(
            'leafr_bookmarks',
            ['leafrid' => $newid, 'userid' => $student1->id],
            'pageno ASC',
            'pageno, note'
        );
        $this->assertEquals([4 => 'Student one note', 7 => ''], $marks1);
        $marks2 = $DB->get_records_menu(
            'leafr_bookmarks',
            ['leafrid' => $newid, 'userid' => $student2->id],
            'pageno ASC',
            'pageno, note'
        );
        $this->assertEquals([2 => 'Student two note'], $marks2);

        // Read confirmation tool: settings and log.
        $settings = confirm::get_settings($newid);
        $this->assertTrue($settings->requireconfirm);
        $this->assertSame('I have read the handbook.', $settings->confirmtext);
        $this->assertEquals(1, $DB->count_records('leafrtool_confirm_log', ['leafrid' => $newid]));
        $this->assertSame(1700000000, confirm::get_confirmed_time($newid, (int)$student1->id));
        $this->assertFalse(confirm::is_confirmed($newid, (int)$student2->id));

        // The original activity keeps its own data.
        $this->assertEquals(2, $DB->count_records('leafr_progress', ['leafrid' => $leafrid]));
        $this->assertEquals(3, $DB->count_records('leafr_bookmarks', ['leafrid' => $leafrid]));
        $this->assertEquals(1, $DB->count_records('leafrtool_confirm_log', ['leafrid' => $leafrid]));
    }
}
