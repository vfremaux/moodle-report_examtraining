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

/**
 * direct log construction implementation
 *
 */
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$id = required_param('id', PARAM_INT) ; // The course id.
$startday = optional_param('startday', -1, PARAM_INT) ; // From (-1 is from course start).
$startmonth = optional_param('startmonth', -1, PARAM_INT) ; // From (-1 is from course start).
$startyear = optional_param('startyear', -1, PARAM_INT) ; // From (-1 is from course start).
$endday = optional_param('endday', -1, PARAM_INT) ; // To (-1 is till now).
$endmonth = optional_param('endmonth', -1, PARAM_INT) ; // To (-1 is till now).
$endyear = optional_param('endyear', -1, PARAM_INT) ; // To (-1 is till now).
$fromstart = optional_param('fromstart', 0, PARAM_INT) ; // Force reset to course startdate.
$from = optional_param('from', -1, PARAM_INT) ; // Alternate way of saying from when for XML generation.
$to = optional_param('to', -1, PARAM_INT) ; // Alternate way of saying from when for XML generation.

$offset = optional_param('offset', 0, PARAM_INT);
$page = 20;

// TODO : secure groupid access depending on proper capabilities.

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

/*
 * Pre print the group selector
 * time and group period form
 */
require_once($CFG->dirroot.'/report/examtraining/courseraw_selector_form.html');

// Compute target group.

if ($groupid) {
    $targetusers = groups_get_members($groupid);
} else {
    $fields = 'u.id, '.get_all_user_name_fields(true, 'u').', email, institution';
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

// Fitlers teachers out.
foreach ($targetusers as $uid => $user) {
    if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
        unset($targetusers[$uid]);
    }
}

// print result

if (!empty($targetusers)) {

    echo 'compiling for '.count($targetusers).' users<br/>';

    $timestamp = time();
    $rawfile = fopen($CFG->dataroot.'/'.$COURSE->id."/barchen_amf_raw_{$timestamp}.csv", 'wb');
    $resultset[] = get_string('entity', 'report_examtraining'); // Groupname.
    $resultset[] = get_string('id', 'report_examtraining'); // Userid.
    $resultset[] = get_string('username'); // Username.
    $resultset[] = get_string('firstenrolldate', 'report_examtraining'); // Enrol start date.
    $resultset[] = get_string('firstaccess', 'report_examtraining'); // Fist trace.
    $resultset[] = get_string('lastaccess', 'report_examtraining'); // Last trace.
    $resultset[] = get_string('startdate', 'report_examtraining'); // Compile start date.
    $resultset[] = get_string('todate', 'report_examtraining'); // Compile end date.
    $resultset[] = get_string('weekstartdate', 'report_examtraining'); // Last week start date.
    $resultset[] = get_string('lastname', 'report_examtraining'); // Last name.
    $resultset[] = get_string('firstname', 'report_examtraining'); // First name.
    $resultset[] = get_string('email'); // Email.
    $resultset[] = get_string('timeelapsed', 'report_examtraining');
    $resultset[] = get_string('timeelapsedcurweek', 'report_examtraining');
    $resultset[] = get_string('aansweredquestions', 'report_examtraining');
    $resultset[] = get_string('aansweredquestionscurweek', 'report_examtraining');
    $resultset[] = get_string('cansweredquestions', 'report_examtraining');
    $resultset[] = get_string('cansweredquestionscurweek', 'report_examtraining');
    $resultset[] = get_string('ratioa', 'report_examtraining');
    $resultset[] = get_string('ratioacurweek', 'report_examtraining');
    $resultset[] = get_string('ratioc', 'report_examtraining');
    $resultset[] = get_string('ratioccurweek', 'report_examtraining');
    $resultset[] = get_string('examsuccess', 'report_examtraining');
    $resultset[] = get_string('examattempts', 'report_examtraining');

    // Add report columns for modules.
    for ($i = 1; $i < 10; $i++) {
        $resultset[] = "Q$i";
    }
    for ($i = 1; $i <= 10; $i++) {
        $resultset[] = "Q".($i*10);
    }

    $resultset[] = get_string('dateofbirth', 'report_examtraining'); // DOB.
    $resultset[] = get_string('placeofbirth', 'report_examtraining'); // POB.
    $resultset[] = get_string('c3', 'report_examtraining'); // C3.

    fputs($rawfile, mb_convert_encoding(implode(';', $resultset)."\n", 'ISO-8859-1', 'UTF-8'));

    $examtraining_context = examtraining_get_context();

    foreach ($targetusers as $uid => $auser) {
        $logs = use_stats_extract_logs($from, $to, $uid, $COURSE->id);
        echo 'Logs extracted. Mem state : '.memory_get_usage().'<br/>';
        $aggregate = use_stats_aggregate_logs($logs, 'module', $uid);
        echo 'Logs aggregated. Mem state : '.memory_get_usage().'<br/>';

        $weeklogs = use_stats_extract_logs($to - DAYSECS * 7, time(), $uid, $COURSE->id);
        echo 'Week Logs extracted. Mem state : '.memory_get_usage().'<br/>';
        $weekaggregate = use_stats_aggregate_logs($weeklogs, 'module', $uid);
        echo 'Week Logs aggregated. Mem state : '.memory_get_usage().'<br/>';

        echo "Compiling for ".fullname($auser).'<br/>';
        $globalresults = new StdClass;
        $globalresults->elapsed = 0;
        if (isset($aggregate)) {
            foreach ($aggregate as $classname => $classarray) {
                foreach ($classarray as $modid => $modulestat) {
                    $globalresults->elapsed += $modulestat->elapsed;
                }
            }
        }

        $globalresults->weekelapsed = 0;
        if (isset($weekaggregate)) {
            foreach ($weekaggregate as $classarray) {
                foreach ($classarray as $modid => $modulestat) {
                    $globalresults->weekelapsed += $modulestat->elapsed;
                }
            }
        }

        $rawfile .= $rawrenderer->globalheader_raw($auser->id, $course->id, $globalresults, $from, $to);
    }

    $fs = get_file_storage();

    $context = context_course::instance($COURSE->id);

    $filerec = new StdClass;
    $filerec->contextid = $context->id;
    $filerec->component = 'report_examtraining';
    $filerec->filearea = 'instantreport';
    $filerec->itemid = 0;
    $filerec->path = '/';
    $filerec->filename = 'examtraining_raw_'.$timestamp.'.csv';

    $fs->create_file_from_string($filerec, $rawfile);

    $strupload = get_string('uploadresult', 'report_examtraining');
    $reporturl = moodle_url::make_file_url('/pluginfile.php', array($context->id, 'report_examtraining', 'instantreport', '0', '/', $filename));
    echo '<a href="'.$reporturl.'">'.$strupload.'</a>';
} else {
    print_string('nothing', 'report_examtraining');
}
