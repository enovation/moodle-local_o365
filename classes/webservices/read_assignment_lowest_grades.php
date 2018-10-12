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

require_once($CFG->dirroot.'/mod/assign/locallib.php');


/**
 * Get a list of lowest grades in the latest graded assignment.
 */
class read_assignment_lowest_grades extends \external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function assignment_lowest_grades_read_parameters() {
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
     * Returns the structure of assignment and the lowest grades.
     *
     * @param int $limitnumber An optional number which lets to set the maximum nuber of returned records
     * @return An array of assignments and warnings.
     */
    public static function assignment_lowest_grades_read($limitnumber = 10) {
        global $USER, $DB;
        $gradesarray = [];
        $warnings = [];
        $params = self::validate_parameters(
            self::assignment_lowest_grades_read_parameters(),
            array(
                'limitnumber' => $limitnumber,
            )
        );

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
            $coursessqlparam = join(',', $courses);
            $sql = 'SELECT ag.assignment FROM {assign_grades} ag
                    JOIN {assign} a ON a.id = ag.assignment
                    WHERE a.course IN ('.$coursessqlparam.')
                    ORDER BY ag.timemodified DESC
                    LIMIT 1';
            $assignment = $DB->get_field_sql($sql);
        }

        if (empty($assignment)) {
            $warnings[] = array(
                'item' => 'assignments',
                'itemid' => 0,
                'warningcode' => '1',
                'message' => 'No  graded assignments found'
            );
        } else {
            $cm = get_coursemodule_from_instance('assign',$assignment);
            $url = new \moodle_url("/mod/assign/view.php", ['id' => $cm->id]);
            $sql = 'SELECT * FROM {assign_grades} WHERE assignment = :aid ORDER BY grade ASC';
            if (!empty($params['limitnumber'])){
                $sql .= ' LIMIT '.$params['limitnumber'];
            }
            $grades = $DB->get_records_sql($sql, ['aid' => $assignment]);
            foreach ($grades as $g) {
                $user = $DB->get_record('user', ['id' => $g->userid], 'id, username, firstname, lastname');
                $grade = array(
                    'username' => $user->username,
                    'fullname' => $user->firstname.' '.$user->lastname,
                    'grade' => $g->grade,
                    'date' => $g->timemodified,
                );
                $gradesarray[] = $grade;
            }

            if (empty($gradesarray)) {
                $warnings[] = array(
                    'item' => 'grades',
                    'itemid' => 0,
                    'warningcode' => '2',
                    'message' => 'No grades found'
                );
            }
        }

        $result = array(
            'cmid' => $cm->id,
            'name' => $cm->name,
            'url' => $url->out(),
            'grades' => $gradesarray,
            'warnings' => $warnings
        );

        return $result;
    }


    /**
     * Creates user grade external_single_structure
     *
     * @return external_single_structure
     */
    private static function get_user_grade_structure() {
        return new \external_single_structure(
            array(
                'username' => new \external_value(PARAM_TEXT, 'participant username'),
                'fullname' => new \external_value(PARAM_TEXT, 'participant fullname'),
                'grade' => new \external_value(PARAM_FLOAT, 'participant grade'),
                'date' => new \external_value(PARAM_INT, 'grade date'),
            ), 'user grade information object'
        );
    }

    /**
     * Describes the return value for get_assignments_ungraded
     *
     * @return external_single_structure
     */
    public static function assignment_lowest_grades_read_returns() {
        return new \external_single_structure(
            array(
                'cmid' => new \external_value(PARAM_INT, 'course module id'),
                'name' => new \external_value(PARAM_TEXT, 'assignment name'),
                'url' => new \external_value(PARAM_TEXT, 'activity link'),
                'grades' => new \external_multiple_structure(self::get_user_grade_structure(), 'list of lowest grades', VALUE_DEFAULT, []),
                'warnings'  => new \external_warnings('item can be \'assignments\' (errorcode 1 or 2)',
                    'When item is "grades" then itemid is by default 0',
                    'errorcode can be 1 (no graded assignments found) or 2 (no grades found)')
            )
        );
    }

}
