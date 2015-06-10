<?php

/**
 * Automater form
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
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/coursecatlib.php');
require_once($CFG->dirroot . '/mod/url/locallib.php');

/**
 * Class course automater form.
 */
class course_automater_form extends moodleform {

    /**
     * Define the form.
     */
    public function definition() {
        global $USER, $PAGE;

        $mform = $this->_form;

        $contextid = $this->_customdata['contextid'];
        $mform->addElement('hidden', 'contextid', $contextid);
        $mform->setType('contextid', PARAM_INT);

        // Add file format hidden field.
        $mform->addElement('hidden', 'format', $this->_customdata['format']);
        $mform->setType('format', PARAM_TEXT);

        $mform->addElement('text', 'fullname', get_string('fullnamecourse'), 'maxlength="254" size="50"');
        $mform->addHelpButton('fullname', 'fullnamecourse');
        $mform->addRule('fullname', get_string('missingfullname'), 'required', null, 'client');
        $mform->setType('fullname', PARAM_TEXT);

        $mform->addElement('text', 'shortname', get_string('shortnamecourse'), 'maxlength="100" size="20"');
        $mform->addHelpButton('shortname', 'shortnamecourse');
        $mform->addRule('shortname', get_string('missingshortname'), 'required', null, 'client');
        $mform->setType('shortname', PARAM_TEXT);

        $displaylist = coursecat::make_categories_list('moodle/course:create');
        $mform->addElement('select', 'category', get_string('coursecategory'), $displaylist);
        $mform->addHelpButton('category', 'coursecategory');

        $mform->addElement('url', 'externalurl', get_string('externalurl', 'url'), array('size' => '60'), array('usefilepicker' => true));
        $mform->setType('externalurl', PARAM_RAW_TRIMMED);
        $mform->addRule('externalurl', null, 'required', null, 'client');

        $mform->addElement('filepicker', 'newfile', get_string('newfile', 'local_automater'));
        $mform->addHelpButton('newfile', 'newfile', 'local_automater');
        $mform->addRule('newfile', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        // Add field validation check for duplicate shortname.
        if ($course = $DB->get_record('course', array('shortname' => $data['shortname']), '*', IGNORE_MULTIPLE)) {
            if (empty($data['id']) || $course->id != $data['id']) {
                $errors['shortname'] = get_string('shortnametaken', '', $course->fullname);
            }
        }

        if (!empty($data['externalurl'])) {
            $url = $data['externalurl'];
            if (preg_match('|^/|', $url)) {
                // links relative to server root are ok - no validation necessary
            } else if (preg_match('|^[a-z]+://|i', $url) or preg_match('|^https?:|i', $url) or preg_match('|^ftp:|i', $url)) {
                // normal URL
                if (!url_appears_valid_url($url)) {
                    $errors['externalurl'] = get_string('invalidurl', 'url');
                }
            } else if (preg_match('|^[a-z]+:|i', $url)) {
                // general URI such as teamspeak, mailto, etc. - it may or may not work in all browsers,
                // we do not validate these at all, sorry
            } else {
                // invalid URI, we try to fix it by adding 'http://' prefix,
                // relative links are NOT allowed because we display the link on different pages!
                if (!url_appears_valid_url('http://' . $url)) {
                    $errors['externalurl'] = get_string('invalidurl', 'url');
                }
            }
        }

        // Add validations for question upload file
        if (empty($data['newfile'])) {
            $errors['newfile'] = get_string('required');
            return $errors;
        }

        $files = $this->get_draft_files('newfile');
        if (count($files) < 1) {
            $errors['newfile'] = get_string('required');
            return $errors;
        }

        if (empty($data['format'])) {
            $errors['format'] = get_string('required');
            return $errors;
        }

        $formatfile = dirname(dirname(dirname(__FILE__))) . '/question/format/' . $data['format'] . '/format.php';
        if (!is_readable($formatfile)) {
            throw new moodle_exception('formatnotfound', 'question', '', $data['format']);
        }

        require_once($formatfile);

        $classname = 'qformat_' . $data['format'];
        $qformat = new $classname();

        $file = reset($files);
        if (!$qformat->can_import_file($file)) {
            $a = new stdClass();
            $a->actualtype = $file->get_mimetype();
            $a->expectedtype = $qformat->mime_type();
            $errors['newfile'] = get_string('importwrongfiletype', 'question', $a);
        }

        return $errors;
    }

}
