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
 * @package     report_examtraining
 * @category    report
 * @copyright   2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/*
 * direct log construction implementation
 *
 */
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/compatlib.php');
require_once($CFG->dirroot.'/report/examtraining/reportasyncprecompilelib.php');
require_once($CFG->dirroot.'/report/examtraining/classes/output/rawrenderer.php');

$input = examtraining_reports_input($course);
$groupid = optional_param('group', false, PARAM_INT);

$page = 20;

// TODO : secure groupid access depending on proper capabilities.


/*
 * Pre print the group selector
 * time and group period form
 */
$input->nousers = true;
echo $renderer->selectorform($course, $view, $input);

$rawrenderer = $PAGE->get_renderer('report_examtraining', 'raw');

// Compute target group.

if ($groupid) {
    $targetusers = groups_get_members($groupid);
} else {
    $fields = 'u.id, '.\report_examtraining\compat::get_user_fields().', email, institution';
    $targetusers = get_users_by_capability($context, 'moodle/course:view', $fields, 'lastname');

    if (count($targetusers) > 100) {
        echo $OUTPUT->notification('Course is too large. Choosing a group');
        $groupid = $defaultgroup; // Defined in courseraw_selector_form.html.
        // DO NOT COMPILE.
        if ($groupid == 0) {
            echo $OUTPUT->notification('Course is too large and no groups in. Cannot compile.');
            echo $OUTPUT->footer();
        }
        $targetusers = groups_get_members($groupid);
    }
}
$input->nousers = count($targetusers) > 0;

// Fitlers teachers out.
foreach ($targetusers as $uid => $user) {
    if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
        unset($targetusers[$uid]);
    }
}

// Print result.

if (!empty($targetusers)) {

    echo 'Compiling for '.count($targetusers).' users<br/>';

    $timestamp = time();

    report_compile_init_columns($resultset);

    // Add report columns for modules.
    for ($i = 1; $i < 10; $i++) {
        $resultset[] = "Q$i";
    }

    for ($i = 1; $i <= 10; $i++) {
        $resultset[] = "Q".($i * 10);
    }

    $resultset[] = get_string('dateofbirth', 'report_examtraining'); // DOB.
    $resultset[] = get_string('placeofbirth', 'report_examtraining'); // POB.
    $resultset[] = get_string('c3', 'report_examtraining'); // C3.

    $rawfile = implode(';', $resultset)."\n";

    $examtrainingcontext = block_userquiz_monitor_get_block($COURSE->id)->config;

    foreach ($targetusers as $uid => $auser) {
        $logs = use_stats_extract_logs($input->from, $input->to, $uid, $COURSE->id);
        echo 'Logs extracted. Mem state : '.memory_get_usage().'<br/>';
        $aggregate = use_stats_aggregate_logs($logs, $input->from, $input->to);
        echo 'Logs aggregated. Mem state : '.memory_get_usage().'<br/>';

        $weeklogs = use_stats_extract_logs($input->to - DAYSECS * 7, $input->to, $uid, $COURSE->id);
        echo 'Week Logs extracted. Mem state : '.memory_get_usage().'<br/>';
        $weekaggregate = use_stats_aggregate_logs($weeklogs, $input->to - DAYSECS * 7, $input->to, '', true, $COURSE);
        echo 'Week Logs aggregated. Mem state : '.memory_get_usage().'<br/>';

        echo "Compiling for ".fullname($auser).'<br/>';
        $globalresults = new StdClass;
        $globalresults->elapsed = 0;
        if (isset($aggregate)) {
            $globalresults->elapsed += $aggregate['coursetotal'][$COURSE->id]->elapsed ?? 0;
        }

        $globalresults->weekelapsed = 0;
        if (isset($weekaggregate)) {
            $globalresults->elapsed += $weekaggregate['coursetotal'][$COURSE->id]->elapsed ?? 0;
        }

        // globalheader retreives additional userquiz_monitor quiz results. 
        $rawfile .= $rawrenderer->globalheader($auser->id, $course->id, $globalresults, $input->from, $input->to);
    }

    $fs = get_file_storage();

    $context = context_course::instance($COURSE->id);

    $filerec = new StdClass;
    $filerec->contextid = $context->id;
    $filerec->component = 'report_examtraining';
    $filerec->filearea = 'instantreport';
    $filerec->itemid = 0;
    $filerec->filepath = '/';
    $filerec->filename = 'examtraining_raw_'.$timestamp.'.csv';

    $fs->create_file_from_string($filerec, $rawfile);

    $strupload = get_string('uploadresult', 'report_examtraining');
    $reporturl = moodle_url::make_pluginfile_url($context->id, 'report_examtraining', 'instantreport',
                                           '0', '/', $filerec->filename);
    echo '<a href="'.$reporturl.'">'.$strupload.'</a>';
} else {
    print_string('nothing', 'report_examtraining');
}
