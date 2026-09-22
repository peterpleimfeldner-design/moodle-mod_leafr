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

use mod_leafr\local\progress;

/**
 * Tests for the functions in lib.php.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * Loads lib.php.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/leafr/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Creating an activity stores the settings and the PDF file.
     *
     * @covers ::leafr_add_instance
     * @covers ::leafr_get_pdf_file
     */
    public function test_add_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $leafr = $this->getDataGenerator()->create_module('leafr', [
            'course' => $course->id,
            'name' => 'Handbook',
            'initialpage' => 0,
            'completionpercent' => 250,
        ]);

        $record = $DB->get_record('leafr', ['id' => $leafr->id]);
        $this->assertEquals('Handbook', $record->name);
        $this->assertEquals(1, $record->initialpage);
        $this->assertEquals(100, $record->completionpercent);
        $this->assertEquals(0, $record->totalpages);

        $file = leafr_get_pdf_file(\context_module::instance($leafr->cmid));
        $this->assertNotNull($file);
        $this->assertEquals('sample.pdf', $file->get_filename());
    }

    /**
     * Deleting an activity removes the progress of all users.
     *
     * @covers ::leafr_delete_instance
     */
    public function test_delete_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        progress::record((int)$leafr->id, 5, [1, 2]);

        $this->assertTrue(leafr_delete_instance($leafr->id));
        $this->assertFalse($DB->record_exists('leafr', ['id' => $leafr->id]));
        $this->assertFalse($DB->record_exists('leafr_progress', ['leafrid' => $leafr->id]));
        $this->assertFalse(leafr_delete_instance($leafr->id));
    }

    /**
     * Resetting the course removes the reading progress.
     *
     * @covers ::leafr_reset_userdata
     */
    public function test_reset_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $leafr = $this->getDataGenerator()->create_module('leafr', ['course' => $course->id]);
        $keep = $this->getDataGenerator()->create_module('leafr', ['course' => $other->id]);
        progress::record((int)$leafr->id, 5, [1]);
        progress::record((int)$keep->id, 5, [1]);

        $status = leafr_reset_userdata((object)['courseid' => $course->id, 'reset_leafr_progress' => 1]);
        $this->assertCount(1, $status);
        $this->assertFalse($DB->record_exists('leafr_progress', ['leafrid' => $leafr->id]));
        $this->assertTrue($DB->record_exists('leafr_progress', ['leafrid' => $keep->id]));
    }

    /**
     * Duplicating an activity (backup and restore) keeps settings and file.
     *
     * @covers \backup_leafr_activity_task
     * @covers \restore_leafr_activity_task
     */
    public function test_duplicate(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $leafr = $this->getDataGenerator()->create_module('leafr', [
            'course' => $course->id,
            'showtoc' => 0,
            'downloadallowed' => 1,
            'initialpage' => 3,
        ]);
        $DB->set_field('leafr', 'totalpages', 12, ['id' => $leafr->id]);

        $cm = get_fast_modinfo($course)->get_cm($leafr->cmid);
        $newcm = duplicate_module($course, $cm);
        $copy = $DB->get_record('leafr', ['id' => $newcm->instance]);

        $this->assertEquals(0, $copy->showtoc);
        $this->assertEquals(1, $copy->downloadallowed);
        $this->assertEquals(3, $copy->initialpage);
        $this->assertEquals(12, $copy->totalpages);
        $this->assertNotNull(leafr_get_pdf_file(\context_module::instance($newcm->id)));
    }

    /**
     * The module declares the expected features.
     *
     * @covers ::leafr_supports
     */
    public function test_supports(): void {
        $this->assertTrue(leafr_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(leafr_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertFalse(leafr_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertEquals(MOD_PURPOSE_CONTENT, leafr_supports(FEATURE_MOD_PURPOSE));
        $this->assertNull(leafr_supports('unknownfeature'));
    }
}
