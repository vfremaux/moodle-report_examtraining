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
 * @package     report_examtraining
 * @category    report
 * @copyright   2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
 * This script handles the report generation in batch task for a single group.
 * It may produce a group csv report.
 * groupid must be provided.
 * This script should be sheduled in a redirect bouncing process for maintaining
 * memory level available for huge batches.
 */

require('../../../config.php');
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$id = required_param('id', PARAM_INT); // The course id.

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);

$input = examtraining_reports_input($course);
$groupid = required_param('groupid', PARAM_INT); // Group id.
$timesession = required_param('timesession', PARAM_INT); // Time of the generation batch.
$readabletimesession = date('Ymd_H_i_s', $timesession);
$sessionday = date('Ymd', $timesession);

ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities.

// Compute target group.

$group = $DB->get_record('groups', array('id' => $groupid));

$targetusers = groups_get_members($groupid);

// Filters teachers out.
foreach ($targetusers as $uid => $user) {
    if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
        unset($targetusers[$uid]);
    }
}

// Print result.

if (!empty($targetusers)) {

    $timestamp = time();

    report_compile_init_columns($resultset);

    // Add report columns for modules.
    for ($i = 1; $i < 10; $i++) {
        $resultset[] = "Q$i";
    }
    for ($i = 1; $i <= 10; $i++) {
        $resultset[] = "Q".($i * 10);
    }

    $rawfile = mb_convert_encoding(implode(';', $resultset)."\n", 'ISO-8859-1', 'UTF-8');

    $reportcontext = examtraining_get_context($course->id);

    global $COURSE;
    $COURSE->id = $course->id;

    foreach ($targetusers as $userid => $auser) {

        $logs = use_stats_extract_logs($input->from, $input->to, $auser->id, $COURSE->id);
        $aggregate = use_stats_aggregate_logs($logs, 'module', $input->from, $input->to);

        $weeklogs = use_stats_extract_logs($input->to - DAYSECS * 7, time(), $auser->id, $COURSE->id);
        $weekaggregate = use_stats_aggregate_logs($weeklogs, 'module', $input->from, $input->to);

        $logusers = $auser->id;
        $globalresults->elapsed = 0;
        if (isset($aggregate[$userid])) {
            foreach ($aggregate[$userid] as $classname => $classarray) {
                foreach ($classarray as $modid => $modulestat) {
                    $globalresults->elapsed += $modulestat->elapsed;
                }
            }
        }

        $globalresults->weekelapsed = 0;
        if (isset($weekaggregate[$userid])) {
            foreach ($weekaggregate[$userid] as $classarray) {
                foreach ($classarray as $modid => $modulestat) {
                    $globalresults->weekelapsed += $modulestat->elapsed;
                }
            }
        }

        examtraining_reports_print_globalheader_raw($auser->id, $course->id, $globalresults, $rawfile, $input->from, $input->to);
    }

    $fs = get_file_storage();

    $filerec = new StdClass;
    $filerec->context = $context->id;
    $filerec->component = 'report_examtraining';
    $filerec->filearea = 'reports';
    $filerec->path = '/autoreports/'.$sessionday.'/';
    $filerec->filename = 'examtraining_sessions_'.$group->name.'_'.$readabletimesession.'.csv';

    $fs->create_file_from_string($filrec, $rawfile);
}
