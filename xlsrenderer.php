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

defined('MOODLE_INTERNAL') || die();

class report_examtraining_xls_renderer extends plugin_renderer_base {

    /**
     * a raster for printing training results in an XLS sheet.
     *
     */
    public function trainings(&$outputdoc, $startrow, $xlsformats, $userid, $courseid, &$results) {
        global $CFG;

        $examcontext = examtraining_get_context();

        $ratiostr = get_string('ratio', 'report_examtraining');
        $aratiostr = get_string('ratioA', 'report_examtraining');
        $cratiostr = get_string('ratioC', 'report_examtraining');
        $acountstr = get_string('countA', 'report_examtraining');
        $ccountstr = get_string('countC', 'report_examtraining');

        // Global result.
        $outputdoc->write_string($startrow,0, get_string('overalhitstraining', 'report_examtraining'),$xlsformats['t']);
        $outputdoc->merge_cells($startrow,0,$startrow,4);
        $startrow++;

        $outputdoc->write_string($startrow, 0, $ratiostr, $xlsformats['tt']);
        $outputdoc->write_string($startrow, 1, $aratiostr, $xlsformats['tt']);
        $outputdoc->write_string($startrow, 2, $cratiostr, $xlsformats['tt']);
        $outputdoc->write_string($startrow, 3, $acountstr, $xlsformats['tt']);
        $outputdoc->write_string($startrow, 4, $ccountstr, $xlsformats['tt']);
        $startrow++;

        $outputdoc->write_string($startrow, 0,(@$results->hitratio + 0).'%', $xlsformats['p']);
        $outputdoc->write_string($startrow, 1,(@$results->ahitratio + 0).'%', $xlsformats['p']);
        $outputdoc->write_string($startrow, 1,(@$results->chitratio + 0).'%', $xlsformats['p']);
        $outputdoc->write_string($startrow, 1,(@$results->aanswered + 0), $xlsformats['p']);
        $outputdoc->write_string($startrow, 1,(@$results->canswered + 0), $xlsformats['p']);
        $startrow++;

        // Jump line.
        $startrow++;

        // Get main categories.
        if (!empty($results->categories)) {
            $cats = get_records('question_categories', 'parent', $examcontext->rootcategory, 'sortorder,id');

            $categorystr = get_string('categoryname', 'report_examtraining');
            $proposedstr = get_string('proposed', 'report_examtraining');
            $answeredstr = get_string('answered', 'report_examtraining');
            $matchedstr = get_string('matched', 'report_examtraining');
            $ratiostr = get_string('ratio', 'report_examtraining');

            $outputdoc->write_string($startrow,0, get_string('traininghits', 'report_examtraining'), $xlsformats['t']);
            $outputdoc->merge_cells($startrow, 0, $startrow, 4);
            $startrow++;

            $outputdoc->write_string($startrow, 0, $categorystr, $xlsformats['tt']);
            $outputdoc->write_string($startrow, 1, $proposedstr, $xlsformats['tt']);
            $outputdoc->write_string($startrow, 2, $answeredstr, $xlsformats['tt']);
            $outputdoc->write_string($startrow, 3, $matchedstr, $xlsformats['tt']);
            $outputdoc->write_string($startrow, 4, $ratiostr, $xlsformats['tt']);
            $startrow++;

            // Per category result.
            foreach ($cats as $cat) {
                $outputdoc->write_string($startrow, 0, format_string($cat->name), $xlsformats['ctl']);
                $outputdoc->write_string($startrow, 1, @$results->categories[$cat->id]->count_proposed + 0, $xlsformats['p']);
                $outputdoc->write_string($startrow, 2, @$results->categories[$cat->id]->count_answered + 0, $xlsformats['p']);
                $outputdoc->write_string($startrow, 3, @$results->categories[$cat->id]->count_matched + 0, $xlsformats['p']);
                $outputdoc->write_string($startrow, 4, (@$results->categories[$cat->id]->ratio + 0).' %', $xlsformats['p']);
                $startrow++;
            }

        } else {
            $outputdoc->write_string($startrow, 0, get_string('traininghits', 'report_examtraining'), $xlsformats['t']);
            $startrow++;
            $outputdoc->write_string($startrow, 0, get_string('notrainingactivity', 'report_examtraining'), $xlsformats['tt']);
            $startrow++;
        }

        // Jump a line.
        $startrow++;

        return $startrow;
    }

    /**
     * a raster for printing in an xls file 
     * with all the relevant data about a user.
     *
     */
    public function globalrow(&$xlsdoc, $userid, $courseid, &$data, $from, $to, &$row) {
        global $CFG, $COURSE, $DB;

        $examcontext = examtraining_get_context();

        $user = $DB->get_record('user', array('id' => $userid));
        if ($courseid != $COURSE->id) {
            $course = $DB->get_record('course', array('id' => $courseid));
        } else {
            $course = &$COURSE;
        }

        $resultset = array();
        $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

         $col = 0;

        $data = '';
        if (!empty($usergroups)) {
            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == groups_get_course_group($courseid)) {
                    $str = "$str";
                }
                $groupnames[] = $str;
            }
            $data = implode(', ', $groupnames); // entity

        }
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $xlsdoc->write_string($row, $col, $user->id, $xlsformats['pl']);
        $col++;

        $loginfo = examtraining_get_log_reader_info();

        $sql = "
            SELECT
                MIN({$loginfo->timeparam})
            FROM
                {{$loginfo->table}}
            WHERE
                userid = ? AND
                {$loginfo->courseparam} = ?
        ";

        $data = date('d/m/Y', $DB->get_field_sql($sql, array($userid, $courseid))); // Userid.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->lastname))), 'ISO-8859-1', 'UTF-8');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->firstname))), 'ISO-8859-1', 'UTF-8');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = raw_format_duration(@$data->elapsed); // Elapsed time.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = raw_format_duration(@$data->weekelapsed); // Elapsed time this week.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $trainingstats = userquiz_get_user_globals($userid, $examcontext->trainingquizzes, $from, $to);
        $weektrainingstats = userquiz_get_user_globals($userid, $examcontext->trainingquizzes, time() - DAYSECS * 7, time());

        $data = 0 + @$trainingstats[$userid]->answered; // Answered questions on training.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = 0 + @$weektrainingstats[$userid]->answered; // Answered questions on training this week.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = ((0 + @$trainingstats[$userid]->chitratio)).' %'; // Ratio C.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = ((0 + @$weektrainingstats[$userid]->chitratio)).' %'; // Ratio C.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = ((0 + @$trainingstats[$userid]->ahitratio)).' %'; // Ratio A.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = ((0 + @$weektrainingstats[$userid]->ahitratio)).' %'; // Ratio A.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $examstats = userquiz_get_user_globals($userid, $examcontext->examquiz, $from, $to);

        $matchedexams = 0;
        if ($stats = userquiz_get_attempts_stats($userid, $examcontext->examquiz)) {
            foreach ($stats as $attemptid => $attemptres) {
                if ($attemptres->ahitratio * 100 >= $examcontext->rateAserie &&
                        $attemptres->chitratio * 100 >= $examcontext->rateCserie) {
                    $matchedexams++;
                }
            }
        }

        $data = 0 + $matchedexams; // Succeeded exam attempts.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = 0 + @$examstats[$userid]->attempts; // Exam attempts.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $row++; // Passed by reference.
    }

}