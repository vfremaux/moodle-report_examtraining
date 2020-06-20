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

$id = required_param('id', PARAM_INT); // The course id.

// Quick controller for enabing/disabling background tasks.
$bgenabled = optional_param('backgroundenabled', 0, PARAM_BOOL);
if ($bgenabled) {
    set_config('backgroundrunsenabled', $bgenabled, 'report_examtraining');
    echo $OUTPUT->notification('Background compilations enabled');
    $CFG->backgroundrunsenabled = true;
}
// Print tools.

$template = new StdClass;

$template->id = $id;
$template->sesskey = sesskey();
$template->backgroundenabledchecked = (empty($CFG->backgroundrunsenabled)) ? '' : 'checked="checked"';
$template->compilerstatsurl = new moodle_url('/report/examtraining/statscompiler/clear_userstats.php');
$template->precompileuserstatsurl = new moodle_url('/report/examtraining/statscompiler/precompile_userstats.php');
$template->precompileunityurl = new moodle_url('/report/examtraining/statscompiler/precompile_unity.php');
$template->precompileurl = new moodle_url('/report/examtraining/statscompiler/precompile.php');
$template->coveragecompilerurl = new moodle_url('/report/examtraining/statscompiler/precompile_coverages.php');
$template->clearstateurl = new moodle_url('/report/examtraining/statscompiler/clear_stats.php');

$template->bulksizehelpicon = $OUTPUT->help_icon('bulksize', 'report_examtraining');
$template->maxbulkshelpicon = $OUTPUT->help_icon('maxbulks', 'report_examtraining');

echo $OUTPUT->render_from_template('report_examtraining/precompiletools', $template);

