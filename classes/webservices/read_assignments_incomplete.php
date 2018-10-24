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
 * Get a list of incomplete due assignments.
 */
class read_assignments_incomplete extends \external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function assignments_incomplete_read_parameters() {
        return new \external_function_parameters([
            'limitnumber' => new \external_value(
                PARAM_INT,
                'maximum number of returned grades',
                VALUE_DEFAULT,
                10
            ),
            'usertype' => new \external_value(
                PARAM_TEXT,
                'type of use - student or teacher',
                VALUE_DEFAULT,
                'teacher'
            ),
        ]);
    }

    /**
     * Returns an array of assignments that are due soon and are incomplete (if user type student) or has users who did not complete yet(if user type teacher).
     *
     * @param int $limitnumber An optional number which lets to set the maximum nuber of returned records
     * @param string $usertype It can be either teacher or student. By default it is teacher.
     * @return An array of assignments and warnings.
     */
    public static function assignments_incomplete_read($limitnumber = 10, $usertype = 'teacher') {
        global $USER, $DB, $OUTPUT;
        $assignmentsarray = [];
        $warnings = [];
        $params = self::validate_parameters(
            self::assignments_incomplete_read_parameters(),
            array(
                'limitnumber' => $limitnumber,
                'usertype' => $usertype
            )
        );

        $courses = array_keys(enrol_get_users_courses($USER->id, true, 'id'));
        if($usertype == 'teacher'){
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
        if(!empty($courses)){
            $coursessqlparam = join(',', $courses);
            $sql = "SELECT id, duedate FROM {assign}                
                    WHERE course IN ($coursessqlparam) AND duedate > UNIX_TIMESTAMP()
                    ORDER BY duedate ASC";
            $assignments = $DB->get_records_sql($sql);
        }else{
            $assignments = [];
        }

        if (empty($assignments)) {
            $warnings[] = array(
                'item' => 'assignments',
                'itemid' => 0,
                'warningcode' => '1',
                'message' => 'No due assignments found'
            );
        } else {
            foreach($assignments as $assign){
                $cm = get_coursemodule_from_instance('assign', $assign->id);
                $url = new \moodle_url("/mod/assign/view.php", ['id' => $cm->id]);
                if($usertype == 'teacher'){
                    $coursecontext = \context_course::instance($cm->course);
                    $enrolledusers = get_enrolled_users($coursecontext, '', 0, 'u.id', null, 0, 0, true);
                    $enrolledusers = array_keys($enrolledusers);
                    $enrolledteachers = get_enrolled_users($coursecontext, 'moodle/grade:edit', 0, 'u.id', null, 0, 0, true);
                    $enrolledteachers = array_keys($enrolledteachers);
                    $enrolledusers = array_diff($enrolledusers, $enrolledteachers);
                    $total = count($enrolledusers);
                    $completedusers = $DB->get_fieldset_sql('SELECT DISTINCT(userid) FROM {course_modules_completion} 
                                        WHERE coursemoduleid = :cmid AND completionstate > 0', array('cmid' => $cm->id));
                    $completedusers = array_diff($completedusers, $enrolledteachers);
                    $completedusers = count($completedusers);
                    $incomplete = $total - $completedusers;
                    if($incomplete == 0){
                        continue;
                    }else{
                        $percentage = number_format(($incomplete/$total*100),2).'%';
                    }
                }else{
                    if($DB->record_exists_sql('SELECT userid FROM {course_modules_completion} 
                                        WHERE userid = :userid AND coursemoduleid = :cmid AND completionstate > 0',
                        array('userid' => $USER->id, 'cmid' => $cm->id))){
                        continue;
                    }
                    $incomplete = 0;
                    $total = 0;
                    $percentage = '';
                }
                $assignment = array(
                    'cmid' => $cm->id,
                    'name' => $cm->name,
                    'incomplete' => $incomplete,
                    'total' => $total,
                    'percentage' => $percentage,
                    'duedate' => $assign->duedate,
                    'icon' => $OUTPUT->image_url('icon', 'assign')->out(),
                    'url' => $url->out()
                );
                $assignmentsarray[] = $assignment;
                if (!empty($params['limitnumber']) && count($assignmentsarray) == $params['limitnumber']) {
                    break;
                }
            }
        }
        if (empty($assignmentsarray)) {
            $warnings[] = array(
                'item' => 'assignments',
                'itemid' => 0,
                'warningcode' => '2',
                'message' => 'No due and incomplete assignments found'
            );
        }
        $result = array(
            'assignments' => $assignmentsarray,
            'warnings' => $warnings
        );

        return $result;
    }


    /**
     * Creates an assignment external_single_structure
     *
     * @return external_single_structure
     */
    private static function get_assignments_incomplete_structure() {
        return new \external_single_structure(
            array(
                'cmid' => new \external_value(PARAM_INT, 'course module id'),
                'name' => new \external_value(PARAM_TEXT, 'assignment name'),
                'incomplete' => new \external_value(PARAM_INT, 'number of users who did not complete'),
                'total' => new \external_value(PARAM_INT, 'total users number'),
                'percentage' => new \external_value(PARAM_TEXT, 'percentage of incompleted/total'),
                'duedate' => new \external_value(PARAM_INT, 'due date'),
                'icon' => new \external_value(PARAM_TEXT, 'activity link'),
                'url' => new \external_value(PARAM_TEXT, 'activity link'),
            ), 'assignment information object'
        );
    }

    /**
     * Describes the return value for get_assignments_incomplete
     *
     * @return external_single_structure
     */
    public static function assignments_incomplete_read_returns() {
        return new \external_single_structure(
            array(
                'assignments' => new \external_multiple_structure(self::get_assignments_incomplete_structure(), 'list of incomplete due assignments', VALUE_DEFAULT, []),
                'warnings'  => new \external_warnings('item can be \'assignments\' (errorcode 1 or 2)',
                    'When item is "assignments" then itemid is by default 0',
                    'errorcode can be 1 (no due assignments found) or 2 (no incomplete due assignments found)')
            )
        );
    }

}
