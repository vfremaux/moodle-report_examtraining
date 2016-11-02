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
 * @package     report_examtraining
 * @category    report
 * @copyright   2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/local/lib/batchlib.php');
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
include_once($CFG->dirroot.'/report/examtraining/locallib.php');

/**
 * pre worker hook
 */
function report_compile_users_preworker(&$context) {
    global $CFG;

    $logs = use_stats_extract_logs($context->from, $context->to, array_keys($context->sourcerecs), $context->course->id);
    $context->aggregate = use_stats_aggregate_logs($logs, 'module', $from, $to);

    $weeklogs = use_stats_extract_logs($context->to - DAYSECS * 7, time(), array_keys($context->sourcerecs), $context->course->id);
    $context->weekaggregate = use_stats_aggregate_logs($weeklogs, 'module', $from, $to);

    if (file_exists($CFG->dataroot.'/'.$context->course->id.'/'.$context->filename)) {
        $context->rawfile = fopen($CFG->dataroot.'/'.$context->course->id."/".$context->filename, 'ab');

        // When running just add rows.
    } else {

        // At first file openning.

        $context->rawfile = fopen($CFG->dataroot.'/'.$context->course->id."/".$context->filename, 'wb');

        report_compile_init_columns($resultset);

        // Add report columns for modules.
        for ($i = 1; $i < 10; $i++) {
            $resultset[] = "Q$i";
        }

        for ($i = 1; $i <= 10; $i++) {
            $resultset[] = "Q".($i * 10);
        }

        fputs($context->rawfile, mb_convert_encoding(implode(';', $resultset)."\n", 'ISO-8859-1', 'UTF-8'));

    }
}

/**
 * worker hook
 *
 */
function report_compile_users_worker($rec, &$context) {
    $logusers = $rec->id;

    $context->globalresults->elapsed = 0;
    $context->globalresults->weekelapsed = 0;
    if (isset($context->aggregate[$rec->id])) {
        foreach ($context->aggregate[$rec->id] as $classname => $classarray) {
            foreach ($classarray as $modid => $modulestat) {
                $context->globalresults->elapsed += $modulestat->elapsed;
            }
        }
    }

    if (isset($context->weekaggregate[$rec->id])) {
        foreach($context->weekaggregate[$rec->id] as $classarray) {
            foreach($classarray as $modid => $modulestat) {
                $context->globalresults->weekelapsed += $modulestat->elapsed;
            }
        }
    }

    examtraining_reports_print_globalheader_raw($rec->id, $context->course->id, $context->globalresults, $context->rawfile,
                                                $context->from, $context->to);
}

/**
 * post worker hook
 */
function report_compile_users_postworker(&$context) {
    fclose($context->rawfile);
}

function report_compile_init_columns(&$resultset) {
    $resultset[] = get_string('entity', 'report_examtraining'); // Group name.

    // User identity.
    $resultset[] = get_string('id', 'report_examtraining'); // Userid from user profile.
    $resultset[] = get_string('username'); // Username from user profile.
    $resultset[] = get_string('lastname', 'report_examtraining'); // User name.
    $resultset[] = get_string('firstname', 'report_examtraining'); // User name.

    // Key Dates.
    $resultset[] = get_string('firstenrolldate', 'report_examtraining'); // Enrol start date.
    $resultset[] = get_string('firstaccess', 'report_examtraining'); // First access to site in user profile.
    $resultset[] = get_string('lastaccess', 'report_examtraining'); // Last access to site in user profile.
    $resultset[] = get_string('startdate', 'report_examtraining'); // Compile start date.
    $resultset[] = get_string('todate', 'report_examtraining'); // Compile end date.
    $resultset[] = get_string('weekstartdate', 'report_examtraining'); // Last week start date.

    // Time spent.
    $resultset[] = get_string('timeelapsed', 'report_examtraining');
    $resultset[] = get_string('timeelapsedcurweek', 'report_examtraining');

    // Program outcomes.
    $resultset[] = get_string('aansweredquestions', 'report_examtraining');
    $resultset[] = get_string('aansweredquestionscurweek', 'report_examtraining');
    $resultset[] = get_string('cansweredquestions', 'report_examtraining');
    $resultset[] = get_string('cansweredquestionscurweek', 'report_examtraining');
    $resultset[] = get_string('ratioa', 'report_examtraining');
    $resultset[] = get_string('ratioacurweek', 'report_examtraining');
    $resultset[] = get_string('ratioc', 'report_examtraining');
    $resultset[] = get_string('ratioccurweek', 'report_examtraining');
    $resultset[] = get_string('examsuccess', 'report_examtraining');
    $resultset[] = get_string('examattempts', 'report_examtraining');
}