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
 *
 * @package     local_performance
 * @subpackage  local
 * @author      Valery Fremaux <valery.fremaux@club-internet.fr>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright   (C) 2016 onwards Valery Fremaux
 */
require_once('../../../config.php');

require_once($CFG->dirroot.'/report/examtraining/statscompilelib.php');

$courseid = optional_param('id', 0, PARAM_INT); // Course id.
$options['limit'] = optional_param('limit', 0, PARAM_INT);
$options['output'] = optional_param('output', 0, PARAM_INT);
$options['running'] = optional_param('running', 0, PARAM_BOOL);
$options['auto'] = optional_param('auto', 0, PARAM_INT); // Seconds to run
$options['maxruns'] = optional_param('maxruns', 0, PARAM_INT); // max rebounces to spawn

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('coursemisconf');
}

$context = context_system::instance();
$params = array('id' => $courseid, 'limit' => $options['limit'], 'running' => $options['running'], 'maxruns' => $options['maxruns'] - 1);
$url = new moodle_url('/report/examtraining/statscompiler/precompile.php', $params);

// Security.

require_capability('moodle/site:config', $context);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

$compiler = new \report_examtraining\stats\compiler();
$attemptsleft = $compiler->precompile_results($courseid, $options);

// check respans if required.

// If nothing left, reset everything and go out.
// backgroundrunsenabled is a "strong force out by config option" in case web side is stucked.
if ($attemptsleft == 0 || empty($CFG->backgroundrunsenabled)) {
    $SESSION->examtrainingruns = 0;
    $SESSION->examtrainingtime = 0;
    $options['running'] = 0;
    return 0; // Nothing more to compile.
}

$needrespawn = false;

// Check if we can respawn : We can respawn if we are in autorunning and maxruns has not be reached.
// We have no limitation engaged in runs or limitation is NOT reached..
if (empty($options['maxruns']) || @$SESSION->examtrainingruns < $options['maxruns']) {
    if ($options['maxruns']) {
        $SESSION->examtrainingruns = @$SESSION->examtrainingruns + 1;
    }
    $needrespawn = true;
}

if (!empty($options['auto'])) {
    // Check the running time condition.
    if (!empty($SESSION->examtrainingtime)) {
        // We can respawn if we have not spent all processing time.
        if (time() - $SESSION->examtrainingtime < $options['auto']) {
            $needrespawn = $needrespawn && true;
        }
    }
}

if ($needrespawn) {
    redirect($url);
} else {
    // clear bounce counter;
    if (isset($SESSION->examtrainingruns)) {
        unset($SESSION->examtrainingruns);
    }
    if (isset($SESSION->examtrainingtime)) {
        unset($SESSION->examtrainingtime);
    }
}

echo $OUTPUT->header();

/*
if ($needrespawn) {
    echo $OUTPUT->notification("Run: ".@$SESSION->examtrainingruns, "info");
    echo $OUTPUT->notification("Respawning to $url", "info");
    echo $OUTPUT->continue_button($url, "Respawn");
}
*/

if (!$attemptsleft) {
    echo $OUTPUT->notification("No more results to compile");
} else {
    echo $OUTPUT->notification("Still $attemptsleft results to compile");
}


if ($courseid) {
    $params = new moodle_url('/report/examtraining/index.php', array('view' => 'compilationtools', 'id' => $courseid));
    echo $OUTPUT->continue_button($params);
}

echo $OUTPUT->footer();