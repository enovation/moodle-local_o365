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
 * Get a list of user assignments grades compared to class average.
 */
class read_assignments_compared extends \external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function assignments_compared_read_parameters() {
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
     * Returns an array of assignments the user is enrolled and graded.
     *
     * @param int $limitnumber An optional number which lets to set the maximum nuber of returned records
     * @return An array of assignments and warnings.
     */
    public static function assignments_compared_read($limitnumber = 10) {
        global $USER, $DB, $OUTPUT;
        $assignmentsarray = [];
        $warnings = [];
        $params = self::validate_parameters(
            self::assignments_compared_read_parameters(),
            array(
                'limitnumber' => $limitnumber
            )
        );

        $sql = "SELECT gi.iteminstance, g.itemid, g.finalgrade, g.timemodified FROM {grade_grades} g
                JOIN {grade_items} gi ON gi.id = g.itemid
                WHERE g.userid = ? AND gi.itemmodule LIKE 'assign' AND g.finalgrade IS NOT NULL
                ORDER BY g.timemodified DESC";

        if (!empty($params['limitnumber'])) {
            $sql .= " LIMIT {$params['limitnumber']}";
        }
        $sqlparams = [$USER->id];
        $assignments = $DB->get_records_sql($sql, $sqlparams);

        if (empty($assignments)) {
            $warnings[] = array(
                'item' => 'grades',
                'itemid' => 0,
                'warningcode' => '1',
                'message' => 'No grades found'
            );
        } else {
            // Find sums of all grade items in assignments.

            foreach($assignments as $assign){
                $cm = get_coursemodule_from_instance('assign', $assign->iteminstance);
                $coursecontext = \context_course::instance($cm->course);
                $course = get_course($cm->course);
                $group = groups_get_course_group($course);
                $participants = get_enrolled_users($coursecontext,'',$group,'u.id',null,0,0,false);
                $participants = join(',', array_keys($participants));
                $url = new \moodle_url("/mod/assign/view.php", ['id' => $cm->id]);
                $sql = "SELECT g.itemid, COUNT(*) AS amount, SUM(g.finalgrade) AS sum
                      FROM {grade_items} gi
                      JOIN {grade_grades} g ON g.itemid = gi.id
                      JOIN {user} u ON u.id = g.userid                      
                     WHERE gi.itemmodule LIKE 'assign' 
                       AND gi.iteminstance = :assignmentid
                       AND u.deleted = 0
                       AND g.finalgrade IS NOT NULL
                       AND u.id IN ($participants)                      
                     GROUP BY g.itemid";
                $sqlparams = ['assignmentid' => $assign->iteminstance];
                $average = $DB->get_record_sql($sql, $sqlparams);
                $assignment = array(
                    'cmid' => $cm->id,
                    'name' => $cm->name,
                    'grade' => $assign->finalgrade,
                    'classgrade' => $average->sum / $average->amount,
                    'date' => $assign->timemodified,
                    'icon' => $OUTPUT->image_url('icon', 'assign')->out(),
                    'url' => $url->out()
                );
                $assignmentsarray[] = $assignment;
            }
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
    private static function get_assignments_compared_structure() {
        return new \external_single_structure(
            array(
                'cmid' => new \external_value(PARAM_INT, 'course module id'),
                'name' => new \external_value(PARAM_TEXT, 'activity name'),
                'grade' => new \external_value(PARAM_TEXT, 'activity grade'),
                'classgrade' => new \external_value(PARAM_TEXT, 'grade date (unix timestamp)'),
                'date' => new \external_value(PARAM_INT, 'grade date'),
                'icon' => new \external_value(PARAM_TEXT, 'activity link'),
                'url' => new \external_value(PARAM_TEXT, 'activity link'),
            ), 'assignment information object'
        );
    }

    /**
     * Describes the return value for get_assignments_compared
     *
     * @return external_single_structure
     */
    public static function assignments_compared_read_returns() {
        return new \external_single_structure(
            array(
                'assignments' => new \external_multiple_structure(self::get_assignments_compared_structure(), 'list of user assignments with grades', VALUE_DEFAULT, []),
                'warnings'  => new \external_warnings('item can be \'assignments\' (errorcode 1)',
                    'When item is "assignments" then itemid is by default 0',
                    'errorcode can be 1 (no records found)')
            )
        );
    }

}
