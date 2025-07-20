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
 * Progress Service
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pandavideo\external;

use Exception;
use external_api;
use external_value;
use mod_pandavideo\analytics\pandavideo_view;
use external_function_parameters;
use external_single_structure;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once("{$CFG->libdir}/externallib.php");

/**
 * Progress Service
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress extends external_api {
    /**
     * Describes the parameters for save
     *
     * @return external_function_parameters
     */
    public static function save_parameters() {
        return new external_function_parameters([
            "viewid" => new external_value(
                PARAM_INT, "Unique identifier of the videoview instance", VALUE_REQUIRED
            ),
            "currenttime" => new external_value(
                PARAM_INT, "Current playback position in seconds", VALUE_REQUIRED
            ),
            "duration" => new external_value(
                PARAM_INT, "Total duration of the video in seconds", VALUE_REQUIRED
            ),
            "percent" => new external_value(
                PARAM_INT, "Percentage of the video that has been watched", VALUE_REQUIRED
            ),
            "videomap" => new external_value(
                PARAM_RAW, "Serialized data or structure representing the video playback map", VALUE_REQUIRED
            ),
        ]);
    }

    /**
     * Record watch time
     *
     * @param int $viewid
     * @param int $currenttime
     * @param int $duration
     * @param int $percent
     * @param string $videomap
     * @return array
     * @throws Exception
     */
    public static function save($viewid, $currenttime, $duration, $percent, $videomap) {
        global $DB;

        $pandavideoview = $DB->get_record("pandavideo_view", ["id" => $viewid]);
        if ($pandavideoview) {
            $context = \context_module::instance($pandavideoview->cm_id);
            self::validate_context($context);
            require_capability("mod/pandavideo:view", $context);

            $params = self::validate_parameters(self::save_parameters(), [
                "viewid" => $viewid,
                "currenttime" => $currenttime,
                "duration" => $duration,
                "percent" => $percent,
                "videomap" => $videomap,
            ]);
            $viewid = $params["viewid"];
            $currenttime = $params["currenttime"];
            $duration = $params["duration"];
            $percent = $params["percent"];
            $videomap = $params["videomap"];

            pandavideo_view::update($viewid, $currenttime, $duration, $percent, $videomap);
            return ["success" => true, "status" => "OK"];
        }
        return ["success" => false, "status" => "notFound"];
    }

    /**
     * Describes the save return value.
     *
     * @return external_single_structure
     */
    public static function save_returns() {
        return new external_single_structure([
            "success" => new external_value(PARAM_BOOL),
            "status" => new external_value(PARAM_RAW),
        ]);
    }
}
