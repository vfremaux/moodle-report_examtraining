<?php

defined('MOODLE_INTERNAL') || die();

/**
 * direct log construction implementation
 *
 */

include_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
include_once($CFG->dirroot.'/report/examtraining/locallib.php');

$startday = optional_param('startday', -1, PARAM_INT) ; // from (-1 is from course start)
$startmonth = optional_param('startmonth', -1, PARAM_INT) ; // from (-1 is from course start)
$startyear = optional_param('startyear', -1, PARAM_INT) ; // from (-1 is from course start)
$endday = optional_param('endday', -1, PARAM_INT) ; // to (-1 is till now)
$endmonth = optional_param('endmonth', -1, PARAM_INT) ; // to (-1 is from till now)
$endyear = optional_param('endyear', -1, PARAM_INT) ; // to (-1 is from till now)
$fromstart = optional_param('fromstart', 0, PARAM_INT) ; // force reset to course startdate
$from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying to when for XML generation
$userid = optional_param('userid', $USER->id, PARAM_INT) ; // admits special values : -1 current group, -2 course users
$output = optional_param('output', 'html', PARAM_ALPHA) ; // 'html' or 'xls'    

ini_set('memory_limit', '1024M');

// TODO : Secure userid access depending on proper capabilities

// in that case we cannot go out our account scope
if (!has_capability('report/examtraining:viewall', $context)) {
    $userid = $USER->id;
}

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

if ($to == -1) { // maybe we get it from parameters
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

// print result

$globalresults = new StdClass;
$globalresults->from = 0 + $from;
$globalresults->to = 0 + $to;
$globalresults->elapsed = 0;
$globalresults->events = 0;

foreach ($aggregate as $module => $classarray) {
    foreach ($classarray as $modulestat) {
        $globalresults->elapsed += 0 + @$modulestat->elapsed;
        $globalresults->events += 0 + @$modulestat->events;
    }
}

if ($output == 'html') {
    // time period form

    include "selector_form.html";

    $renderer = $PAGE->get_renderer('report_examtraining');

    examtrainings_reports_print_header_html($userid, $course->id, $globalresults);
    $stats = null;
    examtrainings_reports_print_trainings_globals_html($userid, $from, $to, 'large', $stats);
    examtrainings_reports_print_times_html($userid, $globalresults);
    // examtrainings_reports_print_exams_summary_html($userid, $from, $to);
    examtrainings_reports_print_trainings_html($userid, $from, $to);
    examtrainings_reports_print_trainings_subcats_html($userid, $from, $to);
    examtrainings_reports_print_exams_html($userid, $from, $to);
    examtrainings_reports_print_assiduity2_html($userid, $from, $to);
    examtrainings_reports_print_modules_html($userid, $from, $to);

    //
    examtrainings_reports_print_radar_html($userid, $from, $to);
    // examtrainings_reports_print_knowledge_covering_html($userid, $course->id, $from, $to);

} else {
    // $CFG->trace = $CFG->dataroot.'/xlsreport.log';

    $filename = 'examtraining_sessions_report_'.date('d-M-Y', time()).'.xls';
    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers
    $workbook->send($filename);

    // preparing some formats
    $xls_formats = examtrainings_reports_xls_formats($workbook);
    $worksheet = examtrainings_reports_init_worksheet($userid, $xls_formats, $workbook);
    $startrow = examtrainings_reports_print_header_xls($worksheet, $userid, $course->id, $globalresults, $xls_formats);
    $startrow = examtrainings_reports_print_trainings_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $questionresults);
    $startrow = examtrainings_reports_print_exams_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $examresults);
    $startrow = examtrainings_reports_print_assiduity_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $questionresults, $examresults, $from, $to);
    $startrow = examtrainings_reports_print_modules_xls($worksheet, $startrow, $xls_formats, $userid, $course->id, $questionresults);
    // $startrow = examtrainings_reports_print_knowledge_covering_xls($worksheet, $startrow, $xls_formats, $USER->id, $course->id, $from, $to);

    ob_end_clean();
    $workbook->close();

}

