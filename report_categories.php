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
 *
 */
require_once($CFG->dirroot.'/report/examtraining/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/classes/output/htmlrenderer.php');

$groupid = optional_param('group', 0, PARAM_INT);

$input = examtraining_reports_input($course);

$htmlrenderer = $PAGE->get_renderer('report_examtraining', 'html');

// Compute target group.
$group = null;
if ($groupid) {
    $group = groups_get_group($groupid);
}

/*
 * Pre print the group selector
 * time and group period form
 */
$input->nousers = true; // tells it is a group selector.
echo $renderer->selectorform($course, $view, $input);

// Print result.

$compiler = new \report_examtraining\stats\compiler();
$stats = $compiler->get_course_globals($COURSE->id, $groupid, $input->from, $input->to);

echo $OUTPUT->heading(get_string('categories', 'report_examtraining'));

$catstats = $compiler->get_per_categories($COURSE->id, $group, $input->from, $input->to);

// echo $OUTPUT->heading(get_string('attempts', 'report_examtraining'));

echo $htmlrenderer->categories_attempts($catstats, $group);

