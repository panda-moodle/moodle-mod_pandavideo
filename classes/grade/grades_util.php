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
 * Grades implementation for mod_pandavideo.
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pandavideo\grade;

use Exception;

/**
 * Class grades_util
 *
 * @package mod_pandavideo\grade
 */
class grades_util {

    /**
     * Function update
     *
     * @param $cmid
     * @param $percent
     * @throws Exception
     */
    public static function update($cmid, $percent) {
        global $DB, $CFG, $USER;

        require_once("{$CFG->libdir}/completionlib.php");

        $cm = get_coursemodule_from_id("pandavideo", $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $pandavideo = $DB->get_record("pandavideo", ["id" => $cm->instance], "*", MUST_EXIST);

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            if ($percent >= $pandavideo->completionpercent) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }

        if ($pandavideo->grade_approval == 1) {
            $grade = [
                "userid" => $USER->id,
                "rawgrade" => $percent,
            ];

            require_once("{$CFG->libdir}/gradelib.php");
            $grades = grade_get_grades($course->id, "mod", "pandavideo", $pandavideo->id, $USER->id);
            if (isset($grades->items[0]->grades)) {
                foreach ($grades->items[0]->grades as $gradeitem) {
                    if (intval($percent) > intval($gradeitem->grade)) {
                        self::grade_item_update($pandavideo, $grade);
                        break;
                    }
                }
            }
        }
    }

    /**
     * Function grade_item_update
     *
     * @param $pandavideo
     * @param null $grades
     * @return int
     */
    public static function grade_item_update($pandavideo, $grades = null) {
        global $CFG;

        require_once("{$CFG->dirroot}/lib/gradelib.php");

        if (!defined("GRADE_TYPE_VALUE")) {
            define("GRADE_TYPE_VALUE", 1);
        }

        $params = [
            "itemname" => $pandavideo->name,
            "gradetype" => GRADE_TYPE_VALUE,
            "grademax" => 100,
            "grademin" => 0,
        ];

        if (isset($pandavideo->cmidnumber)) {
            $params["idnumber"] = $pandavideo->cmidnumber;
        }

        if ($grades === "reset") {
            $params["reset"] = true;
            $grades = null;
        }

        return grade_update(
            "mod/pandavideo",
            $pandavideo->course,
            "mod",
            "pandavideo",
            $pandavideo->id,
            0,
            $grades,
            $params
        );
    }
}
