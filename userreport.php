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

defined('MOODLE_INTERNAL') || die();

/*
 * direct log construction implementation
 *
 */

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$input = examtraining_reports_input($course);
$input->userid = optional_param('userid', $USER->id, PARAM_INT) ; // Admits special values : -1 current group, -2 course users.

ini_set('memory_limit', '1024M');

// TODO : Secure userid access depending on proper capabilities.

// In that case we cannot go out our account scope.
if (!has_capability('report/examtraining:viewall', $context)) {
    $input->userid = $USER->id;
}

// Get data.

$logs = use_stats_extract_logs($input->from, $input->to, $input->userid, $COURSE->id);
$aggregate = use_stats_aggregate_logs($logs, 'module', $input->from, $input->to);

// Print result.

$globalresults = new StdClass;
$globalresults->from = 0 + $input->from;
$globalresults->to = 0 + $input->to;
$globalresults->elapsed = 0;
$globalresults->events = 0;

foreach ($aggregate as $module => $classarray) {
    foreach ($classarray as $modulestat) {
        $globalresults->elapsed += 0 + @$modulestat->elapsed;
        $globalresults->events += 0 + @$modulestat->events;
    }
}

if ($output == 'html') {
    // Time period form.

    require($CFG->dirroot.'/report/examtraining/selector_form.html');

    $renderer = $PAGE->get_renderer('report_examtraining');

    examtrainings_reports_print_header_html($input->userid, $course->id, $globalresults);
    $stats = null;
    examtrainings_reports_print_trainings_globals_html($input->userid, $input->from, $input->to, 'large', $stats);
    examtrainings_reports_print_times_html($input->userid, $globalresults);
    examtrainings_reports_print_trainings_html($input->userid, $input->from, $input->to);
    examtrainings_reports_print_trainings_subcats_html($input->userid, $input->from, $input->to);
    examtrainings_reports_print_exams_html($input->userid, $input->from, $input->to);
    examtrainings_reports_print_assiduity2_html($input->userid, $input->from, $input->to);
    examtrainings_reports_print_modules_html($input->userid, $input->from, $input->to);

    examtrainings_reports_print_radar_html($input->userid, $input->from, $input->to);

} else {
    $filename = 'examtraining_sessions_report_'.date('d-M-Y', time()).'.xls';
    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers.
    $workbook->send($filename);

    // Preparing some formats.
    $xls_formats = examtrainings_reports_xls_formats($workbook);
    $worksheet = examtrainings_reports_init_worksheet($input->userid, $xls_formats, $workbook);
    $startrow = examtrainings_reports_print_header_xls($worksheet, $input->userid, $course->id, $globalresults, $xls_formats);
    $startrow = examtrainings_reports_print_trainings_xls($worksheet, $startrow, $xls_formats, $input->userid, $course->id, $questionresults);
    $startrow = examtrainings_reports_print_exams_xls($worksheet, $startrow, $xls_formats, $input->userid, $course->id, $examresults);
    $startrow = examtrainings_reports_print_assiduity_xls($worksheet, $startrow, $xls_formats, $input->userid, $course->id, $questionresults, $examresults, $input->from, $input->to);
    $startrow = examtrainings_reports_print_modules_xls($worksheet, $startrow, $xls_formats, $input->userid, $course->id, $questionresults);

    ob_end_clean();
    $workbook->close();
}

