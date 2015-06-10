<?php

/**
 * Course backup form
 * 
 * @copyright Copyright 2015 eLearningExperts
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt GNU Public License 3.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Automater course backup form
 */
class course_automater_backupfile_form extends moodleform {

    /**
     * Backup form with file manager field
     */
    function definition() {
        $mform = &$this->_form;
        $contextid = $this->_customdata['contextid'];

        $mform->addElement('hidden', 'contextid', $contextid);
        $mform->setType('contextid', PARAM_INT);
        
        $options = array('subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => array('.mbz'), 'return_types' => FILE_INTERNAL | FILE_EXTERNAL);
        $mform->addElement('filemanager', 'course_backup', get_string('files'), null, $options);
        $submit_string = get_string('upload');
        $this->add_action_buttons(false, $submit_string);
    }

}
