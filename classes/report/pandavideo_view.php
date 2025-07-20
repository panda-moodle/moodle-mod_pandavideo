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
 * Panda Video View implementation for mod_pandavideo.
 *
 * @package   mod_pandavideo
 * @copyright 2025 Panda Video {@link https://pandavideo.com.br}
 * @author    2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pandavideo\report;

use Exception;
use html_writer;
use moodle_url;

/**
 * Class pandavideo_view
 *
 * @package mod_pandavideo\report
 */
class pandavideo_view extends \table_sql {

    /**
     * @var int
     */
    public $cmid = 0;
    /**
     * @var int
     */
    public $userid = 0;

    /**
     * pandavideo_view constructor.
     *
     * @param $uniqueid
     * @param $cmid
     * @param $userid
     * @param $pandavideo
     * @throws \Exception
     */
    public function __construct($uniqueid, $cmid, $userid, $pandavideo) {
        global $DB;

        parent::__construct($uniqueid);

        $this->cmid = $cmid;
        $this->userid = $userid;

        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);

        $download = optional_param("download", null, PARAM_ALPHA);
        if ($download) {
            raise_memory_limit(MEMORY_EXTRA);
            if ($this->userid) {
                $user = $DB->get_record("user", ["id" => $this->userid]);
                $filename = get_string("report_filename", "mod_pandavideo", fullname($user));
            } else {
                $geral = get_string("report_filename_geral", "mod_pandavideo");
                $filename = get_string("report_filename", "mod_pandavideo", $geral);
            }
            $this->is_downloading($download, $filename, $pandavideo->name);
        }

        if ($this->userid) {
            $columns = [
                "user_id",
                "fullname",
                "email",
                "currenttime",
                "duration",
                "percent",
                "videomap",
                "timecreated",
                "timemodified",
            ];
            $headers = [
                get_string("report_userid", "mod_pandavideo"),
                get_string("report_fullname", "mod_pandavideo"),
                get_string("report_email", "mod_pandavideo"),
                get_string("report_time", "mod_pandavideo"),
                get_string("report_duration", "mod_pandavideo"),
                get_string("report_percentage", "mod_pandavideo"),
                get_string("report_videomap", "mod_pandavideo"),
                get_string("report_started", "mod_pandavideo"),
                get_string("report_finished", "mod_pandavideo"),
            ];

            if ($this->is_downloading()) {
                unset($columns[6]);
                unset($headers[6]);
            }
        } else {
            $columns = [
                "user_id",
                "fullname",
                "email",
                "currenttime",
                "duration",
                "percent",
                "quantidade",
                "timecreated",
            ];
            $headers = [
                get_string("report_userid", "mod_pandavideo"),
                get_string("report_fullname", "mod_pandavideo"),
                get_string("report_email", "mod_pandavideo"),
                get_string("report_time", "mod_pandavideo"),
                get_string("report_duration", "mod_pandavideo"),
                get_string("report_percentage", "mod_pandavideo"),
                get_string("report_views", "mod_pandavideo"),
                get_string("report_watched", "mod_pandavideo"),
            ];

            if (!$this->is_downloading()) {
                $columns[] = "extra";
                $headers[] = "";
            }
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
    }

    /**
     * Fullname is treated as a special columname in tablelib and should always
     * be treated the same as the fullname of a user.
     *
     * @param object $linha the data from the db containing all fields
     * @return string contents of cell in column 'fullname', for this row.
     * @throws Exception
     */
    public function col_fullname($linha) {
        global $COURSE;

        $name = fullname($linha);
        if ($this->download) {
            return $name;
        }

        if ($COURSE->id == SITEID) {
            $profileurl = new moodle_url("/user/profile.php", ["id" => $linha->user_id]);
        } else {
            $profileurl = new moodle_url("/user/view.php",
                ["id" => $linha->user_id, "course" => $COURSE->id]);
        }
        return html_writer::link($profileurl, $name);
    }

    /**
     * Function col_currenttime
     *
     * @param $linha
     *
     * @return string
     */
    public function col_currenttime($linha) {
        $seconds = $linha->currenttime % 60;
        $minutes = (floor($linha->currenttime / 60)) % 60;
        $hours = floor($linha->currenttime / 3600);

        $hours = substr("0{$hours}", -2);
        $minutes = substr("0{$minutes}", -2);
        $seconds = substr("0{$seconds}", -2);
        return "{$hours}:{$minutes}:{$seconds}";
    }

    /**
     * Function col_duration
     *
     * @param $linha
     * @return string
     */
    public function col_duration($linha) {
        $seconds = $linha->duration % 60;
        $minutes = (floor($linha->duration / 60)) % 60;
        $hours = floor($linha->duration / 3600);

        $hours = substr("0{$hours}", -2);
        $minutes = substr("0{$minutes}", -2);
        $seconds = substr("0{$seconds}", -2);
        return "{$hours}:{$minutes}:{$seconds}";
    }

    /**
     * Function col_percent
     *
     * @param $linha
     * @return string
     */
    public function col_percent($linha) {
        return "{$linha->percent}%";
    }

    /**
     * Function col_videomap
     *
     * @param $linha
     * @return string
     */
    public function col_videomap($linha) {
        $htmlvideomap = "<div id='videomap-visualization' class='report'>";

        $videomaps = json_decode($linha->videomap);
        foreach ($videomaps as $id => $videomap) {
            if ($id == 0) {
                continue;
            }
            if ($videomap) {
                $htmlvideomap .= "<div id='videomap-visualization-" . $id . "' style='opacity:1'></div>";
            } else {
                $htmlvideomap .= "<div id='videomap-visualization-" . $id . "'></div>";
            }
        }
        $htmlvideomap .= "</div>";
        return $htmlvideomap;
    }

    /**
     * Function col_timecreated
     *
     * @param $linha
     * @return string
     */
    public function col_timecreated($linha) {
        return userdate($linha->timecreated);
    }

    /**
     * Function col_timemodified
     *
     * @param $linha
     * @return string
     */
    public function col_timemodified($linha) {
        return userdate($linha->timemodified);
    }

    /**
     * Function col_extra
     *
     * @param $linha
     * @return string
     * @throws Exception
     */
    public function col_extra($linha) {
        $profileurl = new \moodle_url("/mod/pandavideo/report.php", ["id" => $linha->cm_id, "u" => $linha->user_id]);
        return \html_writer::link($profileurl, get_string("report_all", "mod_pandavideo"));
    }

    /**
     * Function query_db
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @throws Exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $CFG;

        if ($CFG->dbtype == "pgsql") {
            $this->query_db_postgresql($pagesize, $useinitialsbar);
        } else {
            $this->query_db_default($pagesize, $useinitialsbar);
        }
    }

    /**
     * Function query_db_default
     *
     * @param $pagesize
     * @param bool $useinitialsbar
     * @throws Exception
     */
    private function query_db_default($pagesize, $useinitialsbar = true) {
        global $DB;

        $params = ["cm_id" => $this->cmid];

        $sqlwhere = $this->get_sql_where();
        $where = $sqlwhere[0] ? "AND {$sqlwhere[0]}" : "";
        $params = array_merge($params, $sqlwhere[1]);

        $order = $this->get_sort_for_table($this->uniqueid);
        if (!$order) {
            $order = "sv.user_id";
        }

        if ($this->userid) {
            $params["user_id"] = $this->userid;

            $this->sql = "
                  SELECT sv.user_id, sv.currenttime, sv.duration, sv.percent, sv.timecreated, sv.timemodified, sv.videomap,
                         u.firstname, u.lastname, u.email,
                         u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                    FROM {pandavideo_view} sv
                    JOIN {user} u ON u.id = sv.user_id
                   WHERE sv.cm_id   = :cm_id
                     AND sv.user_id = :user_id
                     AND percent    > 0
                         {$where}
                ORDER BY {$order}";

            if ($pagesize != -1) {
                $countsql = "
                     SELECT COUNT(*)
                       FROM (
                            SELECT COUNT(sv.id) AS cont
                             FROM {pandavideo_view} sv
                             JOIN {user} u ON u.id = sv.user_id
                            WHERE sv.cm_id   = :cm_id
                              AND sv.user_id = :user_id
                              AND percent    > 0
                                  {$where}
                       ) AS c";
                $total = $DB->get_field_sql($countsql, $params);
                $this->pagesize($pagesize, $total);
            } else {
                $this->pageable(false);
            }
        } else {
            $this->sql = "
                  SELECT sv.user_id, sv.cm_id, MAX(sv.currenttime) currenttime, MAX(sv.duration) duration,
                         MAX(sv.percent) percent, MAX(sv.timecreated) timecreated,
                         u.firstname, u.lastname, u.email,
                         (
                            SELECT COUNT(*)
                              FROM {pandavideo_view} sv1
                             WHERE sv1.cm_id = sv.cm_id
                               AND sv1.user_id = sv.user_id
                               AND sv1.percent > 0
                         ) AS quantidade,
                         u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                    FROM {pandavideo_view} sv
                    JOIN {user} u ON u.id = sv.user_id
                   WHERE sv.cm_id = :cm_id {$where}
                GROUP BY sv.user_id
                ORDER BY {$order}";

            if ($pagesize != -1) {
                $countsql = "
                     SELECT COUNT(*)
                       FROM (
                            SELECT COUNT(sv.id) AS cont
                              FROM {pandavideo_view} sv
                              JOIN {user} u ON u.id = sv.user_id
                             WHERE sv.cm_id = :cm_id {$where}
                          GROUP BY sv.user_id
                       ) AS c";
                $total = $DB->get_field_sql($countsql, $params);
                $this->pagesize($pagesize, $total);
            } else {
                $this->pageable(false);
            }
        }

        if ($useinitialsbar && !$this->is_downloading()) {
            $this->initialbars(true);
        }

        $this->rawdata = $DB->get_recordset_sql($this->sql, $params, $this->get_page_start(), $this->get_page_size());
    }

    /**
     * Function query_db_postgresql
     *
     * @param $pagesize
     * @param bool $useinitialsbar
     * @throws Exception
     */
    private function query_db_postgresql($pagesize, $useinitialsbar = true) {
        global $DB;

        $params = ["cm_id" => $this->cmid];

        $sqlwhere = $this->get_sql_where();
        $where = $sqlwhere[0] ? "AND {$sqlwhere[0]}" : "";
        $params = array_merge($params, $sqlwhere[1]);

        $order = $this->get_sort_for_table($this->uniqueid);
        if (!$order) {
            $order = "sv.user_id";
        }

        if ($this->userid) {
            $params["user_id"] = $this->userid;

            $this->sql = "
                  SELECT sv.user_id, sv.currenttime, sv.duration, sv.percent, sv.timecreated, sv.timemodified, sv.videomap,
                         u.firstname, u.lastname, u.email,
                         u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                    FROM {pandavideo_view} sv
                    JOIN {user} u ON u.id = sv.user_id
                   WHERE sv.cm_id   = :cm_id
                     AND sv.user_id = :user_id
                     AND percent    > 0
                         {$where}
                ORDER BY {$order}";

            if ($pagesize != -1) {
                $countsql = "
                      SELECT COUNT(*)
                       FROM (
                            SELECT COUNT(sv.id) AS cont
                             FROM {pandavideo_view} sv
                             JOIN {user} u ON u.id = sv.user_id
                            WHERE sv.cm_id   = :cm_id
                              AND sv.user_id = :user_id
                              AND percent    > 0
                                  {$where}
                       ) AS c";
                $total = $DB->get_field_sql($countsql, $params);
                $this->pagesize($pagesize, $total);
            } else {
                $this->pageable(false);
            }
        } else {
            $this->sql = "
                 SELECT sv.user_id,
                        sv.cm_id,
                        MAX(sv.currenttime) AS currenttime,
                        MAX(sv.duration) AS duration,
                        MAX(sv.percent) AS percent,
                        MAX(sv.timecreated) AS timecreated,
                        u.firstname,
                        u.lastname,
                        u.email,
                        (
                            SELECT COUNT(*)
                            FROM {pandavideo_view} sv1
                            WHERE sv1.cm_id = sv.cm_id
                            AND sv1.user_id = sv.user_id
                            AND sv1.percent > 0
                        ) AS quantidade,
                        u.firstnamephonetic,
                        u.lastnamephonetic,
                        u.middlename,
                        u.alternatename
                   FROM {pandavideo_view} sv
                   JOIN {user} u ON u.id = sv.user_id
                  WHERE sv.cm_id = :cm_id {$where}
               GROUP BY sv.user_id, sv.cm_id,
                        u.firstname, u.lastname, u.email,
                        u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
               ORDER BY {$order}";

            if ($pagesize != -1) {
                $countsql = "
                     SELECT COUNT(*)
                       FROM (
                            SELECT COUNT(sv.id) AS cont
                              FROM {pandavideo_view} sv
                              JOIN {user}             u ON u.id = sv.user_id
                             WHERE sv.cm_id = :cm_id {$where}
                          GROUP BY sv.user_id
                       ) AS c";
                $total = $DB->get_field_sql($countsql, $params);
                $this->pagesize($pagesize, $total);
            } else {
                $this->pageable(false);
            }
        }

        if ($useinitialsbar && !$this->is_downloading()) {
            $this->initialbars(true);
        }

        $this->rawdata = $DB->get_recordset_sql($this->sql, $params, $this->get_page_start(), $this->get_page_size());
    }
}
