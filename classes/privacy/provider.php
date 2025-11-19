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
 * The mod_pandavideo module does not store any data.
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pandavideo\privacy;

use context;
use context_module;
use moodle_recordset;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Class provider
 *
 * @package mod_pandavideo\privacy
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     *
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_external_location_link(
            "pandavideo.com.br",
            [
                "user_id" => "privacy:metadata:pandavideo_view:user_id",
                "currenttime" => "privacy:metadata:pandavideo_view:currenttime",
                "duration" => "privacy:metadata:pandavideo_view:duration",
                "percent" => "privacy:metadata:pandavideo_view:percent",
                "videomap" => "privacy:metadata:pandavideo_view:videomap",
                "timecreated" => "privacy:metadata:pandavideo_view:timecreated",
                "timemodified" => "privacy:metadata:pandavideo_view:timemodified",
            ],
            "privacy:metadata:pandavideo_view",
            );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     *
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     * @throws \Exception
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        $sql = "
            SELECT DISTINCT ctx.id
              FROM {pandavideo} sv
              JOIN {modules} m
                ON m.name = 'pandavideo'
              JOIN {course_modules} cm
                ON cm.instance = sv.id
               AND cm.module = m.id
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :modulelevel
              JOIN {pandavideo_view} svv
                ON svv.cm_id = cm.id
             WHERE svv.user_id = :user_id";

        $params = [
            "modulelevel" => CONTEXT_MODULE,
            "user_id" => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin
     *                           combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $sql = "SELECT svv.user_id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND
                  JOIN {pandavideo_view} svv ON svv.cm_id = cm.id
                 WHERE cm.id = :instanceid
                   AND m.name = 'pandavideo'";

        $params = [
            "instanceid" => $context->instanceid,
        ];

        $userlist->add_from_sql("userid", $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     *
     * @throws \Exception
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $userid = $user->id;
        $cmids = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->instanceid;
            }
            return $carry;
        }, []);
        if (empty($cmids)) {
            return;
        }

        $cmidstocmids = static::get_pandavideo_ids_to_cmids_from_cmids($cmids);
        $cmids = array_keys($cmidstocmids);

        // Export the messages.
        list($insql, $inparams) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ["user_id" => $userid]);
        $recordset = $DB->get_recordset_select("pandavideo_view", "cm_id $insql AND user_id = :user_id", $params, "timestamp, id");
        static::recordset_loop_and_export($recordset, "cm_id", [], function ($carry, $record) use ($user, $cmidstocmids) {
            $message = $record->message;
            if ($record->issystem) {
                $message = get_string("message" . $record->message, "mod_pandavideo", fullname($user));
            }
            $carry[] = [
                "message" => $message,
                "sent_at" => transform::datetime($record->timestamp),
                "is_system_generated" => transform::yesno($record->issystem),
            ];
            return $carry;

        }, function($cmid, $data) use ($user, $cmidstocmids) {
            $context = context_module::instance($cmidstocmids[$cmid]);
            $contextdata = helper::get_context_data($context, $user);
            $finaldata = (object) array_merge((array) $contextdata, ["messages" => $data]);
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $finaldata);
        });
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     *
     * @throws \Exception
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id("pandavideo", $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records_select("pandavideo_view", "cm_id = :cm_id", ["cm_id" => $cm->id]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     *
     * @throws \Exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $cmids = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->instanceid;
            }
            return $carry;
        }, []);
        if (empty($cmids)) {
            return;
        }

        $cmidstocmids = static::get_pandavideo_ids_to_cmids_from_cmids($cmids);
        $cmids = array_keys($cmidstocmids);

        list($insql, $inparams) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $sql = "cm_id {$insql} AND user_id = :user_id";
        $params = array_merge($inparams, ["user_id" => $userid]);

        $DB->delete_records_select("pandavideo_view", $sql, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     *
     * @throws \Exception
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $cm = $DB->get_record("course_modules", ["id" => $context->instanceid]);

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(["cm_id" => $cm->id], $userinparams);
        $sql = "cm_id = :cm_id AND user_id {$userinsql}";

        $DB->delete_records_select("pandavideo_view", $sql, $params);
    }

    /**
     * Return a dict of pandavideo IDs mapped to their course module ID.
     *
     * @param array $cmids The course module IDs.
     *
     * @return array
     * @throws \Exception
     */
    protected static function get_pandavideo_ids_to_cmids_from_cmids(array $cmids) {
        global $DB;
        list($insql, $inparams) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $sql = "
            SELECT c.id, cm.id AS cmid
              FROM {pandavideo} c
              JOIN {modules} m
                ON m.name = 'pandavideo'
              JOIN {course_modules} cm
                ON cm.instance = c.id
               AND cm.module = m.id
             WHERE cm.id $insql";
        $params = array_merge($inparams);
        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Loop and export from a recordset.
     *
     * @param moodle_recordset $recordset The recordset.
     * @param string $splitkey            The record key to determine when to export.
     * @param mixed $initial              The initial data to reduce from.
     * @param callable $reducer           The function to return the dataset, receives current dataset, and the current
     *                                    record.
     * @param callable $export            The function to export the dataset, receives the last value from $splitkey
     *                                    and the dataset.
     *
     * @return void
     * @throws \Exception
     */
    protected static function recordset_loop_and_export(moodle_recordset $recordset, $splitkey, $initial,
                                                        callable $reducer, callable $export) {

        $data = $initial;
        $lastid = null;

        foreach ($recordset as $record) {
            if ($lastid && $record->{$splitkey} != $lastid) {
                $export($lastid, $data);
                $data = $initial;
            }
            $data = $reducer($data, $record);
            $lastid = $record->{$splitkey};
        }
        $recordset->close();

        if (!empty($lastid)) {
            $export($lastid, $data);
        }
    }
}
