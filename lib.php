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
 * lib file
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pandavideo\grade\grades_util;

/**
 * Function pandavideo_supports
 *
 * @param $feature
 *
 * @return bool|int|null|string
 */
function pandavideo_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMMENT:
            return true;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Function pandavideo_update_grades
 *
 * @param stdClass $pandavideo
 * @param int $userid
 * @param bool $nullifnone
 *
 * @throws coding_exception
 * @throws dml_exception
 */
function pandavideo_update_grades($pandavideo, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once("{$CFG->libdir}/gradelib.php");

    if ($pandavideo->grade_approval) {
        if ($grades = pandavideo_get_user_grades($pandavideo, $userid)) {
            grades_util::grade_item_update($pandavideo, $grades);
        }
    }
}

/**
 * Function pandavideo_get_user_grades
 *
 * @param stdClass $pandavideo
 * @param int $userid
 *
 * @return array|bool
 * @throws coding_exception
 * @throws dml_exception
 */
function pandavideo_get_user_grades($pandavideo, $userid = 0) {
    global $DB;

    if (!$pandavideo->grade_approval) {
        return false;
    }

    $cm = get_coursemodule_from_instance("pandavideo", $pandavideo->id);

    $params = ["cm_id" => $cm->id];

    $extrawhere = " ";
    if ($userid > 0) {
        $extrawhere .= " AND user_id = :user_id";
        $params["user_id"] = $userid;
    }

    $sql = "SELECT user_id as userid, MAX(percent) as rawgrade
              FROM {pandavideo_view}
             WHERE cm_id = :cm_id {$extrawhere}
             GROUP BY user_id";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Function pandavideo_add_instance
 *
 * @param stdClass $pandavideo
 * @param mod_pandavideo_mod_form|null $mform
 *
 * @return bool|int
 * @throws dml_exception
 */
function pandavideo_add_instance(stdClass $pandavideo, $mform = null) {
    global $DB;

    $pandavideo->timemodified = time();
    $pandavideo->timecreated = time();

    $pandavideo->id = $DB->insert_record("pandavideo", $pandavideo);

    grades_util::grade_item_update($pandavideo);

    return $pandavideo->id;
}

/**
 * function pandavideo_update_instance
 *
 * @param stdClass $pandavideo
 * @param mod_pandavideo_mod_form|null $mform
 *
 * @return bool
 * @throws dml_exception
 */
function pandavideo_update_instance(stdClass $pandavideo, $mform = null) {
    global $DB;

    $pandavideo->timemodified = time();
    $pandavideo->id = $pandavideo->instance;

    $result = $DB->update_record("pandavideo", $pandavideo);

    grades_util::grade_item_update($pandavideo);

    return $result;
}

/**
 * function pandavideo_delete_instance
 *
 * @param int $id
 *
 * @return bool
 * @throws dml_exception
 * @throws coding_exception
 */
function pandavideo_delete_instance($id) {
    global $DB;

    if (!$pandavideo = $DB->get_record("pandavideo", ["id" => $id])) {
        return false;
    }

    $fs = get_file_storage();
    $cm = get_coursemodule_from_id("pandavideo", $pandavideo->id);
    if ($cm) {
        $files = $fs->get_area_files(
            context_module::instance($cm->id)->id,
            "mod_pandavideo",
            "content",
            $pandavideo->id,
            "sortorder DESC, id ASC",
            false
        );

        foreach ($files as $file) {
            $file->delete();
        }
    }
    $DB->delete_records("pandavideo", ["id" => $pandavideo->id]);
    $DB->delete_records("pandavideo_view", ["cm_id" => $cm->id]);

    return true;
}

/**
 * function pandavideo_user_outline
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $pandavideo
 *
 * @return stdClass
 */
function pandavideo_user_outline($course, $user, $mod, $pandavideo) {
    $return = new stdClass();
    $return->time = 0;
    $return->info = "";
    return $return;
}

/**
 * function pandavideo_user_complete
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $pandavideo
 *
 * @throws coding_exception
 * @throws dml_exception
 */
function pandavideo_user_complete($course, $user, $mod, $pandavideo) {
    global $DB;

    $sql = "SELECT sv.user_id, sv.currenttime, sv.duration, sv.percent, sv.timecreated, sv.timemodified, sv.videomap,
                   u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.email
              FROM {pandavideo_view} sv
              JOIN {user} u ON u.id = sv.user_id
             WHERE sv.cm_id   = :cm_id
               AND sv.user_id = :user_id
               AND percent    > 0
          ORDER BY sv.timecreated ASC";
    $param = [
        "cm_id" => $mod->id,
        "user_id" => $user->id,
    ];
    if ($registros = $DB->get_records_sql($sql, $param)) {
        echo "<table><tr>";
        echo "      <th>" . get_string("report_userid", "mod_pandavideo") . "</th>";
        echo "      <th>" . get_string("report_fullname", "mod_pandavideo") . "</th>";
        echo "      <th>" . get_string("report_email", "mod_pandavideo") . "</th>";
        echo "      <th>" . get_string("report_time", "mod_pandavideo") . "</th>";
        echo "      <th>" . get_string("report_duration", "mod_pandavideo") . "</th>";
        echo "      <th>" . get_string("report_percentage", "mod_pandavideo") . "</th>";
        echo "      <th>" . get_string("report_started", "mod_pandavideo") . "</th>";
        echo "      <th>" . get_string("report_finished", "mod_pandavideo") . "</th>";
        echo "  </tr>";
        foreach ($registros as $registro) {
            echo "<tr>";
            echo "  <td>" . $registro->user_id . "</td>";
            echo "  <td>" . fullname($registro) . "</td>";
            echo "  <td>" . $registro->email . "</td>";
            echo "  <td>" . pandavideo_format_time($registro->currenttime) . "</td>";
            echo "  <td>" . pandavideo_format_time($registro->duration) . "</td>";
            echo "  <td>" . $registro->percent . "%</td>";
            echo "  <td>" . userdate($registro->timecreated) . "</td>";
            echo "  <td>" . userdate($registro->timemodified) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        print_string("no_data", "pandavideo");
    }
}

/**
 * Function pandavideo_format_time
 *
 * @param $time
 *
 * @return string
 */
function pandavideo_format_time($time) {
    if ($time < 60) {
        return "00:00:{$time}";
    } else {
        $horas = "";
        $minutos = floor($time / 60);
        $segundos = ($time % 60);

        if ($minutos > 59) {
            $horas = floor($minutos / 60);
            $minutos = ($minutos % 60);
        }

        $horas = substr("00{$horas}", -2);
        $minutos = substr("00{$minutos}", -2);
        $segundos = substr("00{$segundos}", -2);
        return "{$horas}:{$minutos}:{$segundos}";
    }
}

/**
 * function pandavideo_get_coursemodule_info
 *
 * @param stdClass $coursemodule
 *
 * @return cached_cm_info
 * @throws dml_exception
 */
function pandavideo_get_coursemodule_info($coursemodule) {
    global $DB;

    $pandavideo = $DB->get_record(
        "pandavideo",
        ["id" => $coursemodule->instance],
        "id, name, pandaurl, intro, introformat, completionpercent"
    );

    $info = new cached_cm_info();
    if ($pandavideo) {
        $info->name = $pandavideo->name;
    }

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro("pandavideo", $pandavideo, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata["customcompletionrules"]["completionpercent"] = $pandavideo->completionpercent;
    }

    return $info;
}

/**
 * Function pandavideo_extend_settings_navigation
 *
 * @param settings_navigation $settings
 * @param navigation_node $pandavideonode
 *
 * @throws Exception
 */
function pandavideo_extend_settings_navigation($settings, $pandavideonode) {
    global $PAGE;

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $pandavideonode->get_children_key_list();
    $beforekey = null;
    $i = array_search("modedit", $keys);
    if ($i === false && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else {
        if (array_key_exists($i + 1, $keys)) {
            $beforekey = $keys[$i + 1];
        }
    }

    if (has_capability("moodle/course:manageactivities", $PAGE->cm->context)) {
        $node = navigation_node::create(
            get_string("report", "mod_pandavideo"),
            new moodle_url("/mod/pandavideo/report.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            "mod_pandavideo_report",
            new pix_icon("i/report", "")
        );
        $pandavideonode->add_node($node, $beforekey);
    }
}

/**
 * Function pandavideo_extend_navigation_course
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 *
 * @throws Exception
 */
function pandavideo_extend_navigation_course($navigation, $course, $context) {
    $node = $navigation->get("coursereports");
    if ($node && has_capability("mod/pandavideo:view_report", $context)) {
        $url = new moodle_url("/mod/pandavideo/reports.php", ["course" => $course->id]);
        $node->add(
            get_string("pluginname", "pandavideo"),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon("i/report", "")
        );
    }
}

/**
 * Serve the files from the pandavideo file areas
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param context $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 *
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 * @throws coding_exception
 * @throws moodle_exception
 * @throws require_login_exception
 */
function pandavideo_pluginfile($course, $cm, context $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($filearea == "thumb") {
        $url = urldecode($args[0]);
        header("Location: {$url}");
        die();
    }

    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel != CONTEXT_MODULE) {
        $filepath = $args[0];
        $itemid = $args[1];
        $filename = $args[2];

        $fs = get_file_storage();

        $file = $fs->get_file($context->id, "user", $filearea, $itemid, "/{$filepath}", $filename);
        if ($file) {
            send_stored_file($file, 86400, 0, $forcedownload, $options);
            return true;
        }
    }

    // Make sure the user is logged in and has access to the module
    // (plugins that are not course modules should leave out the "cm" part).
    require_login($course, true, $cm);

    // Check the relevant capabilities - these may vary depending on the filearea being accessed.
    if (!has_capability("mod/pandavideo:view", $context)) {
        return false;
    }

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.

    // Use the itemid to retrieve any relevant data records and perform any security checks to see if the
    // user really does have access to the file in question.

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        // Variable $args is empty => the path is "/".
        $filepath = "/";
    } else {
        // Variable $args contains elements of the filepath.
        $filepath = "/" . implode("/", $args) . "/";
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, "mod_pandavideo", $filearea, $itemid, $filepath, $filename);
    if ($file) {
        send_stored_file($file, 86400, 0, $forcedownload, $options);
        return true;
    }
    return false;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata["customcompletionrules"]
 *
 * @return array $descriptions the array of descriptions for the custom rules.
 * @throws coding_exception
 */
function mod_pandavideo_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata["customcompletionrules"]) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    $completionpercent = $cm->customdata["customcompletionrules"]["completionpercent"] ?? 0;
    $descriptions[] = get_string("completionpercent_desc", "mod_pandavideo", $completionpercent);
    return $descriptions;
}

/**
 * Sets the automatic completion state for this database item based on the
 * count of on its entries.
 *
 * @param object $data The data object for this activity
 * @param object $course Course
 * @param object $cm course-module
 *
 * @throws moodle_exception
 * @since Moodle 3.3
 *
 */
function pandavideo_update_completion_state($data, $course, $cm) {
    // If completion option is enabled, evaluate it and return true/false.
    $completion = new completion_info($course);
    if ($data->completionpercent && $completion->is_enabled($cm)) {
        $numentries = data_numentries($data);
        // Check the number of entries required against the number of entries already made.
        if ($numentries >= $data->completionpercent) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        } else {
            $completion->update_state($cm, COMPLETION_INCOMPLETE);
        }
    }
}

/**
 * Obtains the automatic completion state for this database item based on any conditions
 * on its settings. The call for this is in completion lib where the modulename is appended
 * to the function name. This is why there are unused parameters.
 *
 * @param stdClass $course Course
 * @param cm_info|stdClass $cm course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 *
 * @return bool True if completed, false if not, $type if conditions not set.
 * @throws dml_exception
 * @deprecated since Moodle 3.11
 * @todo       MDL-71196 Final deprecation in Moodle 4.3
 * @see        \mod_data\completion\custom_completion
 * @since      Moodle 3.3
 *
 */
function pandavideo_get_completion_state($course, $cm, $userid, $type) {
    global $DB, $PAGE;

    // No need to call debugging here. Deprecation debugging notice already being called in \completion_info::internal_get_state().

    $result = $type; // Default return value
    // Get data details.
    if (isset($PAGE->cm->id) && $PAGE->cm->id == $cm->id) {
        $data = $PAGE->activityrecord;
    } else {
        $data = $DB->get_record("pandavideo", ["id" => $cm->instance], "*", MUST_EXIST);
    }
    // If completion option is enabled, evaluate it and return true/false.
    if ($data->completionpercent) {
        $numentries = 10;

        // Check the number of entries required against the number of entries already made.
        if ($numentries >= $data->completionpercent) {
            $result = true;
        } else {
            $result = false;
        }
    }
    return $result;
}
