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
 * Get a list of late submissions.
 */
class read_late_submissions extends \external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function late_submissions_read_parameters() {
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
     * Returns an array of late submissions.
     *
     * @param int $limitnumber An optional number which lets to set the maximum nuber of returned records
     * @return An array of submissions and warnings.
     */
    public static function late_submissions_read($limitnumber = 10) {
        global $USER, $DB;
        $submissionsarray = [];
        $warnings = [];
        $params = self::validate_parameters(
            self::late_submissions_read_parameters(),
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
            $sql = 'SELECT ass.id, ass.userid, ass.assignment, ass.timemodified, a.duedate, co.fullname as coursename FROM {assign_submission} ass
                    JOIN {assign} a ON ass.assignment = a.id 
                    JOIN {course} co ON co.id = a.course
                    WHERE a.course IN ('.$coursessqlparam.') AND ass.status LIKE "'.ASSIGN_SUBMISSION_STATUS_SUBMITTED.'" AND a.duedate < ass.timemodified 
                    ORDER BY ass.timecreated DESC';
            if (!empty($params['limitnumber'])){
                $sql .= ' LIMIT '.$params['limitnumber'];
            }

            $submissions = $DB->get_records_sql($sql);
        }else{
            $submissions = [];
        }

        if (empty($submissions)) {
            $warnings[] = array(
                'item' => 'submissions',
                'itemid' => 0,
                'warningcode' => '1',
                'message' => 'No  late submissions found'
            );
        } else {
            foreach ($submissions as $submission) {
                $cm = get_coursemodule_from_instance('assign', $submission->assignment);
                $user = $DB->get_record('user', ['id' => $submission->userid], 'id, username, firstname, lastname');
                $url = new \moodle_url('/mod/assign/view.php', ['action' => 'grading', 'id'=> $cm->id, 'tsort' => 'timesubmitted']);
                $record = array(
                    'cmid' => $cm->id,
                    'name' => $cm->name,
                    'username' => $user->username,
                    'fullname' => $user->firstname.' '.$user->lastname,
                    'timesubmitted' => $submission->timemodified,
                    'duedate' => $submission->duedate,
                    'coursename' => $submission->coursename,
                    'url' => $url->out()
                );
                $submissionsarray[] = $record;
            }
        }
        $result = array(
            'submissions' => $submissionsarray,
            'warnings' => $warnings
        );

        return $result;
    }


    /**
     * Creates submissions external_single_structure
     *
     * @return external_single_structure
     */
    private static function get_late_submissions_structure() {
        return new \external_single_structure(
            array(
                'cmid' => new \external_value(PARAM_INT, 'course module id'),
                'name' => new \external_value(PARAM_TEXT, 'assignment name'),
                'username' => new \external_value(PARAM_TEXT, 'student username'),
                'fullname' => new \external_value(PARAM_TEXT, 'student fullname'),
                'timesubmitted' => new \external_value(PARAM_INT, 'time when submission was made'),
                'duedate' => new \external_value(PARAM_INT, 'time when activity is due'),
                'coursename' => new \external_value(PARAM_TEXT, 'course name'),
                'url' => new \external_value(PARAM_TEXT, 'activity submissions link'),
            ), 'submission information object'
        );
    }

    /**
     * Describes the return value for get_late_submissions
     *
     * @return external_single_structure
     */
    public static function late_submissions_read_returns() {
        return new \external_single_structure(
            array(
                'submissions' => new \external_multiple_structure(self::get_late_submissions_structure(), 'list of submissions that were late', VALUE_DEFAULT, []),
                'warnings'  => new \external_warnings('item can be \'submissions\' (errorcode 1)',
                    'When item is "submissions" then itemid is by default 0',
                    'errorcode can be 1 (no late submissions found)')
            )
        );
    }

}
