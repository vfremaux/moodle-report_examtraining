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

/**
* This script handles the report generation in batch task for a single group. 
* It may produce a group csv report.
* groupid must be provided. 
* This script should be sheduled in a redirect bouncing process for maintaining
* memory level available for huge batches. 
*/

require('../../../config.php');
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$id = required_param('id', PARAM_INT) ; // the course id
$startday = optional_param('startday', -1, PARAM_INT) ; // from (-1 is from course start)
$startmonth = optional_param('startmonth', -1, PARAM_INT) ; // from (-1 is from course start)
$startyear = optional_param('startyear', -1, PARAM_INT) ; // from (-1 is from course start)
$endday = optional_param('endday', -1, PARAM_INT) ; // to (-1 is till now)
$endmonth = optional_param('endmonth', -1, PARAM_INT) ; // to (-1 is till now)
$endyear = optional_param('endyear', -1, PARAM_INT) ; // to (-1 is till now)
$fromstart = optional_param('fromstart', 0, PARAM_INT) ; // force reset to course startdate
$from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$groupid = required_param('groupid', PARAM_INT) ; // group id
$timesession = required_param('timesession', PARAM_INT) ; // time of the generation batch
$readabletimesession = date('Ymd_H_i_s', $timesession);
$sessionday = date('Ymd', $timesession);

ini_set('memory_limit', '2048M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);

// TODO : secure groupid access depending on proper capabilities

// calculate start time

if ($from == -1) { // maybe we get it from parameters
    if ($startday == -1 || $fromstart) {
        $from = $course->startdate;
    } else {
        if ($startmonth != -1 && $startyear != -1)
            $from = mktime(0, 0, 8, $startmonth, $startday, $startyear);
        else 
            print_error('Bad start date');
    }
}

if ($to == -1){ // maybe we get it from parameters
    if ($endday == -1) {
        $to = time();
    } else {
        if ($endmonth != -1 && $endyear != -1) {
            $to = mktime(0,0,8,$endmonth, $endday, $endyear);
        } else { 
            print_error('Bad end date');
        }
    }
}

// compute target group

$group = $DB->get_record('groups', array('id' => $groupid));

$targetusers = groups_get_members($groupid);

// filters teachers out
foreach ($targetusers as $uid => $user) {
    if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
        unset($targetusers[$uid]);
    }
}

// print result

if (!empty($targetusers)) {

    $timestamp = time();
    $resultset[] = get_string('entity', 'report_barchenamf3'); // groupname
    $resultset[] = get_string('id', 'report_barchenamf3'); // userid
    $resultset[] = get_string('firstenrolldate', 'report_barchenamf3'); // enrol start date
    $resultset[] = get_string('firstaccess', 'report_barchenamf3'); // fist trace
    $resultset[] = get_string('lastaccess', 'report_barchenamf3'); // last trace
    $resultset[] = get_string('startdate', 'report_barchenamf3'); // compile start date
    $resultset[] = get_string('todate', 'report_barchenamf3'); // compile end date
    $resultset[] = get_string('weekstartdate', 'report_barchenamf3'); // last week start date 
    $resultset[] = get_string('lastname', 'report_barchenamf3'); // user name 
    $resultset[] = get_string('firstname', 'report_barchenamf3'); // user name 
    $resultset[] = get_string('timeelapsed', 'report_barchenamf3');
    $resultset[] = get_string('timeelapsedcurweek', 'report_barchenamf3');
    $resultset[] = get_string('aansweredquestions', 'report_barchenamf3');
    $resultset[] = get_string('aansweredquestionscurweek', 'report_barchenamf3');
    $resultset[] = get_string('cansweredquestions', 'report_barchenamf3');
    $resultset[] = get_string('cansweredquestionscurweek', 'report_barchenamf3');
    $resultset[] = get_string('ratioa', 'report_barchenamf3');
    $resultset[] = get_string('ratioacurweek', 'report_barchenamf3');
    $resultset[] = get_string('ratioc', 'report_barchenamf3');
    $resultset[] = get_string('ratioccurweek', 'report_barchenamf3');
    $resultset[] = get_string('examsuccess', 'report_barchenamf3');
    $resultset[] = get_string('examattempts', 'report_barchenamf3');

    // add report columns for modules
    for ($i = 1; $i < 10 ; $i++) {
        $resultset[] = "Q$i";
    }
    for ($i = 1; $i <= 10 ; $i++) {
        $resultset[] = "Q".($i*10);
    }

    $rawfile = mb_convert_encoding(implode(';', $resultset)."\n", 'ISO-8859-1', 'UTF-8'));

    $report_context = examtraining_get_context($course->id);

    global $COURSE;
    $COURSE->id = $course->id;

    foreach ($targetusers as $userid => $auser) {

        $logs = use_stats_extract_logs($from, $to, $auser->id, $COURSE->id);
        $aggregate = use_stats_aggregate_logs($logs, 'module', $from, $to);

        $weeklogs = use_stats_extract_logs($to - DAYSECS * 7, time(), $auser->id, $COURSE->id);
        $weekaggregate = use_stats_aggregate_logs($weeklogs, 'module', $from, $to);

        $logusers = $auser->id;
        $globalresults->elapsed = 0;
        if (isset($aggregate[$userid])) {
            foreach ($aggregate[$userid] as $classname => $classarray) {
                foreach ($classarray as $modid => $modulestat) {
                    // echo "$classname elapsed : $modulestat->elapsed <br/>";
                    // echo "$classname events : $modulestat->events <br/>";
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

        examtraining_reports_print_globalheader_raw($auser->id, $course->id, $globalresults, $rawfile, $from, $to);
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
