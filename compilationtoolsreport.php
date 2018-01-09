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

$template->backgroundercontrolstr = get_string('backgroundercontrol', 'report_examtraining');
$template->backgroundercontroldescstr = get_string('backgroundercontrol_desc', 'report_examtraining');
$template->updatestr = get_string('update');
$template->fromifall = get_string('fromifall', 'report_examtraining');

$template->attemptstocatscompilationstr = get_string('attemptstocatscompilation', 'report_examtraining');
$template->recordsrangestr = get_string('recordsrange', 'report_examtraining');
$template->onlynewstr = get_string('onlynew', 'report_examtraining');
$template->allrecordsstr = get_string('allrecords', 'report_examtraining');
$template->catsstr = get_string('cats', 'report_examtraining');
$template->withcatsstr = get_string('withcats', 'report_examtraining');
$template->withoutcatsstr = get_string('withoutcats', 'report_examtraining');
$template->bulksizestr = get_string('bulksize', 'report_examtraining');
$template->bulksizehelpicon = $OUTPUT->help_icon('bulksize', 'report_examtraining');
$template->nolimitstr = get_string('nolimit', 'report_examtraining');
$template->autoreleasestr = get_string('autorelease', 'report_examtraining');
$template->manualstr = get_string('manual', 'report_examtraining');
$template->secondstr = get_string('second', 'report_examtraining');
$template->secondpluralstr = get_string('secondplural', 'report_examtraining');
$template->maxbulksstr = get_string('maxbulks', 'report_examtraining');
$template->maxbulkshelpicon = $OUTPUT->help_icon('maxbulks', 'report_examtraining');

$template->compilestatstocatsstr = get_string('compilestatstocats', 'report_examtraining');
$template->compilesomestr = get_string('compilesome', 'report_examtraining');
$template->clearstatsdatastr = get_string('clearstatsdata', 'report_examtraining');
$template->userstatsandcoveragestr = get_string('userstatsandcoverage', 'report_examtraining');
$template->compileuserstr = get_string('compileusers', 'report_examtraining');
$template->clearuserstatsstr = get_string('clearuserstats', 'report_examtraining');

$template->usercoverageglobalstr = get_string('usercoverageglobal', 'report_examtraining');

$template->compilecoverageindexstr = get_string('compilecoverageindex', 'report_examtraining');

echo $OUTPUT->render_from_template('report_examtraining/precompiletools', $template);

