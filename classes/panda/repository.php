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
 * repository for Panda.
 *
 * @package   mod_pandavideo
 * @copyright 2025 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pandavideo\panda;

use curl;
use Exception;
use stdClass;

/**
 * Class Panda repository
 *
 * @package mod_pandavideo
 */
class repository {

    /** @var string */
    private static string $baseurl = "https://api-v2.pandavideo.com.br";

    /** @var string */
    private static string $basedataurl = "https://data.pandavideo.com";

    /**
     * get_video_id
     *
     * @param string $url
     * @return string
     */
    public static function get_video_id($url) {
        if (preg_match('/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/', $url, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * oEmbed function
     *
     * @param $pandaurl
     * @return mixed
     * @throws Exception
     */
    public static function oembed($pandaurl) {
        $videoid = self::get_video_id($pandaurl);
        if (!$videoid) {
            throw new Exception("VideoId not found");
        }
        $dashboard = urlencode("https://dashboard.pandavideo.com.br/videos/{$videoid}");
        $endpoint = "/oembed?url={$dashboard}";
        $response = self::http_get($endpoint, self::$baseurl, true);
        return $response;
    }

    /**
     * List videos
     *
     * @param $page
     * @param $limit
     * @param $title
     * @return object
     * @throws Exception
     */
    public static function get_videos($page = 0, $limit = 100, $title = "") {
        $params = [];
        if ($page) {
            $params[] = "page={$page}";
        }
        if ($limit) {
            $params[] = "limit={$limit}";
        }
        if ($title) {
            $params[] = "title=" . urlencode($title);
        }
        if ($params) {
            $endpoint = "/videos?" . implode("&", $params);
        } else {
            $endpoint = "/videos";
        }

        $response = self::http_get($endpoint, self::$baseurl);
        return $response;
    }

    /**
     * get_folders
     *
     * @return mixed
     * @throws Exception
     */
    public static function get_folders() {
        $endpoint = "/folders";
        $response = self::http_get($endpoint, self::$baseurl);
        return $response;
    }

    /**
     * Get video properties
     *
     * @param string $pandaurl
     * @return stdClass
     * @throws Exception
     */
    public static function get_video_properties($pandaurl) {
        $videoid = self::get_video_id($pandaurl);
        if (!$videoid) {
            throw new Exception("VideoId not found");
        }

        $endpoint = "/videos/{$videoid}";
        $response = self::http_get($endpoint, self::$baseurl, true);
        return $response;
    }

    /**
     * Get analytics from video
     *
     * @param $videoid
     * @return mixed
     * @throws Exception
     */
    public static function get_analytics_from_video($videoid) {
        $endpoint = "/general/{$videoid}";
        $response = self::http_get($endpoint, self::$basedataurl, true);
        return $response;
    }

    /**
     * Get Bandwidth by Video
     *
     * @param $videoid
     * @param $startdate
     * @param $enddate
     * @return mixed
     * @throws Exception
     */
    public static function get_bandwidth_by_video($videoid, $startdate, $enddate) {
        $endpoint = "/analytics/traffic";
        $response = self::http_get($endpoint, self::$baseurl, true);
        return $response;
    }

    /**
     * http_get
     *
     * @param string $endpoint
     * @param $baseurl
     * @param bool $savecache
     * @return mixed
     * @throws Exception
     */
    private static function http_get($endpoint, $baseurl, $savecache = false) {
        $cache = \cache::make("mod_pandavideo", "pandavideo_api_get");
        $cachekey = "mod_pandavideo_{$endpoint}_{$baseurl}";
        if ($savecache && $cache->has($cachekey)) {
            return json_decode($cache->get($cachekey));
        } else {
            $config = get_config("pandavideo");

            if (!isset($config->panda_token[20])) {
                throw new Exception("Token is missing: " . get_string("token_desc", "mod_pandavideo"));
            }

            $url = self::$baseurl . $endpoint;

            $curl = new curl();
            $curl->setopt([
                "CURLOPT_HTTPHEADER" => [
                    "accept: application/json",
                    "Authorization: {$config->panda_token}",
                ],
            ]);
            $body = $curl->get($url);

            if ($curl->error) {
                throw new Exception("Unexpected error.");
            }

            $status = $curl->info["http_code"];
            switch ($status) {
                case 200:
                    if ($savecache) {
                        $cache->set($cachekey, $body);
                    }
                    return json_decode($body);
                case 400:
                    throw new Exception("Panda error 400: Bad request. Check the provided parameters.");
                case 401:
                    throw new Exception("Panda error 401: Unauthorized. Authentication failed or not provided.");
                case 404:
                    throw new Exception("Panda error 404: Not found. Videos or the API were not found.");
                case 500:
                    throw new Exception("Panda error 500: Internal server error. Please try again later.");
                default:
                    throw new Exception("Panda error {$status}: Unexpected error.");
            }
        }
    }

    /**
     * getplayer
     *
     * @param string $pandaurl
     * @param object $pandavideoview
     * @return string
     * @throws Exception
     */
    public static function getplayer(string $pandaurl, $pandavideoview = null) {
        global $OUTPUT;

        $config = get_config("pandavideo");

        if (!isset($config->panda_token[20])) {
            try {
                $pandavideo = self::oembed($pandaurl);
            } catch (Exception $e) {
                return "oEmbed Error: {$e->getMessage()}";
            }
            $pandavideo->video_player = preg_replace('/.*src="(.*?)".*/', "$1", $pandavideo->html);
        } else {
            try {
                $pandavideo = self::get_video_properties($pandaurl);
            } catch (Exception $e) {
                return "Video properties Error: {$e->getMessage()}";
            }
        }

        $mustachecontext = [
            "video_player" => $pandavideo->video_player,
            "pandavideoview_id" => 0,
            "ratio" => max(($pandavideo->height / $pandavideo->width) * 100, 20),
            "showvideomap" => false,
            "videomap_data" => "[]",
        ];
        if ($pandavideoview) {
            $mustachecontext["pandavideoview_id"] = $pandavideoview->id;
            $mustachecontext["pandavideoview_currenttime"] = intval($pandavideoview->currenttime);
            $mustachecontext["videomap_data"] = json_decode($pandavideoview->videomap) ? $pandavideoview->videomap : "[]";
        }

        return $OUTPUT->render_from_template("mod_pandavideo/embed", $mustachecontext);
    }
}
