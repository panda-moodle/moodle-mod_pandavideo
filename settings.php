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

    $setting = new admin_setting_configcheckbox(
        "pandavideo/showvideomap",
        get_string("settings_showvideomap", "mod_pandavideo"),
        get_string("settings_showvideomap_desc", "mod_pandavideo"),
        1);
    $settings->add($setting);

    // Panda Token.
    $setting = new admin_setting_heading("pandavideo/token",
        get_string("settings_token_title", "mod_pandavideo"), "");
    $settings->add($setting);

    $setting = new admin_setting_configtext(
        "pandavideo/panda_token",
        get_string("settings_panda_token", "pandavideo"),
        get_string("settings_panda_tokendesc", "pandavideo"),
        "", PARAM_TEXT);
    $settings->add($setting);

    // Panda DRM.
    $setting = new admin_setting_heading("pandavideo/drm",
        get_string("settings_drm_title", "mod_pandavideo"), "");
    $settings->add($setting);

    $setting = new admin_setting_configtext(
        "pandavideo/drm_group_id",
        get_string("settings_drm_group_id", "pandavideo"),
        get_string("settings_drm_group_iddesc", "pandavideo"),
        "", PARAM_TEXT);
    $settings->add($setting);

    $setting = new admin_setting_configtext(
        "pandavideo/drm_secret",
        get_string("settings_drm_secret", "pandavideo"),
        get_string("settings_drm_secretdesc", "pandavideo"),
        "", PARAM_TEXT);
    $settings->add($setting);

    $securityfield = [
        "id" => get_string("settings_safety_id", "pandavideo"),
        "email" => get_string("settings_safety_email", "pandavideo"),
        "idnumber" => get_string("settings_safety_idnumber", "pandavideo"),
    ];
    $settings->add(new admin_setting_configselect("pandavideo/safety",
        get_string("settings_safety_title", "pandavideo"),
        get_string("settings_safety_desc",  "pandavideo"), "id",
        $securityfield
    ));
}
