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
 * @package local_o365
 * @author Lai Wei <lai.wei@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2018 onwards Microsoft, Inc. (http://microsoft.com/)
 */

namespace local_o365\feature\teams;

class utils {
    /**
     * Determine whether course teams are enabled or not.
     *
     * @return bool
     * @throws \dml_exception
     */
    public static function is_enabled() {
        $createteams = get_config('local_o365', 'createteams');
        return ($createteams === 'oncustom' || $createteams === 'onall') ? true : false;
    }

    /**
     * Get an array of enabled courses.
     *
     * @return array|bool
     * @throws \dml_exception
     */
    public static function get_enabled_courses() {
        $createteams = get_config('local_o365', 'createteams');
        if ($createteams === 'onall') {
            return true;
        } else if ($createteams === 'oncustom') {
            $coursesenabled = get_config('local_o365', 'teamcustom');
            $coursesenabled = @json_decode($coursesenabled, true);
            if (!empty($coursesenabled) && is_array($coursesenabled)) {
                return array_keys($coursesenabled);
            }
        }
        return [];
    }

    /**
     * Determine whether a course is team-enabled.
     *
     * @param $courseid
     *
     * @return bool
     * @throws \dml_exception
     */
    public static function course_is_team_enabled($courseid) {
        $createteams = get_config('local_o365', 'createteams');
        if ($createteams === 'onall') {
            return true;
        } else if ($createteams === 'oncustom') {
            $coursesenabled = get_config('local_o365', 'teamcustom');
            $coursesenabled = @json_decode($coursesenabled, true);
            if (!empty($coursesenabled) && is_array($coursesenabled) && isset($coursesenabled[$courseid])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Change whether teams are enabled for a course.
     *
     * @param $courseid
     * @param bool $enabled
     *
     * @throws \dml_exception
     */
    public static function set_course_team_enabled($courseid, $enabled = true) {
        $teamconfig = get_config('local_o365', 'teamcustom');
        $teamconfig = @json_decode($teamconfig, true);

        if (empty($teamconfig) || !is_array($teamconfig)) {
            $teamconfig = [];
        }

        if ($enabled === true) {
            $teamconfig[$courseid] = $enabled;
        } else {
            if (isset($teamconfig[$courseid])) {
                unset($teamconfig[$courseid]);
                //static::delete_team($courseid);
            }
        }
        set_config('teamcustom', json_encode($teamconfig), 'local_o365');
    }
}
