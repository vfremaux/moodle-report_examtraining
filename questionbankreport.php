<?php

defined('MOODLE_INTERNAL') || die;

/**
 * direct log construction implementation
 *
 */

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$id = required_param('id', PARAM_INT) ; // the course id
$orderby = optional_param('orderby', 'DESC', PARAM_ALPHA) ; // ordering of the result ASC or DESC 

ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities

// calculate start time

// print result
if ($output == 'html') {

    echo '<form action="" method="get" name="paramform">';
    echo '<input type="hidden" name="id" value="'.$id.'" />';
    echo '<input type="hidden" name="view" value="'.$view.'" />';
    echo '<table width="800"><tr valign="top"><td width="50%">';
    print_string('toporder', 'report_examtraining');
    $orderoptions = array('ASC' => get_string('ascending', 'report_examtraining'), 'DESC' => get_string('descending', 'report_barchenamf3'));
    echo html_writer::select($orderoptions, 'orderby', $orderby, array('onchange' => "document.forms['paramform'].submit()"));

    echo '</tr></table>';
    echo '</form>';

    examtraining_print_questionstats($orderby);

} else {

    /// generate XLS

    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers
    header('application/vnd.ms-excel');
    $workbook->send($filename);

    $xls_formats = examtraining_reports_xls_formats($workbook);
    $startrow = 15;

    ob_end_clean();
    $workbook->close();
}
