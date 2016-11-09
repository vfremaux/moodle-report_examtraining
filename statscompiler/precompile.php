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

$id = optional_param('id', 0, PARAM_INT); // Course id.
$cats = optional_param('withcats', false, PARAM_BOOL);
$new = optional_param('range', 0, PARAM_INT);
$fromid = optional_param('fromid', 0, PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('coursemisconf');
}

$context = context_system::instance();
$url = new moodle_url('/report/examtraining/statscompiler/precompile.php', array('id' => $id));

// Security.

require_capability('moodle/site:config', $context);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

if (!userquiz_precompile_results($id, 'userquiz_precompile_results_worker', $cats, $new, $fromid)){
    echo $OUTPUT->notification("No more results to compile");
} else {
    echo $OUTPUT->notification("Still results to compile");
}
if ($id) {
    $params = new moodle_url('/report/examtraining/index.php', array('view' => 'compilationtools', 'id' => $id));
    echo $OUTPUT->continue_button($params);
}

echo $OUTPUT->footer();