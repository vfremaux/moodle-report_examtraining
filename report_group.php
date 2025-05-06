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
 * @package         report_examtraining
 * @category        report
 * @copyright       2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license         http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

/*
 * direct log construction implementation
 */

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/statscompilelib.php');
require_once($CFG->dirroot.'/report/examtraining/classes/output/htmlrenderer.php');
require_once($CFG->dirroot.'/report/examtraining/classes/output/xlsrenderer.php');

$input = examtraining_reports_input($course);
$input->num = optional_param('num', 0, PARAM_INT);
$input->orderby = optional_param('orderby', '', PARAM_TEXT);
$input->subview = optional_param('subview', '', PARAM_TEXT);
$input->offset = optional_param('offset', 0, PARAM_INT);
$input->groupid = optional_param('group', 0, PARAM_INT);
$pagesize = 20;

// TODO : secure groupid access depending on proper capabilities.

// Pre print the group selector.
if ($output == 'html') {
    // Time and group period form.
    $input->nousers = true; // Tells its a group selector.
    echo $renderer->selectorform($course, $view, $input);
}

// Compute target group.

if ($input->groupid) {
    $targetusers = get_enrolled_users($context, '', $input->groupid, 'u.*', 'u.lastname, u.firstname', $input->offset, $pagesize, true);
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

$compiler = new \report_examtraining\stats\compiler();

// Print result.

if ($output == 'html') {

    $htmlrenderer = $PAGE->get_renderer('report_examtraining', 'html');

    echo '<br/>';

    $params = array('id' => $id,
                    'view' => 'course_group',
                    'from' => $input->from,
                    'to' => $input->to,
                    'groupid' => $input->groupid,
                    'output' => 'html');
    $url = new moodle_url('/report/examtraining/index.php', $params);
    echo $renderer->pager($max, $input->offset, $pagesize, $url);

    $reportcontext = block_userquiz_monitor_get_block($course->id)->config;

    if (!empty($targetusers)) {

        foreach ($targetusers as $userid => $auser) {

            $logs = use_stats_extract_logs($input->from, $input->to, $userid, $course->id);
            $aggregate = use_stats_aggregate_logs($logs, $input->from, $input->to);

            $weeklogs = use_stats_extract_logs($input->to - 7 * DAYSECS, $input->to, $userid, $course->id);
            $weekaggregate = use_stats_aggregate_logs($weeklogs, $input->to - 7 * DAYSECS, $input->to, '', true);

            $userglobals = $compiler->get_user_globals(array_keys($targetusers), $course->id, $input->from, $input->to);

            $logusers = $userid;

            $globalresults = new StdClass();
            $globalresults->elapsed = 0;
            $globalresults->events = 0;
            $globalresults->weekelapsed = 0;
            $globalresults->weekevents = 0;

            if (isset($aggregate)) {
                $globalresults->elapsed = $aggregate['coursetotal'][$course->id]->elapsed ?? 0;
            }

            if (isset($weekaggregate)) {
                $globalresults->weekelapsed = $weekaggregate['coursetotal'][$course->id]->elapsed ?? 0;
            }

            $globalresults->linktousersheet = 1;
            echo $htmlrenderer->globalheader($auser->id, $course->id, $globalresults, true);
            echo $htmlrenderer->trainings_globals($auser->id, $input->from, $input->to, 'thin', $userglobals);
            echo $htmlrenderer->exams($auser->id, $input->from, $input->to);
        }
    }

    echo $renderer->pager($max, $input->offset, $pagesize, $url);

    $params = [
        'id' => $course->id,
        'group' => $input->groupid,
        'from' => $input->from,
        'output' => 'xls',
        'view' => 'group',
        'sesskey' => sesskey(),
    ];
    echo '<br/>';
    echo '<center>';
    $buttonurl = new moodle_url('/report/examtraining/index.php', $params);
    echo $OUTPUT->single_button($buttonurl, get_string('generatexls', 'report_examtraining'));
    echo '</center>';

} else {

    // Generate XLS.
    $xlsrenderer = $PAGE->get_renderer('report_examtraining', 'xls');

    if ($input->groupid) {
        $filename = 'examtraining_group_'.$input->groupid.'_report_'.date('d-M-Y_h:m:s', time()).'.xls';
    } else {
        $filename = 'examtraining_course_'.$id.'_report_'.date('d-M-Y_h:m:s', time()).'.xls';
    }
    $workbook = new MoodleExcelWorkbook("-");

    // Sending HTTP headers.
    header('Content-Type:application/vnd.ms-excel');
    $workbook->send($filename);

    $xlsformats = examtraining_reports_xls_formats($workbook);
    $startrow = 0;

    $reportcontext = block_userquiz_monitor_get_block($course->id)->config;

    $row = $startrow;
    $worksheet = &$workbook->add_worksheet('-');

    $xlsrenderer->globalheader($worksheet, $xlsformats, $row, $reportcontext);
    $row++;

    if (!empty($targetusers)) {
        foreach ($targetusers as $userid => $auser) {

            if (has_capability('moodle/course:manageactivities', $context, $userid)) {
                continue;
            }

            // Get data.

            $logs = use_stats_extract_logs($input->from, $input->to, $userid, $course->id);
            $aggregate = use_stats_aggregate_logs($logs, $input->from, $input->to);

            $weeklogs = use_stats_extract_logs(time() - (DAYSECS * 7), time(), $userid, $course->id);
            $weekaggregate = use_stats_aggregate_logs($weeklogs, $input->from, $input->to);

            // Print result.

            $userresults = $compiler->get_user_globals([$userid], $course->id, $input->from, $input->to);
            $userresults[$userid]->elapsed = 0;
            $userresults[$userid]->weekelapsed = 0;

            if (isset($aggregate)) {
                $userresults[$userid]->elapsed = $aggregate['coursetotal'][$course->id]->elapsed ?? 0;
            }

            if (isset($weekaggregate)) {
                $userresults[$userid]->weekelapsed = $weekaggregate['coursetotal'][$course->id]->elapsed ?? 0;
            }

            $xlsrenderer->globalrow($worksheet, $auser->id, $course->id, $userresults[$userid], $input->from, $input->to, $row, $reportcontext);
        }
    }
    ob_end_clean();
    $workbook->close();
}
