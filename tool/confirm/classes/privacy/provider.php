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

namespace leafrtool_confirm\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for leafrtool_confirm.
 *
 * @package   leafrtool_confirm
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the personal data stored by the plugin.
     *
     * @param collection $collection Collection to add the metadata to
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('leafrtool_confirm_log', [
            'userid' => 'privacy:metadata:leafrtool_confirm_log:userid',
            'timeconfirmed' => 'privacy:metadata:leafrtool_confirm_log:timeconfirmed',
        ], 'privacy:metadata:leafrtool_confirm_log');
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
                  FROM {leafrtool_confirm_log} l
                  JOIN {course_modules} cm ON cm.instance = l.leafrid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE l.userid = :userid";
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
        $sql = "SELECT l.userid
                  FROM {leafrtool_confirm_log} l
                  JOIN {course_modules} cm ON cm.instance = l.leafrid
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
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            $leafrid = self::get_instance_id($context);
            if (!$leafrid) {
                continue;
            }
            $record = $DB->get_record('leafrtool_confirm_log', ['leafrid' => $leafrid, 'userid' => $user->id]);
            if ($record) {
                $data = helper::get_context_data($context, $user);
                $data->timeconfirmed = transform::datetime($record->timeconfirmed);
                writer::with_context($context)->export_data([get_string('pluginname', 'leafrtool_confirm')], $data);
            }
        }
    }

    /**
     * Deletes the data of all users in a context.
     *
     * @param \context $context Context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        $leafrid = self::get_instance_id($context);
        if ($leafrid) {
            $DB->delete_records('leafrtool_confirm_log', ['leafrid' => $leafrid]);
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
                $DB->delete_records('leafrtool_confirm_log', ['leafrid' => $leafrid, 'userid' => $userid]);
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
        $DB->delete_records_select('leafrtool_confirm_log', "leafrid = :leafrid AND userid {$insql}", $params);
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
