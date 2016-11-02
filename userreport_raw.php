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

defined('MOODLE_INTERNAL') || die();

/**
 * direct log construction implementation
 */

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$startday = optional_param('startday', -1, PARAM_INT) ; // From (-1 is from course start).
$startmonth = optional_param('startmonth', -1, PARAM_INT) ; // From (-1 is from course start).
$startyear = optional_param('startyear', -1, PARAM_INT) ; // From (-1 is from course start).
$endday = optional_param('endday', -1, PARAM_INT) ; // To (-1 is till now).
$endmonth = optional_param('endmonth', -1, PARAM_INT) ; // To (-1 is from till now).
$endyear = optional_param('endyear', -1, PARAM_INT) ; // To (-1 is from till now).
$fromstart = optional_param('fromstart', 0, PARAM_INT) ; // Force reset to course startdate.
$from = optional_param('from', -1, PARAM_INT) ; // Alternate way of saying from when for XML generation.
$to = optional_param('to', -1, PARAM_INT) ; // Alternate way of saying to when for XML generation.

ini_set('memory_limit', '1024M');

// TODO : Secure userid access depending on proper capabilities.

// Calculate start time.

if ($from == -1) {
    // Maybe we get it from parameters.
    if ($startday == -1 || $fromstart) {
        $from = $course->startdate;
    } else {
        if ($startmonth != -1 && $startyear != -1) {
            $from = mktime(0, 0, 8, $startmonth, $startday, $startyear);
        } else {
            print_error('Bad start date');
        }
    }
}

if ($to == -1) {
    // Maybe we get it from parameters.
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

// Get data.

    $logs = use_stats_extract_logs($from, $to, $userid, $COURSE->id);
    $aggregate = use_stats_aggregate_logs($logs, 'module', $from, $to);

// Get results.

    $questionresults = compute_results($userid, $from, $to, 'training');
    $examresults = compute_results($userid, $from, $to, 'exam');

// Print result.

    $globalresults->items = $questionresults->items;
    $globalresults->from = 0 + $from;
    $globalresults->to = 0 + $to;
    $globalresults->done = $questionresults->done;
    $globalresults->elapsed = 0;
    $globalresults->events = 0;
    foreach ($aggregate as $classarray) {
        foreach ($classarray as $modulestat) {
            $globalresults->elapsed += $modulestat->elapsed;
            $globalresults->events += $modulestat->events;
        }
    }


    if ($output == 'html') {
        // Time period form.

        echo '<link rel="stylesheet" href="reports.css" type="text/css" />';

        include "selector_form.html";

        examtraining_reports_print_header_html($userid, $course->id, $globalresults);
        examtraining_reports_print_trainings_globals_html($userid, $course->id, $questionresults);
        examtraining_reports_print_times_html($userid, $globalresults);
        examtraining_reports_print_exams_summary_html($userid, $course->id, $examresults);
        examtraining_reports_print_trainings_html($userid, $course->id, $questionresults);
        examtraining_reports_print_exams_html($userid, $course->id, $examresults);
        examtraining_reports_print_assiduity_html($userid, $course->id, $questionresults, $examresults, $from, $to);
        examtraining_reports_print_modules_html($userid, $course->id, $questionresults);

        examtraining_reports_print_radar_html(@$questionresults->masteringdata, @$questionresults->masteringheaders);
        examtraining_reports_print_knowledge_covering_html($userid, $course->id, $from, $to);

    } else {

        $filename = 'examtraining_sessions_report_'.date('d-M-Y', time()).'.xls';
        $workbook = new MoodleExcelWorkbook("-");
        // Sending HTTP headers.
        $workbook->send($filename);

        // Preparing some formats.
        $xls_formats = examtraining_reports_xls_formats($workbook);
        $worksheet = examtraining_reports_init_worksheet($userid, $xls_formats, $workbook);
        $startrow = examtraining_reports_print_header_xls($worksheet, $userid, $course->id, $globalresults, $xls_formats);
        $startrow = examtraining_reports_print_trainings_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $questionresults);
        $startrow = examtraining_reports_print_exams_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $examresults);
        $startrow = examtraining_reports_print_assiduity_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $questionresults, $examresults, $from, $to);
        $startrow = examtraining_reports_print_modules_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $questionresults);

        ob_end_clean();
        $workbook->close();

    }
