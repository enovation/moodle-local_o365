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
 * Get a list of user roles
 */
class read_roles extends \external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function roles_read_parameters() {
        return new \external_function_parameters([
            'username' => new \external_value(
                PARAM_TEXT,
                'username',
                VALUE_REQUIRED
            )
        ]);
    }

    /**
     * Returns the roles for the user.
     *
     * @param text name Required param based on which the user is looked up in database
     * @return An array of students and warnings.
     */
    public static function roles_read($username) {
        global $USER, $DB;
        $rolesdetails = [];
        $warnings = [];
        $courses = [];
        $params = self::validate_parameters(
            self::roles_read_parameters(),
            array(
                'username' => $username
            )
        );
        $userid = $DB->get_field('user', 'id', ['username' => $username]);
        if(empty($userid)){
            $warnings[] = array(
                'item' => 'user',
                'itemid' => 0,
                'warningcode' => '1',
                'message' => 'User not found'
            );
        }else{
            $roles = $DB->get_records_sql('SELECT ra.roleid, r.shortname, r.name FROM {role_assignments} ra 
                            JOIN {role} r ON r.id = ra.roleid
                            WHERE ra.userid = :userid ', ['userid' => $userid]);
            foreach($roles as $role){
                $rolesdetails[] = [
                    'shortname' => $role->shortname,
                    'name' => $role->name,
                ];
            }
        }

        $result = array(
            'username' => $params['username'],
            'roles' => $rolesdetails,
            'warnings' => $warnings
        );

        return $result;
    }


    /**
     * Creates absent user external_single_structure
     *
     * @return external_single_structure
     */
    private static function get_roles_structure() {
        return new \external_single_structure(
            array(
                'shortname' => new \external_value(PARAM_TEXT, 'role short username'),
                'name' => new \external_value(PARAM_TEXT, 'participant fullname'),
            ), 'user roles details'
        );
    }

    /**
     * Describes the return value for get_roles
     *
     * @return external_single_structure
     */
    public static function roles_read_returns() {
        return new \external_single_structure(
            array(
                'username' => new \external_value(PARAM_TEXT, 'participant username'),
                'roles' => new \external_multiple_structure(self::get_roles_structure(), 'student roles details details', VALUE_DEFAULT, []),
                'warnings'  => new \external_warnings('item can be \'users\' (errorcode 1)',
                    'When item is "user" then itemid is by default 0',
                    'errorcode can be 1 (no user with such username found)')
            )
        );
    }

}
