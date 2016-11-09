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
 * @package     report_examtraining
 * @subpackage  report
 * @author      Valery Fremaux <valery.fremaux@club-internet.fr>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright   (C) 2016 onwards Valery Fremaux
 */
require('../../../config.php');

require_once($CFG->dirroot.'/report/examtraining/statscompilelib.php');

$id = required_param('id', PARAM_INT);
$ids = required_param('ids', PARAM_RAW);

$context = context_system::instance();
$url = new moodle_url('/report/examtraining/statscompiler/precompile_userstats.php', array('id' => $id));

// Security.

require_capability('moodle/site:config', $context);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

if (!userquiz_precompile_some_results($id, $ids)) {
    echo $OUTPUT->notification("No results to compile");
}
if ($id) {
    echo $OUTPUT->continue_button(new moodle_url('/report/examtraining/index.php', array('view' => 'compilationtools', 'id' => $id)));
}

echo $OUTPUT->footer();
