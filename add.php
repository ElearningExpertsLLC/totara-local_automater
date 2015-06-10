<?php

/**
 * Automater course create
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
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/automater/add_form.php');
require_once($CFG->dirroot . '/mod/quiz/editlib.php');
require_once($CFG->dirroot . '/local/automater/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/question/format.php');

// HTTPS is required in this page when $CFG->loginhttps enabled.
$PAGE->https_required();
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/local/automater/add.php');
$returnurl = new moodle_url('/local/automater/add.php');

require_login();
$context = context_system::instance();
require_capability('local/automater:restorecourse', $context);
$PAGE->set_context($context);

// Make sure we really are on the https page when https login required.
$PAGE->verify_https_required();

$PAGE->set_title(get_string("addnewcourse"));
$PAGE->set_heading($SITE->fullname);

// Display page header.
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string("addnewcourse"));

$mform = new course_automater_form(null, array('contextid' => $context->id, 'format' => get_string('aiken', 'local_automater')));

$filestorage = get_file_storage();
$files = $filestorage->get_area_files($context->id, 'local_automater', 'course_backup', false, '', false);
$file = current($files);

$courseid = 0;
if ($data = $mform->get_data()) {
    $courseid = local_automater_restore($mform, $data, $file);
}

$fileurl = '';
if ($courseid) {
    $viewurl = new moodle_url('/course/view.php', array('id' => $courseid));
    echo $OUTPUT->notification(get_string('restoreexecutionsuccess', 'backup'), 'notifysuccess');
    echo html_writer::link($viewurl, $viewurl);
    echo $OUTPUT->continue_button($viewurl);
} else if ($file) {
    $fileurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename(), true);
    $fileurl = $fileurl->out(false);
    echo html_writer::link($fileurl, $file->get_filename());
    // Finally display the form.
    $mform->display();
} else {
    echo html_writer::tag('p', get_string('nofileavailable', 'local_automater'));
}

// And proper footer.
echo $OUTPUT->footer();
