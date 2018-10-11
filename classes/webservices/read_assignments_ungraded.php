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
 * Get a list of ungraded assignments.
 */
class read_assignments_ungraded extends \external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function assignments_ungraded_read_parameters() {
        return new \external_function_parameters([
            'limitnumber' => new \external_value(
                PARAM_INT,
                'maximum number of returned grades',
                VALUE_DEFAULT,
                10
            ),
        ]);
    }

    /**
     * Returns an array of assignments that needs grading.
     *
     * @param int $limitnumber An optional number which lets to set the maximum nuber of returned records
     * @return An array of assignments and warnings.
     */
    public static function assignments_ungraded_read($limitnumber = 10) {
        global $USER, $DB;
        $assignmentsarray = [];
        $warnings = [];
        $params = self::validate_parameters(
            self::assignments_ungraded_read_parameters(),
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
            $sql = 'SELECT assign.id FROM (SELECT a.id FROM {assign} a JOIN {assign_submission} asub ON asub.assignment = a.id WHERE a.course IN ('
                    .$coursessqlparam.') ORDER BY asub.timecreated DESC) assign GROUP BY assign.id';
            $assignments = $DB->get_fieldset_sql($sql);
        }else{
            $assignments = [];
        }

        if (empty($assignments)) {
            $warnings[] = array(
                'item' => 'assignments',
                'itemid' => 0,
                'warningcode' => '1',
                'message' => 'No  assignments for grading found'
            );
        } else {
            foreach ($assignments as $aid) {

                $cm = get_coursemodule_from_instance('assign', $aid);
                $cmcontext = \context_module::instance($cm->id);
                $assign = new \assign($cmcontext, $cm, $cm->course);
                $url = new \moodle_url("/mod/assign/view.php", ['id' => $cm->id]);
                $currentgroup = groups_get_activity_group($assign->get_course_module(), true);
                $needsgrading = $assign->count_submissions_need_grading($currentgroup);
                if($needsgrading == 0){
                    continue;
                }
                $participants = $assign->count_participants($currentgroup);
                $submitted = $assign->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED, $currentgroup);
                $percentage = ($participants - $submitted + $needsgrading)/$participants;

                $assignment = array(
                    'cmid' => $cm->id,
                    'name' => $cm->name,
                    'needsgrading' => $needsgrading,
                    'participants' => $participants,
                    'submitted' => $submitted,
                    'percentage' => number_format(($percentage*100),2).'%',
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
                'message' => 'No assignments that needs graing found'
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
    private static function get_assignments_ungraded_structure() {
        return new \external_single_structure(
            array(
                'cmid' => new \external_value(PARAM_INT, 'course module id'),
                'name' => new \external_value(PARAM_TEXT, 'assignment name'),
                'needsgrading' => new \external_value(PARAM_INT, 'number of users who did not complete'),
                'participants' => new \external_value(PARAM_INT, 'total users number'),
                'submitted' => new \external_value(PARAM_INT, 'percentage of incompleted/total'),
                'percentage' => new \external_value(PARAM_TEXT, 'due date'),
                'url' => new \external_value(PARAM_TEXT, 'activity link'),
            ), 'assignment information object'
        );
    }

    /**
     * Describes the return value for get_assignments_ungraded
     *
     * @return external_single_structure
     */
    public static function assignments_ungraded_read_returns() {
        return new \external_single_structure(
            array(
                'assignments' => new \external_multiple_structure(self::get_assignments_ungraded_structure(), 'list of assignments that needs grading', VALUE_DEFAULT, []),
                'warnings'  => new \external_warnings('item can be \'assignments\' (errorcode 1 or 2)',
                    'When item is "assignments" then itemid is by default 0',
                    'errorcode can be 1 (no assignments found) or 2 (no assignments that needs graing found)')
            )
        );
    }

}
