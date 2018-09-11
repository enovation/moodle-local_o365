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
 * @author  2012 Paul Charsley, modified slightly 2017 James McQuillan
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2012 Paul Charsley
 */

namespace local_o365\webservices;

/**
 * Get a list of assignments and quizes grades in one or more courses.
 */
class read_grades extends \external_api {

    private static function get_activities_list(){
        return [
            ['name' => 'assign', 'id_field' => 'assignment', 'time_field' => 'timemodified'],
            ['name' => 'quiz', 'id_field' => 'quiz', 'time_field' => 'timemodified']
        ];
    }
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function grades_read_parameters() {
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
     * Returns an array of courses the user is enrolled, and for each course all of the assignments that the user can
     * view within that course.
     *
     * @param array $courseids An optional array of course ids. If provided only assignments within the given course
     * will be returned. If the user is not enrolled in or can't view a given course a warning will be generated and returned.
     * @param array $capabilities An array of additional capability checks you wish to be made on the course context.
     * This requires the parameter $courseids to not be empty.
     * @return An array of courses and warnings.
     * @since  Moodle 2.4
     */
    public static function grades_read($limitnumber = 10) {
        global $USER, $DB;
        $gradesarray = [];
        $warnings = [];
        $params = self::validate_parameters(
            self::grades_read_parameters(),
            array(
                'limitnumber' => $limitnumber
            )
        );

        $activities = self::get_activities_list();
        $sql = '';
        $sqlparams = [];
        foreach($activities as $activity){
            if(!empty($sql)){
                $sql .= ' UNION ';
            }
            $moduleid = $DB->get_field('modules', 'id', ['name' => $activity['name']]);
            $sql .= "(SELECT cm.id,  m.name, '{$activity['name']}' as type, g.{$activity['id_field']} as mid, g.grade, g.{$activity['time_field']} as time FROM {{$activity['name']}_grades} g 
                        JOIN {{$activity['name']}} m ON m.id = g.{$activity['id_field']} 
                        JOIN {course_modules} cm ON cm.module = $moduleid AND cm.instance = g.{$activity['id_field']}
                        WHERE g.userid = ? )";
            $sqlparams[] = $USER->id;
        }

        $sql .= " ORDER BY time DESC";

        if (!empty($params['limitnumber'])) {
            $sql .= " LIMIT {$params['limitnumber']}";
        }
        $grades = $DB->get_records_sql($sql, $sqlparams);
        if (empty($grades)) {
            $warnings[] = array(
                'item' => 'grades',
                'itemid' => 0,
                'warningcode' => '1',
                'message' => 'No grades found'
            );
        } else {
            foreach($grades as $grade){
                $url = new \moodle_url("/mod/{$grade->type}/view.php", ['id' => $grade->mid]);
                $grade = array(
                    'cmid' => $grade->id,
                    'name' => $grade->name,
                    'type' => $grade->type,
                    'grade' => $grade->grade,
                    'url' => $url->out(),
                );
                $gradesarray[] = $grade;
            }
        }
        $result = array(
            'grades' => $gradesarray,
            'warnings' => $warnings
        );

        return $result;
    }


    /**
     * Creates a course external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    private static function get_grades_structure() {
        return new \external_single_structure(
            array(
                'cmid' => new \external_value(PARAM_INT, 'course module id'),
                'name' => new \external_value(PARAM_TEXT, 'activity name'),
                'type' => new \external_value(PARAM_TEXT, 'activity type'),
                'grade' => new \external_value(PARAM_TEXT, 'activity grade'),
                'url' => new \external_value(PARAM_TEXT, 'activity link'),
            ), 'assignment information object'
        );
    }

    /**
     * Describes the return value for get_assignments
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    public static function grades_read_returns() {
        return new \external_single_structure(
            array(
                'grades' => new \external_multiple_structure(self::get_grades_structure(), 'list of user grades', VALUE_DEFAULT, []),
                'warnings'  => new \external_warnings('item can be \'grades\' (errorcode 1)',
                    'When item is "grades" then itemid is by default 0',
                    'errorcode can be 1 (no records found)')
            )
        );
    }

}
