<?php

/**
 * Automater library API
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

/**
 * Perform course restore
 * 
 * @global object $CFG
 * @param \moodleform $mform
 * @param object $data
 * @param object $file
 * @return int restored courseid
 */
function local_automater_restore(\moodleform $mform, $data, $file) {
    global $CFG;

    // Check access and enable avalability setting
    if (!$CFG->enableavailability) {
        set_config('enableavailability', 1);
    }

    // Enable new window displat option for url activties
    $urldisplay = get_config('url', 'displayoptions');
    $urlnewwindow = RESOURCELIB_DISPLAY_NEW;
    if (strpos((string) $urldisplay, (string) $urlnewwindow) === false) {
        $addnewwindow = $urldisplay . ',' . $urlnewwindow;
        set_config('displayoptions', $addnewwindow, 'url');
    }

    // Actual restore performed
    $resotre = new \local_automater\autorestore_ui($mform, $data, $file);
    $courseid = $resotre->process();

    return $courseid;
}

/**
 * Make the file workable which is uploaded through this plugin 
 * 
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param array $filearea
 * @param array $args
 * @param array $forcedownload
 * @param array $options
 * @return boolean
 */
function local_automater_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.
    // Use the itemid to retrieve any relevant data records and perform any security checks to see if the
    // user really does have access to the file in question.
    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/'; // $args is empty => the path is '/'
    } else {
        $filepath = '/' . implode('/', $args) . '/'; // $args contains elements of the filepath
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_automater', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser and no filtering.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
