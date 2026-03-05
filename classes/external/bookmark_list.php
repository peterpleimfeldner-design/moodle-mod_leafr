<?php
namespace mod_leafr\external;

use external_api;
use external_function_parameters;
use external_value;
use external_multiple_structure;
use external_single_structure;

class bookmark_list extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/leafr:view', $context);

        if (!\leafr_pro_is_available()) {
            throw new \moodle_exception('requirespro', 'leafr');
        }

        $cm = get_coursemodule_from_id('leafr', $params['cmid'], 0, false, MUST_EXIST);

        $bookmarks = $DB->get_records('leafr_bookmarks', [
            'leafrid' => $cm->instance,
            'userid'  => $USER->id,
        ], 'timemodified DESC');

        $result = [];
        foreach ($bookmarks as $bm) {
            $result[] = [
                'id'           => (int)$bm->id,
                'pageno'       => (int)$bm->pageno,
                'label'        => $bm->label,
                'note'         => $bm->note ?? '',
                'timecreated'  => (int)$bm->timecreated,
                'timemodified' => (int)$bm->timemodified,
            ];
        }

        return $result;
    }

    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'           => new external_value(PARAM_INT,  'Bookmark ID'),
                'pageno'       => new external_value(PARAM_INT,  'Page number'),
                'label'        => new external_value(PARAM_TEXT, 'Label'),
                'note'         => new external_value(PARAM_TEXT, 'Optional note'),
                'timecreated'  => new external_value(PARAM_INT,  'Created timestamp'),
                'timemodified' => new external_value(PARAM_INT,  'Modified timestamp'),
            ])
        );
    }
}
