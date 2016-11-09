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


$input = examtraining_reports_input($course);
$offset = optional_param('offset', 0, PARAM_INT);
$page = 20;

ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities.

/*
 * Pre print the group selector
 * time and group period form
 */
require($CFG->dirroot.'/report/examtraining/course_selector_form.html');

// Compute target group.

if ($groupid) {
    $targetusers = groups_get_members($groupid);
    $max = count($targetusers);
    $page = count($targetusers);
} else {
    $fields = 'u.id, '.get_all_user_name_fields(true, 'u');
    $allusers = get_users_by_capability($context, 'moodle/course:view', $fields, 'lastname');
    $max = count($allusers);
    $fields = 'u.id, '.get_all_user_name_fields(true, 'u').', email, institution';
    $targetusers = get_users_by_capability($context, 'moodle/course:view', $fields, 'lastname', $offset, $page);
}

// Filters teachers out.
if (!empty($targetusers)) {
    foreach ($targetusers as $uid => $user) {
        if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
            unset($targetusers[$uid]);
        }
    }
}

// Print result.
echo '<br/>';

if (!empty($targetusers)) {

    echo '<table width="800">';
    echo '<tr valign="top">';
    echo '<td width="80%">';
    $reportcontext = examtraining_get_context();
    $userglobals = userquiz_get_user_globals(array_keys($targetusers), $reportcontext->trainingquizzes, $input->from, $input->to);
    echo $renderer->coverage_vs_ratio($targetusers, $course->id, $input->from, $input->to, $userglobals);
    echo '</td><td width="20%">';
    $advicestr = get_string('jqplotzoomadvice', 'report_examtraining');
    echo '<span class="smalltext">'.$advicestr.'</span>';
    echo '</td></tr></table>';
}
