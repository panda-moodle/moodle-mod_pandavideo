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

require_once('../../config.php');
global $DB, $CFG, $PAGE, $OUTPUT;
require_once("{$CFG->libdir}/tablelib.php");

$courseid = optional_param("course", 0, PARAM_INT);
$course = $DB->get_record("course", ["id" => $courseid], "*", MUST_EXIST);

require_course_login($course);

$PAGE->set_url("/mod/pandavideo/reports.php", ["course" => $courseid]);
$PAGE->set_title("{$course->shortname}: " . get_string("reports"));
$PAGE->set_heading($course->fullname . ": " . get_string("modulename", "mod_pandavideo"));
echo $OUTPUT->header();

$title = get_string("reports") . ": " . get_string("modulename", "mod_pandavideo");
echo $OUTPUT->heading($title, 2, "main", "pandavideoheading");

$sql = "SELECT cm.*, sv.name
          FROM {course_modules} cm
          JOIN {modules}        md ON md.id = cm.module
          JOIN {pandavideo}     sv ON sv.id = cm.instance
         WHERE sv.course = :course
           AND md.name   = 'pandavideo'";
$pandavideos = $DB->get_records_sql($sql, ["course" => $courseid]);
$reportnode = ["children" => []];
foreach ($pandavideos as $pandavideo) {
    $videoname = format_string($pandavideo->name);
    $reportnode["children"][] = [
        "display" => true,
        "action" => "{$CFG->wwwroot}/mod/pandavideo/report.php?id={$pandavideo->id}",
        "text" => "{$videoname}",
    ];
}

echo $OUTPUT->render_from_template("core/report_link_page", ["node" => $reportnode]);

echo $OUTPUT->footer();
