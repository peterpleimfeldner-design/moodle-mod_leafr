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

namespace mod_leafr\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_leafr\local\progress;

/**
 * Privacy API implementation for mod_leafr.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describes the personal data stored by the plugin.
     *
     * @param collection $collection Collection to add the metadata to
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('leafr_progress', [
            'userid' => 'privacy:metadata:leafr_progress:userid',
            'seenpages' => 'privacy:metadata:leafr_progress:seenpages',
            'lastpage' => 'privacy:metadata:leafr_progress:lastpage',
            'timemodified' => 'privacy:metadata:leafr_progress:timemodified',
        ], 'privacy:metadata:leafr_progress');
        $collection->add_user_preference('mod_leafr_simpleview', 'privacy:metadata:preference:simpleview');
        return $collection;
    }

    /**
     * Returns the contexts that contain data of a user.
     *
     * @param int $userid User id
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {leafr_progress} p
                  JOIN {course_modules} cm ON cm.instance = p.leafrid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE p.userid = :userid";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'modname' => 'leafr',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Returns the users who have data in a context.
     *
     * @param userlist $userlist List to add the users to
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT p.userid
                  FROM {leafr_progress} p
                  JOIN {course_modules} cm ON cm.instance = p.leafrid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['modname' => 'leafr', 'cmid' => $context->instanceid]);
    }

    /**
     * Exports the data of a user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            $leafrid = self::get_instance_id($context);
            if (!$leafrid) {
                continue;
            }
            $record = progress::get_record($leafrid, $user->id);
            if (!$record) {
                continue;
            }
            $data = helper::get_context_data($context, $user);
            $data->seenpages = $record->seenpages;
            $data->lastpage = (int)$record->lastpage;
            $data->timemodified = transform::datetime($record->timemodified);
            writer::with_context($context)->export_data([], $data);
            helper::export_context_files($context, $user);
        }
    }

    /**
     * Exports the user preferences of a user.
     *
     * @param int $userid User id
     */
    public static function export_user_preferences(int $userid) {
        $simpleview = get_user_preferences('mod_leafr_simpleview', null, $userid);
        if ($simpleview !== null) {
            writer::export_user_preference(
                'mod_leafr',
                'mod_leafr_simpleview',
                transform::yesno($simpleview),
                get_string('privacy:metadata:preference:simpleview', 'leafr')
            );
        }
    }

    /**
     * Deletes the data of all users in a context.
     *
     * @param \context $context Context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        $leafrid = self::get_instance_id($context);
        if ($leafrid) {
            progress::delete_for_instance($leafrid);
        }
    }

    /**
     * Deletes the data of a user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $leafrid = self::get_instance_id($context);
            if ($leafrid) {
                $DB->delete_records('leafr_progress', ['leafrid' => $leafrid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Deletes the data of the approved users in a context.
     *
     * @param approved_userlist $userlist Approved users
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $leafrid = self::get_instance_id($userlist->get_context());
        $userids = $userlist->get_userids();
        if (!$leafrid || !$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['leafrid'] = $leafrid;
        $DB->delete_records_select('leafr_progress', "leafrid = :leafrid AND userid {$insql}", $params);
    }

    /**
     * Returns the Leafr instance id of a module context.
     *
     * @param \context $context Context
     * @return int Instance id, 0 if the context is not a Leafr activity
     */
    protected static function get_instance_id(\context $context): int {
        if (!$context instanceof \context_module) {
            return 0;
        }
        $cm = get_coursemodule_from_id('leafr', $context->instanceid);
        return $cm ? (int)$cm->instance : 0;
    }
}
