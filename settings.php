<?php

/**
 * Automater settings
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
defined('MOODLE_INTERNAL') || die;

global $CFG;

$automatercaps = array(
    'local/automater:backupimport',
    'local/automater:restorecourse'
);
$systemcontext = context_system::instance();
if (has_any_capability($automatercaps, $systemcontext)) {
    $ADMIN->add('root', new admin_category('automater', get_string('pluginname', 'local_automater')));
    $ADMIN->add('automater', new admin_externalpage('courseimport', get_string('courseimport', 'local_automater'), '/local/automater/backup.php'));
    $ADMIN->add('automater', new admin_externalpage('addacourse', get_string('addacourse', 'local_automater'), '/local/automater/add.php'));
    $ADMIN->add('automater', new admin_externalpage('automaterdocumentation', get_string('automaterdocumentation', 'local_automater'), '/local/automater/help.php'));
}
