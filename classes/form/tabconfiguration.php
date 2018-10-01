<?php
/**
 * Created by PhpStorm.
 * User: weilai
 * Date: 01/10/2018
 * Time: 10:19
 */

namespace local_o365\form;

/**
 * A form for configuring of Moodle tab in Teams.
 *
 * @author Lai Wei <lai.wei@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2018 onwards Microsoft, Inc. (http://microsoft.com/)
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Tab configuration form class
 */
class tabconfiguration extends \moodleform {

    /**
     * Definition of the form.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function definition() {
        $mform = $this->_form;

        $courseoptions = self::get_course_options();
        $courseselector = $mform->createElement('select', 'course', get_string('course'), $courseoptions,
            array('onchange' => 'onChange()'));
        $courseselector->setSize(100);
        $courseselector->setMultiple(true);

        $mform->addElement($courseselector);
        $mform->addRule('course', null, 'required', null, 'client');
    }

    /**
     * Return a list of courses that the user has access to, to be used as options in the drop down list.
     *
     * @return array
     * @throws \dml_exception
     */
    private function get_course_options() {
        global $DB, $USER;

        $courseoptions = array();

        if (is_siteadmin($USER->id)) {
            $courses = $DB->get_records('course', ['visible' => 1]);
            unset($courses[SITEID]);
        } else {
            $courses = enrol_get_users_courses($USER->id, true, null, 'fullname');
        }

        foreach ($courses as $course) {
            $courseoptions[$course->id] = $course->fullname . ' (' . $course->shortname . ')';
        }

        asort($courseoptions);

        return $courseoptions;
    }
}