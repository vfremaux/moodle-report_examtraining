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

$orderby = optional_param('orderby', 'DESC', PARAM_ALPHA); // Ordering of the result ASC or DESC.

ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities.

// Print result.
if ($output == 'html') {

    echo '<form action="" method="get" name="paramform">';
    echo '<input type="hidden" name="id" value="'.$id.'" />';
    echo '<input type="hidden" name="view" value="'.$view.'" />';
    echo '<table width="800"><tr valign="top"><td width="50%">';
    print_string('toporder', 'report_examtraining');
    $orderoptions = array('ASC' => get_string('ascending', 'report_examtraining'),
                          'DESC' => get_string('descending', 'report_examtraining'));
    echo html_writer::select($orderoptions, 'orderby', $orderby, array('onchange' => "document.forms['paramform'].submit()"));

    echo '</tr></table>';
    echo '</form>';

    examtraining_print_questionstats($orderby);

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
