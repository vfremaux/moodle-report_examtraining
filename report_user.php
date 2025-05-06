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

$input = examtraining_reports_input($course); // Not processing userid.
$input->nousers = 0;
$userid = optional_param('userid', $USER->id, PARAM_INT); // Admits special values : -1 current group, -2 course users.
$compiler = new \report_examtraining\stats\compiler();

ini_set('memory_limit', '1024M');

// TODO : Secure userid access depending on proper capabilities.

// In that case we cannot go out our account scope.
if (!has_capability('report/examtraining:viewall', $context)) {
    $input->userid = $USER->id;
} else {
    $input->userid = $userid;
}

// Get data.
$logs = use_stats_extract_logs($input->from, $input->to, $input->userid, $course->id);
$aggregate = use_stats_aggregate_logs($logs, $input->from, $input->to);

// Print result.

$results = $compiler->get_user_globals([$input->userid], $course->id, $input->from, $input->to);
$globalresults = $results[$input->userid];
$globalresults->elapsed = $aggregate['coursetotal'][$course->id]->elapsed;

if ($output == 'html') {
    // Time period form.

    echo $renderer->selectorform($course, $view, $input);

    $htmlrenderer = $PAGE->get_renderer('report_examtraining', 'html');

    echo $htmlrenderer->header($userid, $course->id, $globalresults);
    $stats = null;
    echo $htmlrenderer->trainings_globals($input->userid, $input->from, $input->to, 'large', $stats);
    echo $htmlrenderer->times($input->userid, $globalresults);
    echo $htmlrenderer->trainings($input->userid, $input->from, $input->to);
    echo $htmlrenderer->trainings_subcats($input->userid, $input->from, $input->to);
    echo $htmlrenderer->exams($input->userid, $input->from, $input->to);
    echo $htmlrenderer->assiduity2($input->userid, $input->from, $input->to, $view);
    echo $htmlrenderer->modules($input->userid, $input->from, $input->to);
    $title = get_string('mastering', 'report_examtraining');
    echo $htmlrenderer->coverage_radar($input->userid, $input->from, $input->to, $title, 'q', '#A0D040', '#708030');

} else {

    $xlsrenderer = $PAGE->get_renderer('report_examtraining', 'xls');

    $filename = 'examtraining_sessions_report_'.date('d-M-Y', time()).'.xls';
    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers.
    $workbook->send($filename);

    // Preparing some formats.
    $xlsformats = examtraining_reports_xls_formats($workbook);
    $worksheet = examtraining_reports_init_worksheet($input->userid, $xlsformats, $workbook);
    $startrow = $xlsrenderer->header($worksheet, $input->userid, $course->id, $globalresults, $xlsformats);
    $startrow = $xlsrenderer->trainings($worksheet, $startrow, $xlsformats, $input->userid, $course->id, $questionresults);
    $startrow = $xlsrenderer->exams($worksheet, $startrow, $xlsformats, $input->userid, $course->id, $examresults);
    $startrow = $xlsrenderer->assiduity($worksheet, $startrow, $xlsformats, $input->userid, $course->id,
                                                          $questionresults, $examresults, $input->from, $input->to);
    $startrow = $xlsrenderer->modules($worksheet, $startrow, $xlsformats, $input->userid, $course->id, $questionresults);

    ob_end_clean();
    $workbook->close();
}

