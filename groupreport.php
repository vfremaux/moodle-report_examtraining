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

$input = examtraining_reports_input($course);

$page = 20;

// TODO : secure groupid access depending on proper capabilities.

// Pre print the group selector.
if ($output == 'html') {
    // Time and group period form.
    include($CFG->dirroot.'/report/examtraining/course_selector_form.html');
}

// Compute target group.

if ($groupid) {
    $targetusers = groups_get_members($groupid);
    $max = count($targetusers);
    $page = count($targetusers);
} else {
    $allusers = get_users_by_capability($context, 'moodle/course:view', 'u.id, '.get_all_user_name_fields(true, 'u'), 'lastname');
    $max = count($allusers);
    $fields = 'u.id, '.get_all_user_name_fields(true, 'u').', email, institution';
    $targetusers = get_users_by_capability($context, 'moodle/course:view', $fields, 'lastname', $offset, $page);
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

if ($output == 'html') {

    echo '<br/>';

    $params = array('id' => $id, 'view' => 'course_group', 'from' => $input->from, 'to' => $input->to, 'groupid' => $groupid, 'output' => 'html');
    $url = new moodle_url('/report/examtraining/index.php', $params);
    echo $renderer->pager($max, $offset, $page, $url);

    $reportcontext = examtraining_get_context();

    if (!empty($targetusers)) {

        foreach ($targetusers as $userid => $auser) {

            $logs = use_stats_extract_logs($input->from, $input->to, $userid, $course->id);
            $aggregate = use_stats_aggregate_logs($logs, 'module', $input->from, $input->to);

            $weeklogs = use_stats_extract_logs(time() - 7 * DAYSECS, time(), $userid, $course->id);
            $weekaggregate = use_stats_aggregate_logs($weeklogs, 'module', $input->from, $input->to);
    
            $userglobals = userquiz_get_user_globals(array_keys($targetusers), $reportcontext->trainingquizzes, $input->from, $input->to);

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
            echo $renderer->trainings_globals($auser->id, $input->from, $input->to, 'thin', $userglobals);
            echo $renderer->exams($auser->id, $input->from, $input->to);
        }
    }

    echo $renderer->pager($max, $offset, $page, $url);

    $options['id'] = $course->id;
    $options['groupid'] = $groupid;
    $options['from'] = $input->from; // Alternate way.
    $options['output'] = 'xls'; // Ask for XLS.
    $options['view'] = 'course_group'; // Force course view.
    echo '<center>';
    $buttonurl = new moodle_url('/report/examtraining/index.php');
    echo $OUTPUT->single_button($buttonurl, $options, get_string('generateXLS', 'report_examtraining'), 'get');
    echo '</center>';

} else {

    // Generate XLS.
    require_once($CFG->dirroot.'/report/examtraining/xlsrenderer.php');
    $xlsrenderer = new report_examtraining_xls_renderer();

    if ($groupid) {
        $filename = 'examtraining_group_'.$groupid.'_report_'.date('d-M-Y_h:m:s', time()).'.xls';
    } else {
        $filename = 'examtraining_course_'.$id.'_report_'.date('d-M-Y_h:m:s', time()).'.xls';
    }
    $workbook = new MoodleExcelWorkbook("-");

    // Sending HTTP headers.
    header('Content-Type:application/vnd.ms-excel');
    $workbook->send($filename);

    $xlsformats = examtraining_reports_xls_formats($workbook);
    $startrow = 0;

    $reportcontext = examtraining_get_context();

    $row = $startrow;
    $worksheet =& $workbook->add_worksheet('-');

    $xlsrenderer->globalheader($worksheet, $xlsformats, $row);
    $row++;

    if (!empty($targetusers)) {
        foreach ($targetusers as $auser) {

            if (has_capability('moodle/course:manageactivities', $context, $auser->id)) {
                continue;
            }

            // Get data.

            $logs = use_stats_extract_logs($input->from, $input->to, $auser->id, $COURSE->id);
            $aggregate = use_stats_aggregate_logs($logs, 'module', $input->from, $input->to);

            $weeklogs = use_stats_extract_logs(time() - (DAYSECS * 7), time(), $auser->id, $COURSE->id);
            $weekaggregate = use_stats_aggregate_logs($weeklogs, 'module', $input->from, $input->to);

            // Print result.

            $globalresults = userquiz_get_user_globals($auser->id, $reportcontext->trainingquizzes, $input->from, $input->to);
            $globalresults[$auser->id]->elapsed = 0;
            $globalresults[$auser->id]->weekelapsed = 0;

            foreach ($aggregate as $module => $classarray) {
                foreach ($classarray as $modulestat) {
                    $globalresults[$auser->id]->elapsed += $modulestat->elapsed;
                }
            }

            foreach ($weekaggregate as $classarray) {
                foreach ($classarray as $modulestat) {
                    $globalresults[$auser->id]->weekelapsed += $modulestat->elapsed;
                }
            }

            $xlsrenderer->globalrow($worksheet, $auser->id, $course->id, $globalresults[$auser->id], $xlsformats, $row);
        }
    }
    ob_end_clean();
    $workbook->close();
}

