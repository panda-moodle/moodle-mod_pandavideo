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
 * view file
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pandavideo\analytics\pandavideo_view;
use mod_pandavideo\panda\repository;
use mod_pandavideo\event\course_module_viewed;

require_once('../../config.php');
global $DB, $CFG, $USER, $PAGE, $OUTPUT;

require_once("{$CFG->libdir}/completionlib.php");

$id = optional_param("id", 0, PARAM_INT);
$n = optional_param("n", 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id("pandavideo", $id, 0, false, MUST_EXIST);
    $course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
    $pandavideo = $DB->get_record("pandavideo", ["id" => $cm->instance], "*", MUST_EXIST);
} else {
    if ($n) {
        $pandavideo = $DB->get_record("pandavideo", ["id" => $n], "*", MUST_EXIST);
        $course = $DB->get_record("course", ["id" => $pandavideo->course], "*", MUST_EXIST);
        $cm = get_coursemodule_from_instance("pandavideo", $pandavideo->id, $course->id, false, MUST_EXIST);
    } else {
        throw new Exception("You must specify a course_module ID or an instance ID");
    }
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability("mod/pandavideo:view", $context);

$event = course_module_viewed::create([
    "objectid" => $PAGE->cm->instance,
    "context" => $PAGE->context,
]);
$event->add_record_snapshot("course", $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $pandavideo);
$event->trigger();

// Update "viewed" state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$params = [
    "n" => $n,
    "id" => $id,
];
$PAGE->set_url("/mod/pandavideo/view.php", $params);
$PAGE->set_title("{$course->shortname}: {$pandavideo->name}");
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

echo $OUTPUT->header();

$config = get_config("pandavideo");
$pandavideoview = pandavideo_view::create($cm->id);

try {
    $pandatoken = get_config("pandavideo", "panda_token");
    $pandafile = "{$CFG->dirroot}/repository/pandavideo/classes/pandarepository.php";
    $pandavideo = repository::oembed($pandavideo->pandaurl);
    $pandavideo->video_player = preg_replace('/.*src="(.*?)".*/', "$1", $pandavideo->html);

    echo $OUTPUT->render_from_template("mod_pandavideo/embed", [
        "video_player" => $pandavideo->video_player,
        "pandavideoview_id" => $pandavideoview->id ?? 0,
        "pandavideoview_currenttime" => intval($pandavideoview->currenttime),
        "ratio" => max(($pandavideo->height / $pandavideo->width) * 100, 20),
        "showvideomap" => $config->showvideomap,
        "videomap_data" => json_decode($pandavideoview->videomap) ? $pandavideoview->videomap : "[]",
    ]);
} catch (Exception $e) {
    echo $OUTPUT->render_from_template("mod_pandavideo/error", [
        "elementId" => "panda-error",
        "type" => "danger",
        "message" => $e->getMessage(),
    ]);
}

echo $OUTPUT->footer();
