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
 * Prints an instance of mod_pandavideo.
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\session\manager;
use mod_pandavideo\analytics\pandavideo_view;
use mod_pandavideo\event\course_module_viewed;

require(__DIR__ . "/../../config.php");

$id = optional_param("id", 0, PARAM_INT);
$cm = get_coursemodule_from_id("pandavideo", $id, 0, false, MUST_EXIST);
$course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
$pandavideo = $DB->get_record("pandavideo", ["id" => $cm->instance], "*", MUST_EXIST);

$token = required_param("token", PARAM_TEXT);
$externalservice = $DB->get_record("external_services", ["shortname" => MOODLE_OFFICIAL_MOBILE_SERVICE]);
$externaltoken = $DB->get_record("external_tokens", ["token" => $token, "externalserviceid" => $externalservice->id], "userid");
$user = $DB->get_record("user", ["id" => $externaltoken->userid]);

if ($user) {
    manager::login_user($user);
    require_course_login($course, false, null, false, true);
} else {
    redirect(new moodle_url("/mod/pandavideo/view.php", ["id" => $id]));
}

$context = context_module::instance($cm->id);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_url("/mod/pandavideo/view-mobile.php", ["id" => $cm->id]);
$PAGE->set_title(format_string($pandavideo->name));
$PAGE->set_pagelayout("embedded");
$PAGE->add_body_class("body-pandavideo-mobile-view");

$event = course_module_viewed::create([
    "objectid" => $PAGE->cm->instance,
    "context" => $context,
]);
$event->add_record_snapshot("course", $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $pandavideo);
$event->trigger();

echo $OUTPUT->header();

$config = get_config("pandavideo");
$pandavideoview = pandavideo_view::create($cm->id);

try {
    $pandatoken = get_config("pandavideo", "panda_token");
    $pandafile = "{$CFG->dirroot}/repository/pandavideo/classes/pandarepository.php";
    $pandavideo = \mod_pandavideo\panda\repository::oembed($pandavideo->pandaurl);
    $pandavideo->video_player = preg_replace('/.*src="(.*?)".*/', "$1", $pandavideo->html);
    $pandavideo->video_player .= \mod_pandavideo\panda\repository::get_drm_watermark();

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
