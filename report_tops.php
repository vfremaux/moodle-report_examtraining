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

defined('MOODLE_INTERNAL') || die;

/*
 * direct log construction implementation
 *
 */
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/classes/output/htmlrenderer.php');

$input = examtraining_reports_input($course);
$input->orderby = optional_param('orderby', 'DESC', PARAM_ALPHA); // Ordering of the result ASC or DESC.
$input->num = optional_param('num', 15, PARAM_INT);
$pagesize = 20;

ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities.

/*
 * Pre print the group selector
 * time and group period form
 */
$input->nousers = true;
echo $renderer->selectorform($course, $view, $input);

$examcontext = block_userquiz_monitor_get_block($COURSE->id)->config;
$compiler = new \report_examtraining\stats\compiler();

$htmlrenderer = $PAGE->get_renderer('report_examtraining', 'html');

// Compute target group.

if ($groupid) {
    $targetusers = get_enrolled_users($context, '', $groupid, 'u.*', 'u.lastname, u.firstname', 0, 0, true);

    // Filters teachers out.
    if (!empty($targetusers)) {
        foreach ($targetusers as $uid => $user) {
            if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
                unset($targetusers[$uid]);
            }
        }
    }
}

// Print result.

$template = new StdClass;
$template->formurl = new moodle_url('/report/examtraining/index.php');
$template->id = $id;
$template->from = $input->from;
$template->to = $input->to;
$template->groupid = $groupid;
$template->toporderstr = get_string('toporder', 'report_examtraining');
$orderoptions = array('ASC' => get_string('ascending', 'report_examtraining'),
                      'DESC' => get_string('descending', 'report_examtraining'));
                      $attrs = array('onchange' => "document.forms['paramform'].submit()");
$template->orderselect = html_writer::select($orderoptions, 'orderby', $input->orderby, '', $attrs);

$template->toplengthstr = get_string('toplength', 'report_examtraining');
$lengthoptions = array('5' => '5', '10' => '10', '15' => '15', '20' => '20', '30' => '30', '40' => '40', '50' => '50');
$template->lengthselect = html_writer::select($lengthoptions, 'num', $input->num, '', array('onchange' => "document.forms['paramform'].submit()"));

echo $OUTPUT->render_from_template('report_examtraining/topoptionsform', $template);

$userids = null;
if (!empty($targetusers)) {
    $userids = array_keys($targetusers);
}

$topexamattempts = $compiler->get_attempt_count($course->id, $userids, 1, $input->from, $input->to, $input->orderby, $input->num);
$toptrainingattempts = $compiler->get_attempt_count($course->id, $userids, 0, $input->from, $input->to, $input->orderby, $input->num);
$topquestions = $compiler->get_by_function_count($course->id, $userids, $input->from, $input->to, $input->orderby, $input->num);

echo $htmlrenderer->tops($topexamattempts, $toptrainingattempts, $topquestions);
