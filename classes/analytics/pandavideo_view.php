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

namespace mod_pandavideo\analytics;

use Exception;
use mod_pandavideo\grade\grades_util;

/**
 * Panda Video View implementation for mod_pandavideo.
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pandavideo_view {

    /**
     * Function create
     *
     * @param $cmid
     *
     * @return mixed|object
     * @throws Exception
     */
    public static function create($cmid) {
        global $USER, $DB;

        $sql = "SELECT * FROM {pandavideo_view} WHERE cm_id = :cm_id AND user_id = :user_id ORDER BY id DESC LIMIT 1";
        $pandavideoview = $DB->get_record_sql($sql, ["cm_id" => $cmid, "user_id" => $USER->id]);

        if ($pandavideoview) {
            if ($pandavideoview->currenttime > ($pandavideoview->duration - 3)) {
                return self::internal_create($cmid);
            }
            if ($pandavideoview->percent < 90) {
                return $pandavideoview;
            }
        }

        return self::internal_create($cmid);
    }

    /**
     * Function internal_create
     *
     * @param $cmid
     * @return object
     */
    private static function internal_create($cmid) {
        global $USER, $DB;

        $pandavideoview = (object)[
            "cm_id" => $cmid,
            "user_id" => $USER->id,
            "currenttime" => 0,
            "duration" => 0,
            "percent" => 0,
            "videomap" => "{}",
            "timecreated" => time(),
            "timemodified" => time(),
        ];

        try {
            $pandavideoview->id = $DB->insert_record("pandavideo_view", $pandavideoview);
        } catch (\dml_exception $e) {
            return (object)['id' => 0];
        }

        return $pandavideoview;
    }

    /**
     * Function update
     *
     * @param $viewid
     * @param $currenttime
     * @param $duration
     * @param $percent
     * @param $videomap
     * @return bool
     * @throws Exception
     */
    public static function update($viewid, $currenttime, $duration, $percent, $videomap) {
        global $DB, $USER, $CFG;

        $pandavideoview = $DB->get_record('pandavideo_view', ['id' => $viewid, "user_id" => $USER->id]);

        if ($pandavideoview) {
            $pandavideoview->currenttime = $currenttime;
            $pandavideoview->duration = $duration;
            $pandavideoview->percent = $percent;
            $pandavideoview->videomap = $videomap;
            $pandavideoview->timemodified = time();

            $status = $DB->update_record("pandavideo_view", $pandavideoview);

            require_once("{$CFG->dirroot}/mod/pandavideo/classes/grade/grades_util.php");
            grades_util::update($pandavideoview->cm_id, $percent);

            return $status;
        }
        return false;
    }
}
