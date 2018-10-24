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
 * Get a list of assignments and quizes grades in one or more courses.
 */
class read_grades extends \external_api {

    /**
     * @return array the list of modules which latest grades are returned
     */
    private static function get_activities_list(){
        return [
            'assign',
            'quiz'
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
     * Returns an array of latest grades the user got.
     *
     * @param int $limitnumber An optional number which lets to set the maximum nuber of returned record.
     * @return An array of grades and warnings.
     */
    public static function grades_read($limitnumber = 10) {
        global $USER, $DB, $OUTPUT;
        $gradesarray = [];
        $warnings = [];
        $params = self::validate_parameters(
            self::grades_read_parameters(),
            array(
                'limitnumber' => $limitnumber
            )
        );

        $activities = self::get_activities_list();
        $activities = join("','", $activities);
        $sql = "SELECT g.id, gi.itemmodule, gi.iteminstance, g.finalgrade, g.timemodified FROM {grade_grades} g
                JOIN {grade_items} gi ON gi.id = g.itemid
                WHERE g.userid = ? AND gi.itemmodule IN ('$activities') AND g.finalgrade IS NOT NULL
                ORDER BY g.timemodified DESC";
        if (!empty($params['limitnumber'])) {
            $sql .= " LIMIT {$params['limitnumber']}";
        }
        $sqlparams = [$USER->id];
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
                $cm = get_coursemodule_from_instance($grade->itemmodule, $grade->iteminstance);
                $url = new \moodle_url("/mod/{$grade->itemmodule}/view.php", ['id' => $grade->iteminstance]);
                $grade = array(
                    'cmid' => $cm->id,
                    'name' => $cm->name,
                    'type' => $grade->itemmodule,
                    'grade' => $grade->finalgrade,
                    'date' => $grade->timemodified,
                    'icon' => $OUTPUT->image_url('icon', $grade->itemmodule)->out(),
                    'url' => $url->out()
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
     * Creates a grade external_single_structure
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
                'date' => new \external_value(PARAM_INT, 'grade date (unix timestamp)'),
                'icon' => new \external_value(PARAM_TEXT, 'activity link'),
                'url' => new \external_value(PARAM_TEXT, 'activity link'),
            ), 'assignment information object'
        );
    }

    /**
     * Describes the return value for get_grades
     *
     * @return external_single_structure
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
