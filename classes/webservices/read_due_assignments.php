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

require_once($CFG->dirroot.'/course/modlib.php');
require_once($CFG->libdir.'/externallib.php');
require_once($CFG->dirroot.'/user/externallib.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');
require_once($CFG->dirroot.'/mod/assign/lib.php');


/**
 * Get a list of due assignments in one or more courses.
 */
class read_due_assignments extends \external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function due_assignments_read_parameters() {
        return new \external_function_parameters([
            'courseids' => new \external_multiple_structure(
                new \external_value(PARAM_INT, 'course id, empty for retrieving all the courses where the user is enroled in'),
                '0 or more course ids',
                VALUE_DEFAULT,
                []
            ),
            'assignmentids' => new \external_multiple_structure(
                new \external_value(PARAM_INT, 'assignment id, empty for retrieving all assignments for courses the user is enroled in'),
                '0 or more assignment ids',
                VALUE_DEFAULT,
                []
            ),
            'capabilities'  => new \external_multiple_structure(
                new \external_value(PARAM_CAPABILITY, 'capability'),
                'list of capabilities used to filter courses',
                VALUE_DEFAULT,
                []
            ),
            'includenotenrolledcourses' => new \external_value(
                PARAM_BOOL,
                'whether to return courses that the user can see even if is not enroled in. This requires the parameter courseids to not be empty.',
                VALUE_DEFAULT,
                false
            ),
            'limitnumber' => new \external_value(
                PARAM_INT,
                'maximum number of returned assignments',
                VALUE_DEFAULT,
                10
            ),
        ]);
    }

    /**
     * Returns an array of due assignments the user is enrolled.
     *
     * @param array $courseids An optional array of course ids. If provided only assignments within the given course
     * will be returned. If the user is not enrolled in or can't view a given course a warning will be generated and returned.
     * @param array $assignmentids An optional array of assignment ids.
     * @param array $capabilities An array of additional capability checks you wish to be made on the course context.
     * @param bool $includenotenrolledcourses Wheter to return courses that the user can see even if is not enroled in.
     * This requires the parameter $courseids to not be empty.
     * @param int $limitnumber An optional number which lets to set the maximum nuber of returned records
     * @return An array of courses and warnings.
     */
    public static function due_assignments_read($courseids = [], $assignmentids = [], $capabilities = [], $includenotenrolledcourses = false, $limitnumber = 10) {
        global $USER, $DB, $OUTPUT;
        $assignmentarray = [];
        $warnings = [];
        $params = self::validate_parameters(
            self::due_assignments_read_parameters(),
            array(
                'courseids' => $courseids,
                'assignmentids' => $assignmentids,
                'capabilities' => $capabilities,
                'includenotenrolledcourses' => $includenotenrolledcourses,
                'limitnumber' => $limitnumber
            )
        );

        $assignmentids = array_flip($params['assignmentids']);
        $courses = array();
        $fields = 'sortorder,shortname,fullname,timemodified';

        // If the courseids list is empty, we return only the courses where the user is enrolled in.
        if (empty($params['courseids'])) {
            $courses = enrol_get_users_courses($USER->id, true, $fields);
            $courseids = array_keys($courses);
        } else if ($includenotenrolledcourses) {
            // In this case, we don't have to check here for enrolmnents. Maybe the user can see the course even if is not enrolled.
            $courseids = $params['courseids'];
        } else {
            // We need to check for enrolments.
            $mycourses = enrol_get_users_courses($USER->id, true, $fields);
            $mycourseids = array_keys($mycourses);

            foreach ($params['courseids'] as $courseid) {
                if (!in_array($courseid, $mycourseids)) {
                    unset($courses[$courseid]);
                } else {
                    $courses[$courseid] = $mycourses[$courseid];
                }
            }
            $courseids = array_keys($courses);
        }

        foreach ($courseids as $cid) {

            try {
                $context = \context_course::instance($cid);
                self::validate_context($context);

                // Check if this course was already loaded (by enrol_get_users_courses).
                if (!isset($courses[$cid])) {
                    $courses[$cid] = get_course($cid);
                }
            } catch (Exception $e) {
                unset($courses[$cid]);
                continue;
            }
            if (count($params['capabilities']) > 0 && !has_all_capabilities($params['capabilities'], $context)) {
                unset($courses[$cid]);
            }
        }
        if(!empty($courses)){
            $courseids = implode(",", array_keys($courses));
            $assignments = $DB->get_records_sql("SELECT * FROM {assign} a WHERE a.course IN (".$courseids.") AND a.duedate > UNIX_TIMESTAMP() ORDER BY a.duedate ASC");
            foreach ($assignments as $assignment) {
                // Check assignment ID filter.
                if (!empty($assignmentids) && !isset($assignmentids[$assignment])) {
                    continue;
                }
                $cm = get_coursemodule_from_instance('assign', $assignment->id);
                $course = get_course($assignment->course);
                if(assign_get_completion_state($course,$cm, $USER->id,false)){
                    continue;
                }

                $url = new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
                $assignment = array(
                    'id' => $assignment->id,
                    'cmid' => $cm->id,
                    'course' => $assignment->course,
                    'name' => $assignment->name,
                    'duedate' => $assignment->duedate,
                    'icon' => $OUTPUT->image_url('icon', 'assign')->out(),
                    'url' => $url->out(),
                );
                $assignmentarray[] = $assignment;
                if(!empty( $params['courseids']) && $params['limitnumber'] == count($assignmentarray)){
                    break;
                }
            }
        }
        if(empty($assignmentarray)){
            $warnings[] = array(
                'item' => 'assignments',
                'itemid' => 0,
                'warningcode' => '1',
                'message' => 'No due assignments found'
            );
        }

        $result = array(
            'assignments' => $assignmentarray,
            'warnings' => $warnings
        );

        return $result;
    }


    /**
     * Creates an assignment external_single_structure
     *
     * @return external_single_structure
     */
    private static function get_due_assignments_structure() {
        return new \external_single_structure(
            array(
                'id' => new \external_value(PARAM_INT, 'assignment id'),
                'cmid' => new \external_value(PARAM_INT, 'course module id'),
                'course' => new \external_value(PARAM_INT, 'course id'),
                'name' => new \external_value(PARAM_TEXT, 'assignment name'),
                'duedate' => new \external_value(PARAM_TEXT, 'assignment due date'),
                'icon' => new \external_value(PARAM_TEXT, 'activity link'),
                'url' => new \external_value(PARAM_TEXT, 'assignment link'),
            ), 'assignment information object'
        );
    }

    /**
     * Describes the return value for get_due_assignments
     *
     * @return external_single_structure
     */
    public static function due_assignments_read_returns() {
        return new \external_single_structure(
            array(
                'assignments' => new \external_multiple_structure(self::get_due_assignments_structure(), 'list of due assignments', VALUE_DEFAULT, []),
                'warnings'  => new \external_warnings('item can be \'assignments\' (errorcode 1)',
                    'When item is "assignments" then itemid is by default 0',
                    'errorcode can be 1 (no records found)')
            )
        );
    }

}
