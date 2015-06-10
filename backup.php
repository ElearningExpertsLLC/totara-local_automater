<?php

/**
 * Import course backup file
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
require_once($CFG->dirroot . '/local/automater/backupfile_form.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

// HTTPS is required in this page when $CFG->loginhttps enabled.
$PAGE->https_required();

$url = new moodle_url('/local/automater/backup.php');
$PAGE->set_url($url);
require_login();
$context = context_system::instance();
$PAGE->set_context($context);

// Make sure we really are on the https page when https login required.
$PAGE->verify_https_required();

$PAGE->set_title(get_string('course'));
$PAGE->set_heading(get_string('course'));
$PAGE->set_pagelayout('admin');

require_capability('local/automater:backupimport', $context);

// Includes backup form
$mform = new course_automater_backupfile_form(null, array('contextid' => $context->id));

$backupfile = new stdClass;
$backupfile->id = 0;
$draftitemid = file_get_submitted_draft_itemid('course_backup');
file_prepare_draft_area($draftitemid, $context->id, 'local_automater', 'course_backup', $backupfile->id, array('maxfiles' => 1));
$backupfile->course_backup = $draftitemid;

$mform->set_data($backupfile);

if ($data = $mform->get_data()) {
    file_save_draft_area_files($data->course_backup, $context->id, 'local_automater', 'course_backup', $backupfile->id, array('maxfiles' => 1));
    totara_set_notification(get_string('uploadsuccess', 'local_automater'), $url, array('class'=>'notifysuccess'));
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('importfile', 'backup'));
$mform->display();

echo $OUTPUT->footer();
