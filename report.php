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
 * Report for pandavideo.
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pandavideo\report\pandavideo_view;

require_once('../../config.php');
global $DB, $CFG, $USER, $PAGE, $OUTPUT;
require_once("{$CFG->libdir}/tablelib.php");

$id = optional_param("id", 0, PARAM_INT);
$userid = optional_param("u", false, PARAM_INT);
$cm = get_coursemodule_from_id("pandavideo", $id, 0, false, MUST_EXIST);
$course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
$pandavideo = $DB->get_record("pandavideo", ["id" => $cm->instance], "*", MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability("mod/pandavideo:view_report", $context);

if (!has_capability("moodle/course:manageactivities", $context, $USER)) {
    $userid = $USER->id;
}

$table = new pandavideo_view("pandavideo_report", $cm->id, $userid, $pandavideo);

if (!$table->is_downloading()) {
    $PAGE->set_url("/mod/pandavideo/report.php", ["id" => $cm->id]);
    $PAGE->set_title("{$course->shortname}: {$pandavideo->name}");
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    $linkvoltar = "";
    if ($table->userid) {
        $linkvoltar = " <a href='?id={$table->cmid}' class='pandavideo-report-link'>" . get_string("back") . "</a>";
    }

    if ($userid) {
        $user = $DB->get_record("user", ["id" => $userid]);
        $title = get_string("report_filename", "mod_pandavideo", fullname($user));
    } else {
        $geral = get_string("report_filename_geral", "mod_pandavideo");
        $title = get_string("report_filename", "mod_pandavideo", $geral);
    }
    echo $OUTPUT->heading("{$title}{$linkvoltar}", 2, "main", "pandavideoheading");
}

$table->define_baseurl("{$CFG->wwwroot}/mod/pandavideo/report.php?id={$cm->id}&u={$userid}");
$table->out(40, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
