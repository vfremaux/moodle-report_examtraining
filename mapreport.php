<?php

defined('MOODLE_INTERNAL') || die();

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
$groupid = optional_param('groupid', false, PARAM_INT) ; // admits special values : -1 current group, -2 course users
$output = optional_param('output', 'html', PARAM_ALPHA) ; // 'html' or 'xls'

$offset = optional_param('offset', 0, PARAM_INT);
$page = 20;

ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities

// calculate start time

if ($from == -1){ // maybe we get it from parameters
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
            $to = mktime(0, 0, 8, $endmonth, $endday, $endyear);
        } else {
            print_error('Bad end date');
        }
    }
}

// Pre print the group selector
// time and group period form
include($CFG->dirroot.'/report/examtraining/course_selector_form.html');

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
echo '<br/>';

if (!empty($targetusers)) {

    echo '<table width="800"><tr valign="top"><td width="80%">';
    $report_context = examtraining_get_context();
    $userglobals = userquiz_get_user_globals(array_keys($targetusers), $report_context->trainingquizzes, $from, $to);
    echo $renderer->coverage_vs_ratio($targetusers, $course->id, $from, $to, $userglobals);
    echo '</td><td width="20%">';
    $advicestr = get_string('jqplotzoomadvice', 'report_barchenamf3');
    echo "<span class=\"smalltext\">$advicestr</span>";
    echo '</td></tr></table>';
}
