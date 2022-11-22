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
 * This file contains functions used by the examtraining report.
 *
 * @package     report_examtraining
 * @category    report
 * @copyright   2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->libdir.'/excellib.class.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

@raise_memory_limit('512M');

$id = required_param('id', PARAM_INT); // The course id.
$view = optional_param('view', 'user', PARAM_TEXT); // The course id.
$userid = optional_param('userid', '', PARAM_INT); // The course id.
$output = optional_param('output', 'html', PARAM_ALPHA); // Values: html, xls or wkpdf.
$groupid = optional_param('groupid', 0, PARAM_INT);

$params = array('id' => $id, 'view' => $view);
if (!empty($userid)) {
    $params['userid'] = $userid;
}
$url = new moodle_url('/report/examtraining/index.php', $params);
$PAGE->set_url($url);

$context = context_course::instance($id);
$PAGE->set_context($context);

// Clear compile session data.
if (isset($SESSION->examtrainingruns)) {
    unset($SESSION->examtrainingruns);
}
if (isset($SESSION->examtrainingtime)) {
    unset($SESSION->examtrainingtime);
}

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourse');
}

// Secure output by buffering everything before real output.
if ($output != 'html') {
    ob_start();
}

$pdfinstalled = false;
if (is_readable($CFG->dirroot.'/local/vflibs/html2pdf/html2pdf.class.php')) {
    $pdfinstalled = true;
}

require_login($course);
require_capability('report/examtraining:view', $context);

$PAGE->requires->jquery_plugin('jqplotjquery', 'local_vflibs');
$PAGE->requires->jquery_plugin('jqplot', 'local_vflibs');
$PAGE->requires->css('/local/vflibs/jquery/jqplot/jquery.jqplot.css');

$renderer = $PAGE->get_renderer('report_examtraining');

// Resolve view.
if (has_capability('report/examtraining:viewall', $context)) {
    if (!preg_match('/user|userattempt|group|categories|course|map|tops|raw|questionbank|compilationtools/', $view)) {
        $view = 'courseraw';
    }
} else {
    $view = 'user';
}

// Check availability of the view.
if (file_exists($CFG->dirroot."/report/examtraining/report_{$view}.php")) {
    $reportview = $CFG->dirroot."/report/examtraining/report_{$view}.php";
} else {
    print_error('non existing report view : '.$view);
    die;
}

// If screen output, output HTML moodle header.
if ($output == 'html') {
    $PAGE->navbar->add(format_string($course->fullname), new moodle_url('/course/view.php', array('id' => $course->id)));
    $PAGE->navbar->add(get_string('pluginname', 'report_examtraining'));
    $PAGE->set_title(get_string('reports', 'report_examtraining'));
    $PAGE->set_heading(get_string('reports', 'report_examtraining'));

    echo $OUTPUT->header();

    echo $renderer->tabs($view, $groupid);

    $html = '';
    $tablewidth = "100%";
} else if ($output == 'pdf') {
    require_once($CFG->dirroot.'/local/vflibs/html2pdf/moodlehtml2pdf.php');

    $pdf = new HTML2PDF('P', 'A4', 'fr', true, 'UTF-8', array(10, 15, 10, 20));
    $pdf->pdf->SetDisplayMode('fullpage');
    $pdf->setTestIsImage();

    $reportclassname = basename(dirname(__FILE__));
    $pdfheader = html2pdf_get_header($reportclassname);
    $pdffooter = html2pdf_get_footer($reportclassname);
    $pdfstyleheets = html2pdf_get_stylesheets($reportclassname);

    $html = '';

    $tablewidth = "560";
}

@ini_set('max_execution_time', '1200');

include($reportview);

if ($output == 'html') {
    echo $OUTPUT->footer();
} else if ($output == 'pdf') {

    $title = get_string("$view", 'report_examtraining');

    $html = "<page backtop=\"50mm\" backbottom=\"10mm\" backleft=\"10mm\" backright=\"10mm\">
             <page_header>$pdfheader</page_header>
             <page_footer>$pdffooter</page_footer>
             $pdfstyleheets
             <h1 class=\"reportitle\">$title</h1>".$html.'</page>';

    /*
     * Add a HTML file, a HTML string, a page from URL or a PDF file
     * footer and header are provided as options, just drop the body here, built by the report view.
     */
    $pdf->writeHTML("$html");

    // Save the PDF, or ...
    $filename = $reportclassname.'_report_'.date('d-M-Y', time()).'.pdf';

    // ... send to client as file download.
    $pdf->Output($filename);
    die;
}
