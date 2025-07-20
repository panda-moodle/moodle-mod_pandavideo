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
 * setting file
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die;

global $ADMIN, $CFG;
if ($ADMIN->fulltree) {

    require_once("{$CFG->libdir}/resourcelib.php");

    $title = get_string("token", "mod_pandavideo");
    $description = get_string("token_desc", "mod_pandavideo");
    $setting = new admin_setting_configtext("pandavideo/panda_token",
        $title, $description, "panda-xxxxxxxx", PARAM_TEXT);
    $settings->add($setting);

    $title = get_string("showvideomap", "mod_pandavideo");
    $description = get_string("showvideomap_desc", "mod_pandavideo");
    $settings->add(new admin_setting_configcheckbox("pandavideo/showvideomap",
        $title, $description, 1));
}
