<?php

/**
 * Automater lang file
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
// Plugin settings.
$string['pluginname'] = 'Automater';
$string['newfile'] = 'Quiz import';
$string['newfile_help'] = 'Quiz import accept the text file with aiken format';
$string['courseimport'] = 'Course import';
$string['addacourse'] = 'Add a course';
$string['nofileavailable'] = 'Please upload the Course backup file and than try restore';
$string['aiken'] = 'aiken';
$string['invalidbackupcourse'] = 'Invalid backup course: Only one url, one quiz and two label activtiy should be present in first section';
$string['quizname'] = 'Quiz - {$a} Training';
$string['restoreexecutionsuccess'] = 'The course was restored successfully';
$string['automater:backupimport'] = 'Import course backup';
$string['automater:restorecourse'] = 'Restore automater course';
$string['automaterdocumentation'] = 'Documentation';
$string['documentationtitle'] = 'Course backup template requirements';
$string['uploadsuccess'] = 'Automater course backup uploaded successfully';
$string['documentationpage'] = 'Standard backup course first section layout and modifications take place during the course restore are given below:-
    <ul>	
        <li>Standard settings, need to be preset in the template    
            <ul>
                <li>Course must be in topics format</li>
                <li>2 sections</li>
            </ul>
        </li>
        <li>Topic1 (name managed per template import file): "Training Course"
            <ul>
                <li>URL Activity	
                    <ul>
                        <li>Name - Course template backup has URL activity with any name and that name is replaced with course fullname. Url view will open in new window.</li>
                        <li>Description - contains a standard description per the template (no edit needed by wizard)</li>
                        <li>External URL - modified by automater after template/backup import</li>
                        <li>All other settings should be preset in the template</li>
                        </li>
                    </ul>
                </li>
            </ul>

            <ul>
                <li>Label Activity
                    <ul>
                        <li>Name - the name of the label activity will be same as of backup course label activity</li>
                    </ul>
                </li>
            </ul>

            <ul>
                <li>Quiz Activity
                    <ul>
                        <li>Name - the name of the quiz activity is modified as "Quiz - {{coursefullname}} Training"</li>
                        <li>The quiz description is not modified by the automater, it is part of the course backup template</li>
                        <li>Restoration will ensure that activity completion setting for this quiz is only set to "Student must receive a grade to complete this activity"</li>
                        <li>All other quiz settings are part of the course template</li>
                        <li>Only aiken format file is accepted for bulk questions upload file. “Grade to pass” info under Gradebook
                        and the “maximum grade” info under quiz is automatically set equal to the number of quiz questions during restore</li>
                    </ul>
                </li>
            </ul>

            <ul>
                <li>Label Activity
                    <ul>
                        <li>This label content is part of the course backup template, would be imported with the template</li>
                        <li>The "Restrict Access" settings is automatically set to - Restricted (completely hidden, no message) until quiz activity is completed and
                        must be complete with pass grade</li>
                    </ul>
                </li>
            </ul>
        </li>
    </ul>
';
