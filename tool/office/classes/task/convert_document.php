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

namespace leafrtool_office\task;

use core_files\converter;
use core_files\conversion;
use leafrtool_office\local\conversion as conversion_record;

defined('MOODLE_INTERNAL') || die();

/**
 * Converts an uploaded Word/PowerPoint/ODF file to PDF in the background, using Moodle's own
 * document converter (site administration must have one configured, see leafrtool_office_help.php
 * and ROADMAP.md Paket I). Requeues itself while the conversion is still in progress, the same
 * pattern assignfeedback_editpdf uses for its own PDF conversions.
 *
 * @package   leafrtool_office
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convert_document extends \core\task\adhoc_task {

    /** @var int Gives up after this many retries (roughly 10 minutes at 30s apart). */
    const MAX_ATTEMPTS = 20;

    /** @var int Seconds between retries while the converter is still working. */
    const RETRY_DELAY = 30;

    /**
     * Attempts the conversion, or requeues itself if it is still in progress.
     */
    public function execute() {
        $data = $this->get_custom_data();
        $leafrid = (int)$data->leafrid;
        $fileid = (int)$data->fileid;
        $attempt = (int)($data->attempt ?? 1);

        $file = get_file_storage()->get_file_by_id($fileid);
        if (!$file) {
            // The source file was replaced or removed before this task ran; nothing to do.
            return;
        }

        $converter = new converter();
        if (!$converter->can_convert_storedfile_to($file, 'pdf')) {
            conversion_record::mark_failed($leafrid, get_string('conversionerror_noconverter', 'leafrtool_office'));
            return;
        }

        $result = $converter->start_conversion($file, 'pdf');

        switch ($result->get('status')) {
            case conversion::STATUS_COMPLETE:
                $this->store_result($leafrid, $result);
                break;

            case conversion::STATUS_IN_PROGRESS:
            case conversion::STATUS_PENDING:
                if ($attempt >= self::MAX_ATTEMPTS) {
                    conversion_record::mark_failed($leafrid, get_string('conversionerror_timeout', 'leafrtool_office'));
                    break;
                }
                $next = new self();
                $next->set_custom_data((object)['leafrid' => $leafrid, 'fileid' => $fileid, 'attempt' => $attempt + 1]);
                $next->set_next_run_time(time() + self::RETRY_DELAY);
                \core\task\manager::queue_adhoc_task($next);
                break;

            default:
                conversion_record::mark_failed($leafrid, (string)$result->get('statusmessage'));
        }
    }

    /**
     * Copies the converted PDF into the activity's own file area and marks the conversion complete.
     *
     * @param int $leafrid Leafr instance id
     * @param conversion $result The completed conversion
     */
    private function store_result(int $leafrid, conversion $result): void {
        $destfile = $result->get_destfile();
        if (!$destfile) {
            conversion_record::mark_failed($leafrid, get_string('conversionerror_noresult', 'leafrtool_office'));
            return;
        }
        $cm = get_coursemodule_from_instance('leafr', $leafrid);
        if (!$cm) {
            // The activity was deleted while this task was queued.
            return;
        }
        $context = \context_module::instance($cm->id);

        $fs = get_file_storage();
        foreach ($fs->get_area_files($context->id, 'mod_leafr', 'convertedpdf', 0, 'id', false) as $old) {
            $old->delete();
        }
        $fs->create_file_from_storedfile([
            'contextid' => $context->id,
            'component' => 'mod_leafr',
            'filearea' => 'convertedpdf',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'document.pdf',
        ], $destfile);

        conversion_record::mark_complete($leafrid);
    }
}
