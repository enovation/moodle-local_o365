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
 * @author  2018 Enovation
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_o365\webservices;


/**
 * Get a list of absent students for teacher.
 */
class read_absent_students extends \external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function absent_students_read_parameters() {
        return new \external_function_parameters([
            'limitnumber' => new \external_value(
                PARAM_INT,
                'maximum number of returned participants',
                VALUE_DEFAULT,
                10
            ),
        ]);
    }

    /**
     * Returns the absent students in last month.
     *
     * @param int $limitnumber An optional number which lets to set the maximum nuber of returned records
     * @return An array of assignments and warnings.
     */
    public static function absent_students_read($limitnumber = 10) {
        global $USER, $DB;
        $usersarray = [];
        $warnings = [];
        $courses = [];
        $params = self::validate_parameters(
            self::absent_students_read_parameters(),
            array(
                'limitnumber' => $limitnumber,
            )
        );
        if(!is_siteadmin()){
            $courses = array_keys(enrol_get_users_courses($USER->id, true, 'id'));
            $teachercourses = [];
            foreach($courses as $course){
                $context = \context_course::instance($course, IGNORE_MISSING);
                if (!has_capability('moodle/grade:edit', $context)) {
                    continue;
                }
                $teachercourses[] = $course;
            }
            $courses = $teachercourses;
        }
        if(!empty($courses) ||  is_siteadmin()){
            $monthstart = mktime(0, 0, 0, date("n"), 1);
            $userssql = 'SELECT u.id, u.username, u.firstname, u.lastname, u.lastaccess FROM {user} u ';
            $sqlparams = [];
            if(!empty($courses)){
                $coursessqlparam = join(',', $courses);
                $userssql .= " JOIN {role_assignments} ra ON u.id = ra.userid 
                               JOIN {role} r ON ra.roleid = r.id AND r.shortname = 'student'
                               JOIN {context} c ON c.id = ra.contextid AND c.contextlevel = 50 AND c.instanceid IN ($coursessqlparam)
                               ";
            }
            $userssql .= ' WHERE u.lastaccess < :monthstart AND u.deleted = 0 AND u.suspended = 0';
            $sqlparams['monthstart'] = $monthstart;
            $userssql .= ' ORDER BY u.lastaccess DESC';
            if (!empty($params['limitnumber'])){
                $userssql .= ' LIMIT '.$params['limitnumber'];
            }
            $userslist = $DB->get_records_sql($userssql, $sqlparams);
        }

        if (empty($userslist)) {
            $warnings[] = array(
                'item' => 'users',
                'itemid' => 0,
                'warningcode' => '1',
                'message' => 'No  absent users found'
            );
        } else {
            foreach ($userslist as $user) {
                $usersarray[] = array(
                    'username' => $user->username,
                    'fullname' => $user->firstname.' '.$user->lastname,
                    'lastaccess' => $user->lastaccess,
                );
            }
        }

        $result = array(
            'users' => $usersarray,
            'warnings' => $warnings
        );

        return $result;
    }


    /**
     * Creates absent user external_single_structure
     *
     * @return external_single_structure
     */
    private static function get_absent_students_structure() {
        return new \external_single_structure(
            array(
                'username' => new \external_value(PARAM_TEXT, 'participant username'),
                'fullname' => new \external_value(PARAM_TEXT, 'participant fullname'),
                'lastaccess' => new \external_value(PARAM_INT, 'grade date'),
            ), 'user grade information object'
        );
    }

    /**
     * Describes the return value for get_absent_students
     *
     * @return external_single_structure
     */
    public static function absent_students_read_returns() {
        return new \external_single_structure(
            array(
                'users' => new \external_multiple_structure(self::get_absent_students_structure(), 'list of absent users', VALUE_DEFAULT, []),
                'warnings'  => new \external_warnings('item can be \'assignments\' (errorcode 1 or 2)',
                    'When item is "users" then itemid is by default 0',
                    'errorcode can be 1 (no absent users found)')
            )
        );
    }

}
