<?php

defined('MOODLE_INTERNAL') || die;

/**
 * direct log construction implementation
 *
 */

include_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
include_once $CFG->dirroot.'/report/examtraining/locallib.php';

$id = required_param('id', PARAM_INT) ; // the course id
$orderby = optional_param('orderby', 'DESC', PARAM_ALPHA) ; // ordering of the result ASC or DESC 
$num = optional_param('num', 15, PARAM_INT);
$startday = optional_param('startday', -1, PARAM_INT) ; // from (-1 is from course start)
$startmonth = optional_param('startmonth', -1, PARAM_INT) ; // from (-1 is from course start)
$startyear = optional_param('startyear', -1, PARAM_INT) ; // from (-1 is from course start)
$endday = optional_param('endday', -1, PARAM_INT) ; // to (-1 is till now)
$endmonth = optional_param('endmonth', -1, PARAM_INT) ; // to (-1 is till now)
$endyear = optional_param('endyear', -1, PARAM_INT) ; // to (-1 is till now)
$fromstart = optional_param('fromstart', 0, PARAM_INT) ; // force reset to course startdate
$from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying from when for XML generation

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

// Pre print the group selector
// time and group period form
include $CFG->dirroot."/report/examtraining/course_selector_form.html";

// compute target group

if ($groupid) {
    $targetusers = groups_get_members($groupid);
    $max = count($targetusers);
    $page = count($targetusers);
} else {
    $allusers = get_users_by_capability($context, 'moodle/course:view', 'u.id, '.get_all_user_name_fields(true, 'u'), 'lastname');
    $max = count($allusers);
    $targetusers = get_users_by_capability($context, 'moodle/course:view', 'u.id, '.get_all_user_name_fields(true, 'u').', email, institution', 'lastname');
}

// fitlers teachers out
if (!empty($targetusers)) {
    foreach ($targetusers as $uid => $user) {
        if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
            unset($targetusers[$uid]);
        }
    }
}

// print result
echo '<br/>';

echo '<form action="" method="get" name="paramform">';
echo '<input type="hidden" name="id" value="'.$id.'" />';
echo '<input type="hidden" name="from" value="'.$from.'" />';
echo '<input type="hidden" name="to" value="'.$to.'" />';
echo '<input type="hidden" name="groupid" value="'.$groupid.'" />';
echo '<input type="hidden" name="view" value="course_'.$page.'" />';
echo '<table width="100%"><tr valign="top"><td width="50%">';
print_string('toporder', 'report_examtraining');
$orderoptions = array('ASC' => get_string('ascending', 'report_examtraining'), 'DESC' => get_string('descending', 'report_examtraining'));
echo html_writer::select($orderoptions, 'orderby', $orderby, '', array('onchange' => "document.forms['paramform'].submit()"));
echo '</td><td width="50%">';
print_string('toplength', 'report_examtraining');
$lengthoptions = array('5' => '5', '10' => '10', '15' => '15', '20' => '20', '30' => '30', '40' => '40', '50' => '50');
echo html_writer::select($lengthoptions, 'num', $num, '', array('onchange' => "document.forms['paramform'].submit()"));
echo '</td></tr></table>';
echo '</form>';

if (!empty($targetusers)) {

    $targetuserlist = implode("','", array_keys($targetusers));
    $exam_context = examtraining_get_context();

    $sql = "
        SELECT
            userid,
            COUNT(*) attempts
        FROM
            {quiz_attempts} qa,
            {userquiz_attempts} ua
        WHERE
            qa.uniqueid = ua.uniqueid AND
            userid IN ('$targetuserlist') AND
            qa.quiz = {$exam_context->examquiz} AND
            qa.timefinish != 0
        GROUP BY
            ua.userid
        ORDER BY
            attempts $orderby
        LIMIT
            0,$num
    ";
    // echo ($sql);
    $topexams = $DB->get_records_sql($sql);

    $testquizzes = implode("','", array_keys($amf_context->trainingquizzes));
    
    echo '<center><table width="100%"><tr valign="top"><td width="100%" align="center">';

    $attemptsstr = get_string('attempts', 'report_examtraining');
    $userstr = get_string('user');

    if ($topexams) {
        $table = new html_table();
        $table->head = array("<b>$attemptsstr</b>", "<b>$userstr</b>");
        $table->align = array('left', 'left');
        $table->size = array('10%', '90%');
        $table->width = '90%';
        foreach ($topexams as $top) {
            $groups = examtraining_get_grouplist($id, $top->userid);
            $groupclause = ($groups) ? " ($groups) " : '';
            $userurl = new moodle_url('/user/view.php', array('id' => $top->userid));
            $userline = '<a href="'.$userurl.'">'.fullname($targetusers[$top->userid]).' '.$groupclause.'</a>';
            $table->data[] = array($top->attempts, $userline);
        }
        echo $OUTPUT->heading(get_string('topexams', 'report_examtraining'));
        echo html_writer::table($table);
        unset($table);
    }
    echo '</tr></table></center>';
    
    /// toplist by questions and by coverage

    $sql = "
        SELECT
            userid,
            SUM(qcount) as qcount
        FROM
            {quiz_attempts} qa,
            {userquiz_attempts} ua
        WHERE
            qa.uniqueid = ua.uniqueid AND
            userid IN ('$targetuserlist') AND
            qa.quiz IN('$testquizzes') AND
            qa.timefinish != 0
        GROUP BY
            qa.userid
        ORDER BY
            qcount $orderby
        LIMIT
            0,$num
    ";

    $topquestions = $DB->get_records_sql($sql);

    $sql = "
        SELECT
            userid,
            coveragematched
        FROM
            {userquiz_monitor_user_stats} us
        WHERE
            userid IN ('$targetuserlist') AND
            blockid = $amf_context->instanceid
        ORDER BY 
            coveragematched $orderby
        LIMIT
            0,$num
    ";

    // echo ($sql);
    $topmatchedcoverage = $DB->get_records_sql($sql);

    $questionsstr = get_string('questions', 'report_examtraining');
    $coveragestr = get_string('coverageshort', 'report_examtraining');

    echo '<center><table width="100%"><tr valign="top"><td width="50%" align="center">';

    echo $OUTPUT->heading(get_string('topquestions', 'report_examtraining'));
    $attemptsstr = get_string('questions', 'report_examtraining');
    $userstr = get_string('user');
    if ($topexams) {
        $table = new html_table();
        $table->head = array("<b>$questionsstr</b>", "<b>$userstr</b>");
        $table->align = array('left', 'left');
        $table->size = array('10%', '90%');
        $table->width = '90%';
        foreach ($topquestions as $top) {
            $groups = examtraining_get_grouplist($id, $top->userid);
            $groupclause = ($groups) ? " ($groups) " : '';
            $userurl = new moodle_url('/user/view.php', array('id' => $top->userid));
            $userline = "<a href="'.$userurl.'">".fullname($targetusers[$top->userid]).' '.$groupclause.'</a>';
            $table->data[] = array($top->qcount, $userline);
        }
        echo html_writer::table($table);
        unset($table);
    }

    echo '</td><td width="50%" align="center">';

    echo $OUTPUT->heading(get_string('topcoveragematched', 'report_examtraining'));

    if ($topmatchedcoverage) {
        $table = new html_table();
        $table->head = array("<b>$coveragestr</b>", "<b>$userstr</b>");
        $table->align = array('left', 'left');
        $table->size = array('10%', '90%');
        $table->width = '90%';
        foreach ($topmatchedcoverage as $top) {
            $groups = examtraining_get_grouplist($id, $top->userid);
            $groupclause = ($groups) ? " ($groups) " : '' ;
            $userline = "<a href=\"{$CFG->wwwroot}/user/view.php?id={$top->userid}\">".fullname($targetusers[$top->userid]).' '.$groupclause.'</a>';
            $table->data[] = array($top->coveragematched.' %', $userline);
        }
        echo html_writer::table($table);
        unset($table);
    }

    echo '</td></tr></table></center>';
}
