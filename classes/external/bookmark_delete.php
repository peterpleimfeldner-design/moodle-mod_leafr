<?php
namespace mod_leafr\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

class bookmark_delete extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'bookmarkid' => new external_value(PARAM_INT, 'Bookmark ID to delete'),
        ]);
    }

    public static function execute(int $cmid, int $bookmarkid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'       => $cmid,
            'bookmarkid' => $bookmarkid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/leafr:view', $context);

        if (!\leafr_pro_is_available()) {
            throw new \moodle_exception('requirespro', 'leafr');
        }

        $cm = get_coursemodule_from_id('leafr', $params['cmid'], 0, false, MUST_EXIST);

        // Only delete own bookmarks.
        $bookmark = $DB->get_record('leafr_bookmarks', [
            'id'      => $params['bookmarkid'],
            'leafrid' => $cm->instance,
            'userid'  => $USER->id,
        ]);

        if (!$bookmark) {
            throw new \moodle_exception('bookmarknotfound', 'leafr');
        }

        $DB->delete_records('leafr_bookmarks', ['id' => $params['bookmarkid']]);

        return ['status' => 'deleted'];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status'),
        ]);
    }
}
