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
require_once($CFG->dirroot.'/report/examtraining/classes/output/htmlrenderer.php');
require_once($CFG->dirroot.'/report/examtraining/classes/output/xlsrenderer.php');
require_once($CFG->dirroot.'/local/vflibs/jqplotlib.php');

$input = examtraining_reports_input($course);
$userid = optional_param('userid', $USER->id, PARAM_INT); // Admits special values : -1 current group, -2 course users.

ini_set('memory_limit', '1024M');

// TODO : Secure userid access depending on proper capabilities.

// In that case we cannot go out our account scope.
if (!has_capability('report/examtraining:viewall', $context)) {
    $input->userid = $USER->id;
}

// Get data.

$logs = use_stats_extract_logs($input->from, $input->to, $userid, $COURSE->id);
$aggregate = use_stats_aggregate_logs($logs, $input->from, $input->to);

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

    echo $renderer->selectorform();

    $htmlrenderer = $PAGE->get_renderer('report_examtraining', 'html');

    echo $htmlrenderer->header($userid, $course->id, $globalresults);
    $stats = null;
    echo $htmlrenderer->trainings_globals($userid, $input->from, $input->to, 'large', $stats);
    echo $htmlrenderer->times($userid, $globalresults);
    echo $htmlrenderer->trainings($userid, $input->from, $input->to);
    echo $htmlrenderer->trainings_subcats($userid, $input->from, $input->to);
    echo $htmlrenderer->exams($userid, $input->from, $input->to);
    echo $htmlrenderer->assiduity2($userid, $input->from, $input->to, $view);
    echo $htmlrenderer->modules($userid, $input->from, $input->to);
    echo $htmlrenderer->radar($userid, $input->from, $input->to);

} else {

    $xlsrenderer = $PAGE->get_renderer('report_examtraining', 'xls');

    $filename = 'examtraining_sessions_report_'.date('d-M-Y', time()).'.xls';
    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers.
    $workbook->send($filename);

    // Preparing some formats.
    $xlsformats = examtraining_reports_xls_formats($workbook);
    $worksheet = examtraining_reports_init_worksheet($userid, $xlsformats, $workbook);
    $startrow = $xlsrenderer->header($worksheet, $userid, $course->id, $globalresults, $xlsformats);
    $startrow = $xlsrenderer->trainings($worksheet, $startrow, $xlsformats, $userid, $course->id, $questionresults);
    $startrow = $xlsrenderer->exams($worksheet, $startrow, $xlsformats, $userid, $course->id, $examresults);
    $startrow = $xlsrenderer->assiduity($worksheet, $startrow, $xlsformats, $userid, $course->id,
                                                          $questionresults, $examresults, $input->from, $input->to);
    $startrow = $xlsrenderer->modules($worksheet, $startrow, $xlsformats, $userid, $course->id, $questionresults);

    ob_end_clean();
    $workbook->close();
}

