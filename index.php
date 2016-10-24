<?php

require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->libdir.'/excellib.class.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

@raise_memory_limit('512M');

$url = new moodle_url('/report/examtraining/index.php');
$PAGE->set_url($url);

$id = required_param('id', PARAM_INT); // course id.

$context = context_course::instance($id);
$PAGE->set_context($context);

$output = optional_param('output', 'html', PARAM_ALPHA) ; // 'html', 'xls' or 'wkpdf'    
$view = optional_param('view', 'courseraw', PARAM_TEXT);
$groupid = optional_param('groupid', 0, PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourse');
}

// secure output by buffering everything before real output
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

// resolve view
if (has_capability('report/examtraining:viewall', $context)) {
    if (!preg_match('/user|userattempt|course|course_map|course_group|courseraw|questionbank|compilationtools/', $view)) {
        $view = 'courseraw';
    }
    if ($view == 'course') {
        $page = 'map'; // set a default
    } else {
        if (preg_match('/^course_/', $view)) {
            $page = str_replace('course_', '', $view);
            $view = 'course';
        } else {
            $page = $view;
        }
    }
} else {
    $view = 'user';
    $page = $view;
}

// check availability of the view
if (file_exists($CFG->dirroot."/report/examtraining/{$page}report.php")) {
    $reportview = $CFG->dirroot."/report/examtraining/{$page}report.php";
} else {
    print_error('non existing report view : '.$page);
    die;
}

// if screen output, output HTML moodle header
if ($output == 'html') {
    $PAGE->navbar->add(format_string($course->fullname), new moodle_url('/course/view.php', array('id' => $course->id)));
    $PAGE->navbar->add(get_string('barchenamfreport','report_examtraining'));
    $PAGE->set_title(get_string('reports', 'report_examtraining'));
    $PAGE->set_heading(get_string('reports', 'report_examtraining'));

    echo $OUTPUT->header();

    echo $OUTPUT->container_start();

    /// Print tabs with options for user
    if (has_capability('report/examtraining:viewall', $context)) {

        $rows[0][] = new tabobject('user', "index.php?id={$course->id}&amp;view=user", get_string('user', 'report_examtraining'));
        $rows[0][] = new tabobject('course', "index.php?id={$course->id}&amp;view=course&groupid={$groupid}", get_string('course', 'report_examtraining'));
        $rows[0][] = new tabobject('courseraw', "index.php?id={$course->id}&amp;view=courseraw&groupid={$groupid}", get_string('courseraw', 'report_examtraining'));
        $rows[0][] = new tabobject('questionbank', "index.php?id={$course->id}&amp;view=questionbank", get_string('questionbank', 'report_examtraining'));
        if (has_capability('moodle/site:config', context_system::instance())) {
            $rows[0][] = new tabobject('compilationtools', "index.php?id={$course->id}&amp;view=compilationtools", get_string('compilationtools', 'report_examtraining'));
        }

        if ($view == 'course') {
            if (has_capability('report/examtraining:viewsensibleresults', $context)) {
                $rows[1][] = new tabobject('course_map', "index.php?id={$course->id}&amp;view=course_map&amp;groupid={$groupid}", get_string('coursemap', 'report_examtraining'));
            }
            $rows[1][] = new tabobject('course_group', "index.php?id={$course->id}&amp;view=course_group&amp;groupid={$groupid}", get_string('coursegroup', 'report_examtraining'));
            if (has_capability('report/examtraining:viewsensibleresults', $context)) {
                $rows[1][] = new tabobject('course_tops', "index.php?id={$course->id}&amp;view=course_tops&amp;groupid={$groupid}", get_string('coursetops', 'report_examtraining'));
            }
            print_tabs($rows, $view, 'course', array($page));
        } else {
            print_tabs($rows, $view);
        }
    }
    echo $OUTPUT->container_end();

    $html = '';
    $tablewidth = "100%";
} elseif ($output == 'pdf') {
    require_once($CFG->dirroot.'/local/lib/html2pdf/moodlehtml2pdf.php');

    $pdf = new HTML2PDF('P', 'A4', 'fr', true, 'UTF-8', array(10, 15, 10, 20));
    $pdf->pdf->SetDisplayMode('fullpage');
    $pdf->setTestIsImage();
    // $pdf->setModeDebug();

    $reportclassname = basename(dirname(__FILE__));
    $pdfheader = html2pdf_get_header($reportclassname);
    $pdffooter = html2pdf_get_footer($reportclassname);
    $pdfstyleheets = html2pdf_get_stylesheets($reportclassname);

    $html = '';

    $tablewidth = "560";
}

@ini_set('max_execution_time','600');

include $reportview;

if ($output == 'html') {
    echo $OUTPUT->footer();
} elseif ($output == 'pdf') {

    $title = get_string("$view-$page", 'report_examtraining');

    $html = "<page backtop=\"50mm\" backbottom=\"10mm\" backleft=\"10mm\" backright=\"10mm\">
             <page_header>$pdfheader</page_header>
             <page_footer>$pdffooter</page_footer>
             $pdfstyleheets
             <h1 class=\"reportitle\">$title</h1>".$html.'</page>';

    // Add a HTML file, a HTML string, a page from URL or a PDF file
    // footer and header are provided as options, just drop the body here, built by the report view.
    $pdf->writeHTML("$html");

    // Save the PDF, or ...
    $filename = $reportclassname.'_report_'.date('d-M-Y', time()).'.pdf';

    // ... send to client as file download
    $pdf->Output($filename);
    die;
}
