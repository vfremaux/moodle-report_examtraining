<?php

defined('MOODLE_INTERNAL') || die;

/**
 * direct log construction implementation
 *
 */

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

$offset = optional_param('offset', 0, PARAM_INT);    
$page = 20;

// TODO : secure groupid access depending on proper capabilities

// calculate start time

if ($from == -1) { // maybe we get it from parameters
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

// Pre print the group selector
if ($output == 'html') {
    // time and group period form
    include "course_selector_form.html";
}

// compute target group

if ($groupid) {
    $targetusers = groups_get_members($groupid);
    $max = count($targetusers);
    $page = count($targetusers);
} else {
    $allusers = get_users_by_capability($context, 'moodle/course:view', 'u.id, '.get_all_user_name_fields(true, 'u'), 'lastname');
    $max = count($allusers);
    $targetusers = get_users_by_capability($context, 'moodle/course:view', 'u.id, '.get_all_user_name_fields(true, 'u').', email, institution', 'lastname', $offset, $page);
}

// fitlers teachers out
if (!empty($targetusers)) {
    foreach($targetusers as $uid => $user) {
        if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
            unset($targetusers[$uid]);
        }
    }
}

// print result

if ($output == 'html') {

    echo '<br/>';

    $url = new moodle_url('/report/examtraining/index.php', array('id' => $id, 'view' => 'course_group', 'from' => $from, 'to' => $to, 'groupid' => $groupid, 'output' => 'html'));
    echo $renderer->pager($max, $offset, $page, $url);

    $report_context = examtraining_get_context();

    if (!empty($targetusers)) {

        foreach ($targetusers as $userid => $auser) {

            $logs = use_stats_extract_logs($from, $to, $userid, $course->id);
            $aggregate = use_stats_aggregate_logs($logs, 'module', $from, $to);

            $weeklogs = use_stats_extract_logs(time() - 7 * DAYSECS, time(), $userid, $course->id);
            $weekaggregate = use_stats_aggregate_logs($weeklogs, 'module', $from, $to);
    
            $userglobals = userquiz_get_user_globals(array_keys($targetusers), $report_context->trainingquizzes, $from, $to);

            $logusers = $auser->id;

            $globalresults->elapsed = 0;
            $globalresults->events = 0;
            $globalresults->weekelapsed = 0;
            $globalresults->weekevents = 0;

            if (!empty($aggregate[$userid])) {
                foreach ($aggregate[$userid] as $module => $classarray) {
                    foreach ($classarray as $modulestat) {
                        $globalresults->elapsed += $modulestat->elapsed;
                        $globalresults->events += $modulestat->events;
                    }
                }
            }

            if (!empty($weekaggregate[$userid])) {
                foreach ($weekaggregate[$userid] as $classarray) {
                    foreach ($classarray as $modulestat) {
                        $globalresults->weekelapsed += $modulestat->elapsed;
                        $globalresults->weekevents += $modulestat->events;
                    }
                }
            }

            $gobalresults->linktousersheet = 1;
            echo $renderer->globalheader($auser->id, $course->id, $globalresults, true);
            echo $renderer->trainings_globals($auser->id, $from, $to, 'thin', $userglobals);
            echo $renderer->exams($auser->id, $from, $to);
        }
    }

    echo $renderer->pager($max, $offset, $page, $url);

    $options['id'] = $course->id;
    $options['groupid'] = $groupid;
    $options['from'] = $from; // alternate way
    $options['output'] = 'xls'; // ask for XLS
    $options['view'] = 'course_group'; // force course view
    echo '<center>';
    echo $OUTPUT->single_button(new moodle_url('/report/examtraining/index.php'), $options, get_string('generateXLS', 'report_examtraining'), 'get');
    echo '</center>';

} else {

    /// generate XLS
    require_once($CFG->dirroot.'/report/examtraining/xlsrenderer.php');
    $xlsrenderer = new report_examtraining_xls_renderer();

    if ($groupid) {
        $filename = 'examtraining_group_'.$groupid.'_report_'.date('d-M-Y_h:m:s', time()).'.xls';
    } else {
        $filename = 'examtraining_course_'.$id.'_report_'.date('d-M-Y_h:m:s', time()).'.xls';
    }
    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers
    header('Content-Type:application/vnd.ms-excel');
    $workbook->send($filename);

    $xls_formats = examtraining_reports_xls_formats($workbook);
    $startrow = 0;

    $report_context = examtraining_get_context();

    $row = $startrow;
    $worksheet =& $workbook->add_worksheet('-');

    $xlsrenderer->globalheader($worksheet, $xls_formats, $row);
    $row++;

    if (!empty($targetusers)) {
        foreach ($targetusers as $auser) {

            if (has_capability('moodle/course:manageactivities', $context, $auser->id)) {
                continue;
            }

            // get data

            $logs = use_stats_extract_logs($from, $to, $auser->id, $COURSE->id);
            $aggregate = use_stats_aggregate_logs($logs, 'module', $from, $to);

            $weeklogs = use_stats_extract_logs(time() - (DAYSECS * 7), time(), $auser->id, $COURSE->id);
            $weekaggregate = use_stats_aggregate_logs($weeklogs, 'module', $from, $to);

            // print result

            $globalresults = userquiz_get_user_globals($auser->id, $report_context->trainingquizzes, $from, $to);
            $globalresults[$auser->id]->elapsed = 0;
            $globalresults[$auser->id]->weekelapsed = 0;

            foreach ($aggregate as $module => $classarray) {
                foreach ($classarray as $modulestat) {
                    // echo "$module : $modulestat->elapsed <br/>";
                    $globalresults[$auser->id]->elapsed += $modulestat->elapsed;
                }
            }

            foreach ($weekaggregate as $classarray) {
                foreach ($classarray as $modulestat) {
                    $globalresults[$auser->id]->weekelapsed += $modulestat->elapsed;
                }
            }

            $xlsrenderer->globalrow($worksheet, $auser->id, $course->id, $globalresults[$auser->id], $xls_formats, $row);
        }
    }
    ob_end_clean();
    $workbook->close();
}

