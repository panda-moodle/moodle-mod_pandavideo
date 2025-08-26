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
 * Mod Form file
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_supervideo\form\supervideo_filepicker;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->dirroot}/course/moodleform_mod.php");

/**
 * class mod_pandavideo_mod_for
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_pandavideo_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     *
     * @throws coding_exception
     */
    public function definition() {
        global $CFG, $COURSE;

        // Register Element Type supervideo_filepicker.
        MoodleQuickForm::registerElementType(
            "supervideo_filepicker",
            "{$CFG->dirroot}/mod/supervideo/classes/form/supervideo_filepicker.php",
            supervideo_filepicker::class
        );

        $mform = $this->_form;
        $mform->updateAttributes(["enctype" => "multipart/form-data"]);

        $mform->addElement("header", "general", get_string("general", "form"));

        $mform->addElement("text", "name", get_string("name"), ["size" => "48"], []);
        $mform->setType("name", !empty($CFG->formatstringstriptags) ? PARAM_TEXT : PARAM_CLEANHTML);
        $mform->addRule("name", null, "required", null, "client");
        $mform->addRule("name", get_string("maximumchars", "", 255), "maxlength", 255, "client");

        $filemanageroptions = [
            "accepted_types" => ["video/pandavideo"],
            "maxbytes" => -1,
            "return_types" => 1,
        ];
        $title = get_string("pandaurl", "mod_pandavideo");
        $mform->addElement("supervideo_filepicker", "pandaurl", $title, null, $filemanageroptions);
        $mform->setType("pandaurl", PARAM_TEXT);
        $mform->addHelpButton("pandaurl", "pandaurl", "mod_pandavideo");

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Grade Element.
        $mform->addElement("header", "modstandardgrade", get_string("modgrade", "grades"));

        $values = [
            0 => get_string("grade_approval_0", "mod_pandavideo"),
            1 => get_string("grade_approval_1", "mod_pandavideo"),
        ];
        $mform->addElement("select", "grade_approval", get_string("grade_approval", "mod_pandavideo"), $values);

        $mform->addElement(
            "select",
            "gradecat",
            get_string("gradecategoryonmodform", "grades"),
            grade_get_categories_menu($COURSE->id, false)
        );
        $mform->addHelpButton("gradecat", "gradecategoryonmodform", "grades");
        $mform->hideIf("gradecat", "grade_approval", "eq", "0");

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        $mform->hideIf("completionusegrade", "grade_approval", "eq", "0");
        $mform->hideIf("completionpassgrade", "grade_approval", "eq", "0");

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

        $btn = false;
        if (!($this->_cm && $this->_cm->instance)) {
            $course = $this->optional_param("course", 0, PARAM_INT);
            $section = $this->optional_param("section", false, PARAM_INT);
            if ($course && $section !== false) {
                $btn = "course={$course}&section={$section}&sesskey=" . sesskey();
            }
        }
    }

    /**
     * Set up the completion checkbox which is not part of standard data.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        $defaultvalues["completionpercentenabled"] = !empty($defaultvalues["completionpercent"]) ? 1 : 0;
        if (empty($defaultvalues["completionpercent"])) {
            $defaultvalues["completionpercent"] = 1;
        }
    }

    /**
     * Allows modules to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionpercentenabled) || !$autocompletion) {
                $data->completionpercent = 0;
            }
        }
    }

    /**
     * Function add_completion_rules_oold
     *
     * @return array
     * @throws coding_exception
     */
    public function add_completion_rules_oold() {
        $mform =& $this->_form;

        $mform->addElement("text", "completionpercent", get_string("completionpercent", "mod_pandavideo"), ["size" => 4]);
        $mform->addHelpButton("completionpercent", "completionpercent", "mod_pandavideo");
        $mform->setType("completionpercent", PARAM_INT);

        return ["completionpercent"];
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     *
     * @return array Array of string IDs of added items, empty array if none
     * @throws coding_exception
     */
    public function add_completion_rules() {
        $mform = &$this->_form;
        $group = [
            $mform->createElement(
                "checkbox",
                "completionpercentenabled",
                "",
                get_string("completionpercent_label", "mod_pandavideo")
            ),
            $mform->createElement(
                "text",
                "completionpercent",
                get_string("completionpercent_label", "mod_pandavideo"),
                ["size" => "2"]
            ),
            $mform->createElement("html", "%"),
        ];

        $mform->addGroup(
            $group,
            "completionpercentgroup",
            get_string("completionpercent", "mod_pandavideo"),
            [" "],
            false
        );
        $mform->disabledIf("completionpercent", "completionpercentenabled", "notchecked");
        $mform->setDefault("completionpercent", 0);
        $mform->setType("completionpercent", PARAM_INT);
        return ["completionpercentgroup"];
    }

    /**
     * Function completion_rule_enabled
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return ($data["completionpercent"] > 0);
    }

    /**
     * Function validation
     *
     * @param $data
     * @param $files
     * @return array
     * @throws coding_exception
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!isset($data["pandaurl"]) || empty($data["pandaurl"])) {
            $errors["pandaurl"] = get_string("required");
        }

        if (isset($data["completionpercent"]) && $data["completionpercent"] != "") {
            $data["completionpercent"] = intval($data["completionpercent"]);
            if ($data["completionpercent"] < 1) {
                $data["completionpercent"] = "";
            }
            if ($data["completionpercent"] > 100) {
                $errors["completionpercent"] = get_string("completionpercent_error", "mod_pandavideo");
            }
        }

        if (isset($data["gradepass"]) && $data["gradepass"] != "") {
            $data["gradepass"] = intval($data["gradepass"]);
            if ($data["gradepass"] < 1) {
                $data["gradepass"] = "";
            }
            if ($data["gradepass"] > 100) {
                $errors["gradepass"] = get_string("completionpercent_error", "mod_pandavideo");
            }
        }

        return $errors;
    }
}
