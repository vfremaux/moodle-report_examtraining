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

defined('MOODLE_INTERNAL') || die;

/*
 * direct log construction implementation
 *
 */
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$input = examtraining_reports_input($course);
$input->orderby = optional_param('orderby', 'DESC', PARAM_ALPHA); // Ordering of the result ASC or DESC.
$input->num = optional_param('num', 15, PARAM_INT);
$input->subview = optional_param('subview', '', PARAM_TEXT);
$pagesize = 20;

ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities.

/*
 * Pre print the group selector
 * time and group period form
 */
$input->nousers = true;
echo $renderer->selectorform($course, $view, $input);

// Compute target group.

if ($groupid) {
    $targetusers = get_enrolled_users($context, '', $groupid, 'u.*', 'u.lastname, u.firstname', $input->offset, $pagesize, true);
    $max = count($targetusers);
    $pagesize = count($targetusers);
} else {
    $allusers = get_enrolled_users($context, '', 0, 'u.id', 'u.lastname, u.firstname', 0, 0, true);
    $max = count($allusers);
    $targetusers = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname, u.firstname', $input->offset, $pagesize, true);
}

// Filters teachers out.

if (!empty($targetusers)) {
    foreach ($targetusers as $uid => $user) {
        if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
            unset($targetusers[$uid]);
        }
    }
}

// Print result.

$template = new StdClass;
$template->formurl = new moodle_url('/report/examtraining/index.php');
$template->id = $id;
$template->from = $input->from;
$template->to = $input->to;
$template->groupid = $groupid;
$template->subview = $subview;
$template->toporderstr = get_string('toporder', 'report_examtraining');
$orderoptions = array('ASC' => get_string('ascending', 'report_examtraining'),
                      'DESC' => get_string('descending', 'report_examtraining'));
                      $attrs = array('onchange' => "document.forms['paramform'].submit()");
$template->orderselect = html_writer::select($orderoptions, 'orderby', $input->orderby, '', $attrs);

$template->toplengthstr = get_string('toplength', 'report_examtraining');
$lengthoptions = array('5' => '5', '10' => '10', '15' => '15', '20' => '20', '30' => '30', '40' => '40', '50' => '50');
$template->lengthselect = html_writer::select($lengthoptions, 'num', $input->num, '', array('onchange' => "document.forms['paramform'].submit()"));

echo $OUTPUT->render_from_template('report_examtraining/topoptionsform', $template);

$template = new StdClass;

if (!empty($targetusers)) {

    $template->hasusers = true;

    list($insql, $params) = $DB->get_in_or_equal(array_keys($targetusers));
    $examcontext = examtraining_get_context();

    $params1 = $params;
    $params1[] = $examcontext->examquiz;

    $sql = "
        SELECT
            userid,
            COUNT(*) attempts
        FROM
            {quiz_attempts} qa,
            {report_examtraining} ua
        WHERE
            qa.uniqueid = ua.uniqueid AND
            qa.userid $insql AND
            qa.quiz = ? AND
            qa.timefinish != 0
        GROUP BY
            qa.userid
        ORDER BY
            attempts {$input->orderby}
        LIMIT
            0, {$input->num}
    ";

    $topexams = $DB->get_records_sql($sql, $params1);

    list($qinsql, $qparams) = $DB->get_in_or_equal(array_keys($examcontext->trainingquizzes));

    $attemptsstr = get_string('attempts', 'report_examtraining');
    $userstr = get_string('user');

    $template->topexamshdr = get_string('topexams', 'report_examtraining');
    $template->notrainingactivity = $OUTPUT->notification(get_string('notrainingactivity', 'report_examtraining'));

    if ($topexams) {
        $template->hasexams = true;

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
        $template->topexamstable = html_writer::table($table);
    }

    // Toplist by questions and by coverage.
    $params2 = array_merge($params, $qparams);

    $sql = "
        SELECT
            userid,
            SUM(qcount) as qcount
        FROM
            {quiz_attempts} qa,
            {report_examtraining} ua
        WHERE
            qa.uniqueid = ua.uniqueid AND
            userid $insql AND
            qa.quiz $qinsql AND
            qa.timefinish != 0
        GROUP BY
            qa.userid
        ORDER BY
            qcount {$input->orderby}
        LIMIT
            0, {$input->num}
    ";

    $topquestions = $DB->get_records_sql($sql, $params2);

    $questionsstr = get_string('questions', 'report_examtraining');
    $coveragestr = get_string('coverageshort', 'report_examtraining');

    $template->topquestionshdr = get_string('topquestions', 'report_examtraining');

    if ($topquestions) {
        $template->hasquestions = true;
        $table = new html_table();
        $table->head = array("<b>$questionsstr</b>", "<b>$userstr</b>");
        $table->align = array('left', 'left');
        $table->size = array('10%', '90%');
        $table->width = '90%';
        foreach ($topquestions as $top) {
            $groups = examtraining_get_grouplist($id, $top->userid);
            $groupclause = ($groups) ? " ($groups) " : '';
            $userurl = new moodle_url('/user/view.php', array('id' => $top->userid));
            $userline = '<a href="'.$userurl.'">'.fullname($targetusers[$top->userid]).' '.$groupclause.'</a>';
            $table->data[] = array($top->qcount, $userline);
        }
        $template->topquestionstable = html_writer::table($table);
    }

    $params3 = $params;
    $params3[] = $examcontext->instanceid;

    $sql = "
        SELECT
            userid,
            coveragematched
        FROM
            {userquiz_monitor_user_stats} us
        WHERE
            userid $insql AND
            blockid = ?
        ORDER BY
            coveragematched {$input->orderby}
        LIMIT
            0, {$input->num}
    ";

    $topmatchedcoverage = $DB->get_records_sql($sql, $params3);

    $template->topcoveragehdr = get_string('topcoveragematched', 'report_examtraining');

    if ($topmatchedcoverage) {
        $table = new html_table();
        $table->head = array("<b>$coveragestr</b>", "<b>$userstr</b>");
        $table->align = array('left', 'left');
        $table->size = array('10%', '90%');
        $table->width = '90%';
        foreach ($topmatchedcoverage as $top) {
            $groups = examtraining_get_grouplist($id, $top->userid);
            $groupclause = ($groups) ? " ($groups) " : '';
            $userurl = new moodle_url('/user/view.php', array('id' => $top->userid));
            $userline = '<a href="'.$userurl.'">'.fullname($targetusers[$top->userid]).' '.$groupclause.'</a>';
            $table->data[] = array($top->coveragematched.' %', $userline);
        }
        $template->topcoveragetable = html_writer::table($table);
    }
} else {
    $template->nousersstr = $OUTPUT->notification(get_string('nousers', 'report_examtraining'));
}

echo $OUTPUT->render_from_template('report_examtraining/topreport', $template);
