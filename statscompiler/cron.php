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
 *
 * @package     report_examtraining
 * @subpackage  report
 * @author      Valery Fremaux <valery.fremaux@club-internet.fr>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright   (C) 2016 onwards Valery Fremaux
 */

require('../../../config.php');

require_once($CFG->dirroot.'/report/examtraining/statscompilelib.php');

@raise_memory_limit('512M');
@set_time_limit(1800);

$CFG->trace = $CFG->dataroot.'/userquiz_cron_compile.log';

$attempts = userquiz_cron_results();

$admin = get_admin();

email_to_user($admin, $admin, $SITE->fullname." : Userquiz Statcompilation : $attempts attempts compiled", 'Done.', 'Done.');
