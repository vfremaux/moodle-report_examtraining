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

defined('MOODLE_INTERNAL') || die;

/*
 * direct log construction implementation
 */
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/classes/output/htmlrenderer.php');
require_once($CFG->dirroot.'/local/vflibs/jqplotlib.php');

$orderby = optional_param('orderby', 'DESC', PARAM_ALPHA); // Ordering of the result ASC or DESC.

ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities.

// Print result.
if ($output == 'html') {

    $template = new StdClass;

    $template->formurl = new moodle_url('/report/examtraining/index.php');
    $template->id = $id;
    $template->view = $view;
    $template->toporderstr = get_string('toporder', 'report_examtraining');
    $orderoptions = array('ASC' => get_string('ascending', 'report_examtraining'),
                          'DESC' => get_string('descending', 'report_examtraining'));
    $template->select = html_writer::select($orderoptions, 'orderby', $orderby, '', array('onchange' => "document.forms['paramform'].submit()"));

    echo $OUTPUT->render_from_template('report_examtraining/questionbankform', $template);

    $htmlrenderer = $PAGE->get_renderer('report_examtraining', 'html');
    echo $htmlrenderer->questionstats($orderby);

} else {

    // Generate XLS.

    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers.
    header('application/vnd.ms-excel');
    $workbook->send($filename);

    $xlsformats = examtraining_reports_xls_formats($workbook);
    $startrow = 15;

    ob_end_clean();
    $workbook->close();
}
