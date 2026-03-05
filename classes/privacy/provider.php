<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Privacy API implementation for mod_leafr (GDPR/DSGVO compliance)
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_leafr\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider for mod_leafr.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    // ── Metadata declarations ──────────────────────────────────────────

    /**
     * Returns metadata about the stored user data.
     *
     * @param collection $collection The metadata collection to add data to
     * @return collection The enriched collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference('leafr_pos_*', [
            'preference' => 'privacy:metadata:preference:readingpos',
        ]);

        $collection->add_user_preference('leafr_progress_*', [
            'preference' => 'privacy:metadata:preference:progress',
        ]);

        $collection->add_user_preference('leafr_simpleview_*', [
            'preference' => 'privacy:metadata:preference:simpleview',
        ]);

        $collection->add_database_table('leafr_bookmarks', [
            'userid'       => 'privacy:metadata:leafr_bookmarks:userid',
            'pageno'       => 'privacy:metadata:leafr_bookmarks:pageno',
            'label'        => 'privacy:metadata:leafr_bookmarks:label',
            'note'         => 'privacy:metadata:leafr_bookmarks:note',
            'timecreated'  => 'privacy:metadata:leafr_bookmarks:timecreated',
            'timemodified' => 'privacy:metadata:leafr_bookmarks:timemodified',
        ], 'privacy:metadata:leafr_bookmarks');

        return $collection;
    }

    // ── Context lookups ───────────────────────────────────────────────

    /**
     * Returns all contexts that contain user data for the given user.
     *
     * @param int $userid The user's id
     * @return contextlist The contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Find contexts via bookmarks table.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                  JOIN {leafr} l ON l.id = cm.instance
                  JOIN {leafr_bookmarks} lb ON lb.leafrid = l.id
                 WHERE lb.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'ctxlevel' => CONTEXT_MODULE,
            'userid'   => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Returns all users who have user data in the given context.
     *
     * @param userlist $userlist The userlist to add users to
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT lb.userid
                  FROM {leafr_bookmarks} lb
                  JOIN {leafr} l ON l.id = lb.leafrid
                  JOIN {course_modules} cm ON cm.instance = l.id AND cm.module = (
                      SELECT id FROM {modules} WHERE name = 'leafr'
                  )
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    // ── Data export ───────────────────────────────────────────────────

    /**
     * Exports all data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved context list
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('leafr', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $data = [];

            // Export reading position.
            $pos = get_user_preferences('leafr_pos_' . $cm->id, null, $user->id);
            if ($pos !== null) {
                $data['readingposition'] = (int)$pos;
            }

            // Export progress.
            $progress = get_user_preferences('leafr_progress_' . $cm->id, null, $user->id);
            if ($progress !== null) {
                $data['progress'] = json_decode($progress, true);
            }

            // Export bookmarks.
            $bookmarks = $DB->get_records('leafr_bookmarks', [
                'leafrid' => $cm->instance,
                'userid'  => $user->id,
            ]);
            if (!empty($bookmarks)) {
                $data['bookmarks'] = array_values((array)$bookmarks);
            }

            if (!empty($data)) {
                writer::with_context($context)->export_data(['leafr'], (object)$data);
            }
        }
    }

    // ── Data deletion ─────────────────────────────────────────────────

    /**
     * Deletes data for a specific user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved context list
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('leafr', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // Delete bookmarks.
            $DB->delete_records('leafr_bookmarks', [
                'leafrid' => $cm->instance,
                'userid'  => $user->id,
            ]);

            // Delete user preferences.
            unset_user_preference('leafr_pos_'        . $cm->id, $user->id);
            unset_user_preference('leafr_progress_'   . $cm->id, $user->id);
            unset_user_preference('leafr_simpleview_' . $cm->id, $user->id);
        }
    }

    /**
     * Deletes data for all users in the given context.
     *
     * @param \context $context The module context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('leafr', $context->instanceid);
        if (!$cm) {
            return;
        }

        // Delete all bookmarks for this activity.
        $DB->delete_records('leafr_bookmarks', ['leafrid' => $cm->instance]);

        // Delete all user preferences for this activity via SQL LIKE.
        $DB->delete_records_select(
            'user_preferences',
            $DB->sql_like('name', ':pattern1') . ' OR ' . $DB->sql_like('name', ':pattern2') . ' OR ' . $DB->sql_like('name', ':pattern3'),
            [
                'pattern1' => 'leafr_pos_' . $cm->id,
                'pattern2' => 'leafr_progress_' . $cm->id,
                'pattern3' => 'leafr_simpleview_' . $cm->id,
            ]
        );
    }

    /**
     * Deletes data for the given users in the given context.
     *
     * @param approved_userlist $userlist The approved userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('leafr', $context->instanceid);
        if (!$cm) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $inparams['leafrid'] = $cm->instance;

        $DB->delete_records_select('leafr_bookmarks', "leafrid = :leafrid AND userid $insql", $inparams);

        foreach ($userlist->get_userids() as $userid) {
            unset_user_preference('leafr_pos_'        . $cm->id, $userid);
            unset_user_preference('leafr_progress_'   . $cm->id, $userid);
            unset_user_preference('leafr_simpleview_' . $cm->id, $userid);
        }
    }
}
