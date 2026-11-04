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

namespace leafrtool_confirm;

use leafrtool_confirm\local\confirm;

/**
 * Tests for backup and restore of the read confirmation settings and log.
 *
 * @package   leafrtool_confirm
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \backup_leafrtool_confirm_subplugin
 * @covers    \restore_leafrtool_confirm_subplugin
 */
final class backup_test extends \advanced_testcase {
    /**
     * Backs up a course with user data and restores it into a new course.
     *
     * @param int $courseid Course to back up
     * @param bool $restoreuserinfo Whether the restore includes user data
     * @return int Id of the new course
     */
    protected function backup_and_restore(int $courseid, bool $restoreuserinfo): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $backupid = 'leafrtool_confirm_backup_test';
        $file->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($backupid)
        );
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'RESTORED', $this->getDataGenerator()->create_category()->id);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        // Users are restored in both cases; only the activity's own "include user data" setting differs,
        // as when a teacher unticks user data for a single activity.
        $rc->get_plan()->get_setting('users')->set_value(true);
        foreach ($rc->get_plan()->get_settings() as $name => $setting) {
            if (str_ends_with($name, '_userinfo') && $setting->get_status() == \base_setting::NOT_LOCKED) {
                $setting->set_value($restoreuserinfo);
            }
        }
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * Returns the leafr instance of a course.
     *
     * @param int $courseid Course id
     * @return \stdClass
     */
    protected function get_leafr(int $courseid): \stdClass {
        global $DB;
        return $DB->get_record('leafr', ['course' => $courseid], '*', MUST_EXIST);
    }

    /**
     * Prepares a course with an activity that requires a confirmation and one confirmed student.
     *
     * @return array [course, leafr, student]
     */
    protected function create_confirmed_activity(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        confirm::save_settings((int)$leafr->id, true, 'I have read it.');
        confirm::record_confirmation((int)$leafr->id, (int)$student->id);
        return [$course, $leafr, $student];
    }

    /**
     * Restoring with user data brings back the settings and the confirmations.
     */
    public function test_restore_with_user_data(): void {
        [$course, , $student] = $this->create_confirmed_activity();

        $newleafr = $this->get_leafr($this->backup_and_restore((int)$course->id, true));

        $settings = confirm::get_settings((int)$newleafr->id);
        $this->assertTrue($settings->requireconfirm);
        $this->assertSame('I have read it.', $settings->confirmtext);
        $this->assertTrue(confirm::is_confirmed((int)$newleafr->id, (int)$student->id));
    }

    /**
     * Restoring without user data brings back the settings but no confirmations, even if the
     * backup contains them.
     */
    public function test_restore_without_user_data(): void {
        global $DB;
        [$course, , $student] = $this->create_confirmed_activity();

        $newleafr = $this->get_leafr($this->backup_and_restore((int)$course->id, false));

        $this->assertTrue(confirm::get_settings((int)$newleafr->id)->requireconfirm);
        $this->assertFalse(confirm::is_confirmed((int)$newleafr->id, (int)$student->id));
        $this->assertSame(0, $DB->count_records('leafrtool_confirm_log', ['leafrid' => $newleafr->id]));
        $this->assertSame(0, $DB->count_records('leafr_progress', ['leafrid' => $newleafr->id]));
    }
}
