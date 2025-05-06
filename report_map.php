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

defined('MOODLE_INTERNAL') || die();

/*
 * direct log construction implementation
 */
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/classes/output/htmlrenderer.php');
require_once($CFG->dirroot.'/local/vflibs/jqplotlib.php');


$input = examtraining_reports_input($course);
$input->num = optional_param('num', 0, PARAM_INT);
$input->orderby = optional_param('orderby', '', PARAM_TEXT);
$input->subview = optional_param('subview', '', PARAM_TEXT);
$input->offset = optional_param('offset', 0, PARAM_INT);
$page = 20;

ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities.

/*
 * Pre print the group selector
 * time and group period form
 */
$input->nousers = true;
echo $renderer->selectorform($course, $view, $input);

// Compute target group.
$pagesize = 50;

if ($groupid) {
    $targetusers = get_enrolled_users($context, '', $groupid, 'u.*', 'u.lastname, u.firstname', $input->offset, $pagesize, true);
    $max = count($targetusers);
    $pagesize = count($targetusers);
} else {
    $allusers = get_enrolled_users($context, '', 0, 'u.id', 'u.lastname, u.firstname', 0, 0, true);
    $max = count($allusers);
    $targetusers = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname, u.firstname', $input->offset, $pagesize, true);
}

// Filters teachers out.
if (!empty($targetusers)) {
    foreach ($targetusers as $uid => $user) {
        if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
            unset($targetusers[$uid]);
        }
    }
}

$htmlrenderer = $PAGE->get_renderer('report_examtraining', 'html');

// Print result.
echo '<br/>';

if (!empty($targetusers)) {

    echo '<table width="800">';
    echo '<tr valign="top">';
    echo '<td width="80%">';
    $reportcontext = block_userquiz_monitor_get_block($COURSE->id)->config;
    $compiler = new \report_examtraining\stats\compiler();
    $userglobals = $compiler->get_user_globals(array_keys($targetusers), $COURSE->id, $input->from, $input->to);
    echo $htmlrenderer->coverage_vs_ratio($targetusers, $course->id, $input->from, $input->to, $userglobals);
    echo '</td><td width="20%">';
    $advicestr = get_string('jqplotzoomadvice', 'report_examtraining');
    echo '<span class="smalltext">'.$advicestr.'</span>';
    echo '</td></tr></table>';
}
