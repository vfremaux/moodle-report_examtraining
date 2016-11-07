<?php

defined('MOODLE_INTERNAL') || die();

/**
 * direct log construction implementation
 *
 */

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$startday = optional_param('startday', -1, PARAM_INT) ; // from (-1 is from course start)
$startmonth = optional_param('startmonth', -1, PARAM_INT) ; // from (-1 is from course start)
$startyear = optional_param('startyear', -1, PARAM_INT) ; // from (-1 is from course start)
$endday = optional_param('endday', -1, PARAM_INT) ; // to (-1 is till now)
$endmonth = optional_param('endmonth', -1, PARAM_INT) ; // to (-1 is from till now)
$endyear = optional_param('endyear', -1, PARAM_INT) ; // to (-1 is from till now)
$fromstart = optional_param('fromstart', 0, PARAM_INT) ; // force reset to course startdate
$from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying to when for XML generation

ini_set('memory_limit', '1024M');

// TODO : Secure userid access depending on proper capabilities

// calculate start time

if ($from == -1) { // maybe we get it from parameters
    if ($startday == -1 || $fromstart) {
        $from = $course->startdate;
    } else {
        if ($startmonth != -1 && $startyear != -1) {
            $from = mktime(0,0,8,$startmonth, $startday, $startyear);
        } else {
            print_error('Bad start date');
        }
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

// get data

    $logs = use_stats_extract_logs($from, $to, $userid, $COURSE->id);
    $aggregate = use_stats_aggregate_logs($logs, 'module', $from, $to);

// get results

    $questionresults = compute_results($userid, $from, $to, 'training');
    $examresults = compute_results($userid, $from, $to, 'exam');

// print result

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
        // time period form

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

        /*
        $options['id'] = $course->id;
        $options['userid'] = $userid;
        $options['from'] = $from; // alternate way
        $options['output'] = 'xls'; // ask for XLS
        $options['asxls'] = 'xls'; // force XLS for index.php
        echo '<center>';
        print_single_button($CFG->wwwroot.'/course/report/barchenamf2/index.php', $options, get_string('generateXLS', 'report_barchenamf2'), 'get');
        echo '</center>';
        */

    } else {

        $filename = 'examtraining_sessions_report_'.date('d-M-Y', time()).'.xls';
        $workbook = new MoodleExcelWorkbook("-");
        // Sending HTTP headers
        $workbook->send($filename);

        // preparing some formats
        $xls_formats = examtraining_reports_xls_formats($workbook);
        $worksheet = examtraining_reports_init_worksheet($userid, $xls_formats, $workbook);
        $startrow = examtraining_reports_print_header_xls($worksheet, $userid, $course->id, $globalresults, $xls_formats);
        $startrow = examtraining_reports_print_trainings_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $questionresults);
        $startrow = examtraining_reports_print_exams_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $examresults);
        $startrow = examtraining_reports_print_assiduity_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $questionresults, $examresults, $from, $to);
        $startrow = examtraining_reports_print_modules_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $questionresults);
        // $startrow = examtraining_reports_print_knowledge_covering_xls($worksheet, $startrow, $xls_formats, $USER->id, $course->id, $from, $to);

        ob_end_clean();
        $workbook->close();

    }
