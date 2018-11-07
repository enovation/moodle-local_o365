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
 * Get a list of students with the name sent and their last login time.
 */
class read_student_last_login extends \external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function student_last_login_read_parameters() {
        return new \external_function_parameters([
            'name' => new \external_value(
                PARAM_TEXT,
                'user name',
                VALUE_REQUIRED
            )
        ]);
    }

    /**
     * Returns the students with the searched name and last login time.
     *
     * @param text name Required param based on which the user is looked up in database
     * @return An array of students and warnings.
     */
    public static function student_last_login_read($name) {
        global $USER, $DB, $PAGE;
        $studentsdetails = [];
        $warnings = [];
        $courses = [];
        $params = self::validate_parameters(
            self::student_last_login_read_parameters(),
            array(
                'name' => $name
            )
        );

        $lastloginsql = "SELECT u.id, u.username, CONCAT(u.firstname, ' ', u.lastname) as fullname, u.lastaccess FROM {user} u
                    WHERE CONCAT(u.firstname, ' ', u.lastname) LIKE '%{$params['name']}%' AND u.suspended = 0 AND u.deleted = 0";

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
            if(!empty($courses)){
                $userssql = 'SELECT u.id FROM {user} u ';
                $coursessqlparam = join(',', $courses);
                $userssql .= " JOIN {role_assignments} ra ON u.id = ra.userid 
                               JOIN {role} r ON ra.roleid = r.id AND r.shortname = 'student'
                               JOIN {context} c ON c.id = ra.contextid AND c.contextlevel = 50 AND c.instanceid IN ($coursessqlparam)
                               ";
                $userssql .= ' WHERE u.deleted = 0 AND u.suspended = 0';

                $userslist = $DB->get_fieldset_sql($userssql);
                $userssqlparam = join(',', $userslist);
                $lastloginsql .= ' AND u.id IN ('.$userssqlparam.')';
            }else{
                $lastloginsql .= ' AND u.id IN ('.$USER->id.')';
            }
        }
        $lastloginsql .= ' ORDER BY u.lastaccess DESC';

        $users = $DB->get_records_sql($lastloginsql);

        if (empty($users)) {
            $warnings[] = array(
                'item' => 'users',
                'itemid' => 0,
                'warningcode' => '1',
                'message' => 'No  user with such name found'
            );
        }else{
            foreach($users as $user){
                $userpicture = new \user_picture($user);
                $userpicture->size = 1;
                $pictureurl = $userpicture->get_url($PAGE)->out(false);
                $studentsdetails[] = [
                    'username' => $user->username,
                    'fullname' => $user->fullname,
                    'picture' => $pictureurl,
                    'lastlogin' => $user->lastaccess,
                ];
            }
        }

        $result = array(
            'students' => $studentsdetails,
            'warnings' => $warnings
        );

        return $result;
    }


    /**
     * Creates absent user external_single_structure
     *
     * @return external_single_structure
     */
    private static function get_student_last_login_structure() {
        return new \external_single_structure(
            array(
                'username' => new \external_value(PARAM_TEXT, 'participant username'),
                'fullname' => new \external_value(PARAM_TEXT, 'participant fullname'),
                'picture' => new \external_value(PARAM_URL, 'participant picture url'),
                'lastlogin' => new \external_value(PARAM_INT, 'last login date'),
            ), 'user last login details'
        );
    }

    /**
     * Describes the return value for get_student_last_login
     *
     * @return external_single_structure
     */
    public static function student_last_login_read_returns() {
        return new \external_single_structure(
            array(
                'students' => new \external_multiple_structure(self::get_student_last_login_structure(), 'student last login details', VALUE_DEFAULT, []),
                'warnings'  => new \external_warnings('item can be \'users\' (errorcode 1)',
                    'When item is "users" then itemid is by default 0',
                    'errorcode can be 1 (no user with such name found)')
            )
        );
    }

}
