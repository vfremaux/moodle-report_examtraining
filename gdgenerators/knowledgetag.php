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

require '../../../../config.php';

ob_start();

require_once($CFG->dirroot.'/report/examtraining/locallib.php');

// Searching for font files

// output special situations messages 

global $examtraining_context;

$from = optional_param('from', 0, PARAM_INT);
$to = optional_param('to', time(), PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$courseid = required_param('course', PARAM_INT);

$examtraining_context = amf_get_context($courseid);

$questionids = array();
examtraining_reports_get_questions_rec($examtraining_context->rootcategory, $questionids);
sort($questionids);

// we get all states unique matched questions
$quizzeslist = implode("','", $examtraining_context->testquizzes);

// get all matched questions by the user.
$matched = array();
/*
$select = " userid = $userid AND timefinish > $from AND timefinish < $to AND quiz IN ('$quizzeslist') ";
if ($attempts = $DB->get_records_select('quiz_attempts', $select)){
    foreach ($attempts as $attempt) {
        if ($states = get_all_user_records($attempt->id, $userid, 'graded')) {
            foreach ($states as $state) {
                if (preg_match('/^random(?:constrained)?(\d+)/', $state->answer, $matches)) {
                    $realquestionid = $matches[1];
                }
                $matched[] = $realquestionid;
            }
        }
    }
}    
*/

$questionidlist = implode("','", $questionids);
$questionsused = $DB->get_records_select('userquiz_monitor_coverage', " userid = $userid AND questionid IN ('$questionidlist') ", 'questionid', 'questionid, usecount,matchcount');
$usedids = array_keys($questionsused);
$questioncount = count($questionids);

if (!$questioncount) {
    // generate blue tag
}

// generate tag mask
$root = ceil(sqrt((float)count($questionids)));
$basesize = floor(280 / $root);
$offset = ceil((280 - ($basesize * $root)) / 2) + 1; // offset for recentering the coverage

$imageWidth = 300;
$imageHeight = 300;

$output = optional_param('output', '', PARAM_TEXT);
if ($output == 'html') {
    for ($i = 0 ; $i < $root ; $i++) {
        for ($j = 0 ; $j < $root ; $j++) {
            $qix = @$questionids[$i * $root + $j];
            if (!in_array($qix, $usedids) || $questionsused[$qix]->usecount == 0) {
                echo 'X';
            } elseif (@$questionsused[$qix]->usecount != 0 && @$questionsused[$qix]->matchcount == 0) {
                echo 'B';
            } else {
                echo 'V';
            }
        }
    }
    die;
}

$background = $CFG->dirroot.'/report/examtraining/gdgenerators/background.png';

$im = imagecreatefrompng($background);
// imageantialias($im, FALSE);

$colors['black'] = imagecolorallocate($im, 0, 0, 0);
$colors['red'] = imagecolorallocate($im, 200, 0, 0);

for ($i = 0 ; $i < $root ; $i++) {
    for ($j = 0 ; $j < $root ; $j++) {
        $qix = @$questionids[$i * $root + $j];
        if (!in_array($qix, $usedids) || $questionsused[$qix]->usecount == 0) {
            imagefilledrectangle($im, $offset + $i * $basesize + 10, $offset + $j * $basesize + 10, $offset + ($i+1)*$basesize + 10, $offset + ($j+1)*$basesize + 10, $colors['black']);
        } elseif (@$questionsused[$qix]->usecount != 0 && @$questionsused[$qix]->matchcount == 0) {
            imagefilledrectangle($im, $offset + $i * $basesize + 10, $offset + $j * $basesize + 10, $offset + ($i+1)*$basesize + 10, $offset + ($j+1)*$basesize + 10, $colors['red']);
        }
    }
}

// delivering image
ob_end_clean();
// print_object($examtraining_context);
// print_object($matched);
//  print_object($questionids);
header("Content-type: image/png");
imagepng($im);
imagedestroy($im);
exit;
