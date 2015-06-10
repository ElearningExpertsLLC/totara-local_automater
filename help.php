<?php

/**
 * Automater course documentation
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

// HTTPS is required in this page when $CFG->loginhttps enabled.
$PAGE->https_required();
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/local/automater/help.php');

require_login();
$context = context_system::instance();
require_capability('local/automater:restorecourse', $context);
$PAGE->set_context($context);

// Make sure we really are on the https page when https login required.
$PAGE->verify_https_required();

$PAGE->set_title(get_string('documentationtitle', 'local_automater'));
$PAGE->set_heading($SITE->fullname);

// Display page header.
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('documentationtitle', 'local_automater'));

echo $OUTPUT->container(get_string('documentationpage', 'local_automater'));

// And proper footer.
echo $OUTPUT->footer();
