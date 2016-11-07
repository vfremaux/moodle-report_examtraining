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
 * This file contains functions used by the examtraining report
 *
 * @package    report
 * @subpackage examtraining
 * @copyright  2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');

require_once($CFG->dirroot.'/report/examtraining/reportasyncprecompilelib.php');

$CFG->trace = $CFG->dataroot.'/report_cron_compile.log';

@raise_memory_limit('512M');
@set_time_limit(1800);

global $COURSE;

$courseid = required_param('course', PARAM_INT);

if (!$batchcontext->course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('coursemisconf');
}

$COURSE = $batchcontext->course;

// Just for code reuse. We don'nt use any form.
$input = examtraining_reports_input($batchcontext->course);

$batchcontext->from = $input->from;
$batchcontext->to = $input->to;

$filename = optional_param('filename', '', PARAM_TEXT);

$context = context_course::instance($courseid);

// Could be captured by batch function.
$limit = optional_param('limit', 20, PARAM_INT);
if ($limit) {
    $start = (0 + @$CFG->runs) * $limit;
    $fields = 'u.id, '.get_all_user_name_fields(true, 'u').', email, institution';
    $batchcontext->sourcerecs = get_users_by_capability($context, 'moodle/course:view', $fields, 'lastname', $start, $limit);
    $reccount = count($batchcontext->sourcerecs);
} else {
    $fields = 'u.id, '.get_all_user_name_fields(true, 'u').', email, institution';
    $batchcontext->sourcerecs = get_users_by_capability($context, 'moodle/course:view', $fields, 'lastname');
}

// We make the filename once, and then reopen each time.
$timestamp = time();
$batchcontext->filename = (empty($filename)) ? "examtraining_fullraw_{$timestamp}.csv" : $filename;

batch('report_compile_users_preworker', 'report_compile_users_worker', 'report_compile_users_postworker', 'user',
      ' 1 ', $batchcontext);

