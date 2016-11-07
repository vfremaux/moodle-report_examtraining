<?php

defined('MOODLE_INTERNAL') ||  die();

if (has_capability('report/examtraining:view', $context)) {
    echo '<p>';
    $report = get_string('examtrainingreport', 'report_examtraining');
    $reporturl = new moodle_url('/report/examtraining/index.php', array('id' => $course->id));
    echo '<a href="'.$reporturl.'">'.$report.'</a>';
    echo '</p>';
}
