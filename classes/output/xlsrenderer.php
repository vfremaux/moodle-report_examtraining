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
namespace report_examtraining\output;

defined('MOODLE_INTERNAL') || die();

class xls_renderer extends \plugin_renderer_base {

    /**
     * a raster for printing training results in an XLS sheet.
     *
     */
    public function trainings(&$xlsdoc, $startrow, $xlsformats, $userid, $courseid, &$results) {
        global $CFG, $DB;

        $examcontext = block_userquiz_monitor_get_block($courseid)->config;

        $ratiostr = get_string('ratio', 'report_examtraining');
        $aratiostr = get_string('ratioa', 'report_examtraining');
        $cratiostr = get_string('ratioc', 'report_examtraining');
        $acountstr = get_string('counta', 'report_examtraining');
        $ccountstr = get_string('countc', 'report_examtraining');

        // Global result.
        $xlsdoc->write_string($startrow, 0, get_string('overalhitstraining', 'report_examtraining'), $xlsformats['t']);
        $xlsdoc->merge_cells($startrow, 0, $startrow, 4);
        $startrow++;

        $xlsdoc->write_string($startrow, 0, $ratiostr, $xlsformats['tt']);
        $xlsdoc->write_string($startrow, 1, $aratiostr, $xlsformats['tt']);
        $xlsdoc->write_string($startrow, 2, $cratiostr, $xlsformats['tt']);
        $xlsdoc->write_string($startrow, 3, $acountstr, $xlsformats['tt']);
        $xlsdoc->write_string($startrow, 4, $ccountstr, $xlsformats['tt']);
        $startrow++;

        $xlsdoc->write_string($startrow, 0, (@$results->qratio + 0).'%', $xlsformats['p']);
        $xlsdoc->write_string($startrow, 1, (@$results->aratio + 0).'%', $xlsformats['p']);
        $xlsdoc->write_string($startrow, 1, (@$results->cratio + 0).'%', $xlsformats['p']);
        $xlsdoc->write_string($startrow, 1, (@$results->acount + 0), $xlsformats['p']);
        $xlsdoc->write_string($startrow, 1, (@$results->ccount + 0), $xlsformats['p']);
        $startrow++;

        // Jump line.
        $startrow++;

        // Get main categories.
        if (!empty($results->categories)) {
            $cats = $DB->get_records('question_categories', 'parent', $examcontext->rootcategory, 'sortorder,id');

            $categorystr = get_string('categoryname', 'report_examtraining');
            $proposedstr = get_string('proposed', 'report_examtraining');
            $answeredstr = get_string('answered', 'report_examtraining');
            $matchedstr = get_string('matched', 'report_examtraining');
            $ratiostr = get_string('ratio', 'report_examtraining');

            $xlsdoc->write_string($startrow, 0, get_string('traininghits', 'report_examtraining'), $xlsformats['t']);
            $xlsdoc->merge_cells($startrow, 0, $startrow, 4);
            $startrow++;

            $xlsdoc->write_string($startrow, 0, $categorystr, $xlsformats['tt']);
            $xlsdoc->write_string($startrow, 1, $proposedstr, $xlsformats['tt']);
            $xlsdoc->write_string($startrow, 2, $answeredstr, $xlsformats['tt']);
            $xlsdoc->write_string($startrow, 3, $matchedstr, $xlsformats['tt']);
            $xlsdoc->write_string($startrow, 4, $ratiostr, $xlsformats['tt']);
            $startrow++;

            // Per category result.
            foreach ($cats as $cat) {
                $xlsdoc->write_string($startrow, 0, format_string($cat->name), $xlsformats['ctl']);
                $xlsdoc->write_string($startrow, 1, @$results->categories[$cat->id]->qsize + 0, $xlsformats['p']);
                $xlsdoc->write_string($startrow, 2, @$results->categories[$cat->id]->qcount + 0, $xlsformats['p']);
                $xlsdoc->write_string($startrow, 3, @$results->categories[$cat->id]->qmatched + 0, $xlsformats['p']);
                $xlsdoc->write_string($startrow, 4, (@$results->categories[$cat->id]->qratio + 0).' %', $xlsformats['p']);
                $startrow++;
            }

        } else {
            $xlsdoc->write_string($startrow, 0, get_string('traininghits', 'report_examtraining'), $xlsformats['t']);
            $startrow++;
            $xlsdoc->write_string($startrow, 0, get_string('notrainingactivity', 'report_examtraining'), $xlsformats['tt']);
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

        if ($courseid != $COURSE->id) {
            $course = $DB->get_record('course', array('id' => $courseid));
        } else {
            $course = &$COURSE;
        }

        $examcontext = block_userquiz_monitor_get_block($course->id)->config;

        $user = $DB->get_record('user', array('id' => $userid));

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
            $data = implode(', ', $groupnames); // Entity.

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

        $data = examtraining_raw_format_duration(@$data->elapsed); // Elapsed time.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = examtraining_raw_format_duration(@$data->weekelapsed); // Elapsed time this week.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $trainingstats = userquiz_get_user_globals($userid, $examcontext->trainingquizzes, $from, $to);
        $weektrainingstats = userquiz_get_user_globals($userid, $examcontext->trainingquizzes, time() - DAYSECS * 7, time());

        if ($userid == 7198) {
            debug_trace("Getting results for report. Xls renderer. ");
            debug_trace($trainingstats);
        }

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

    public function questiondetail(&$xlsdoc, $startrow, $effg, $xlsformats) {
        global $qcategories;

        $xlsdoc->write_string($startrow, 0, get_string('questionsort', 'report_examtraining', $effg->sortorder), $xlsformats['t']);
        $xlsdoc->merge_cells($startrow , 0, $startrow, 6);
        $xlsdoc->merge_cells($startrow + 1, 0, $startrow + 1, 6);
        $xlsdoc->write_string($startrow, 7, $effg->name, $xlsformats['tw']);
        $xlsdoc->merge_cells($startrow, 7, $startrow, 12);

        $startrow++;
        $xlsdoc->write_string($startrow, 7, $effg->questiontext, $xlsformats['tw']);
        $xlsdoc->merge_cells($startrow, 7, $startrow, 12);

        $startrow++;
        $xlsdoc->write_string($startrow, 0, get_string('answers', 'report_examtraining'), $xlsformats['t']);
        $ansnum = 0;
        foreach ($effg->answers as $a) {
            $format = ($a->fraction) ? $xlsformats['t+'] : $xlsformats['t-'];
            $xlsdoc->write_string($startrow, 7, $a->answer, $format);
            $xlsdoc->merge_cells($startrow, 7, $startrow, 12);
            $startrow++;
            $ansnum++;
        }
        $xlsdoc->merge_cells($startrow - $ansnum, 0, $startrow - $ansnum, 6);   // Answer title line.
        $xlsdoc->merge_cells($startrow - $ansnum + 1, 0, $startrow - 1, 6);  // Other answers.

        $givenanswerclass = ($effg->score) ? 'qcorrect' : 'qfailed';
        $givenanswerformat = ($effg->defaultgrade == 1000) ? $xlsformats['t+'] : $xlsformats['t-'];
        $xlsdoc->write_string($startrow, 0, get_string('givenanswer', 'report_examtraining'), $xlsformats['t']);
        $xlsdoc->merge_cells($startrow, 0, $startrow, 6);
        $xlsdoc->write_string($startrow, 7, $effg->answeredtext, $givenanswerformat);
        $xlsdoc->merge_cells($startrow, 7, $startrow, 12);

        $startrow++;
        $xlsdoc->write_string($startrow, 0, get_string('category', 'report_examtraining'), $xlsformats['t']);
        $xlsdoc->merge_cells($startrow, 0, $startrow, 6);
        $xlsdoc->write_string($startrow, 7, $qcategories[$effg->category]->name, $xlsformats['t']);
        $xlsdoc->merge_cells($startrow, 7, $startrow, 12);

        $startrow++;
        $xlsdoc->write_string($startrow, 0, get_string('type', 'report_examtraining'), $xlsformats['t']);
        $xlsdoc->merge_cells($startrow, 0, $startrow, 6);
        $xlsdoc->write_string($startrow, 7, $effg->type, $xlsformats['t']);
        $xlsdoc->merge_cells($startrow, 7, $startrow, 12);

        $startrow++;
        $xlsdoc->write_string($startrow, 0, get_string('score', 'report_examtraining'), $xlsformats['t']);
        $xlsdoc->merge_cells($startrow, 0, $startrow, 6);
        $xlsdoc->write_string($startrow, 7, $effg->score, $xlsformats['t']);
        $xlsdoc->merge_cells($startrow, 7, $startrow, 12);

        return $startrow;
    }

    public function catscores(&$xlsdoc, $startrow, $scores, $xlsformats) {

        $xlsdoc->write_string($startrow, 0, get_string('category', 'report_examtraining'), $xlsformats['t']);
        $xlsdoc->write_string($startrow, 1, $scores->name, $xlsformats['t']);

        if (!empty($scores->atype)) {
            $xlsdoc->write_string($startrow, 2, 'Type A', $xlsformats['t']);
            $xlsdoc->write_string($startrow, 3, sprintf('%0.2f', $scores->aratio), $xlsformats['t']);
            if ($scores->atype) {
                $xlsdoc->write_string($startrow, 3, @$scores->ascore.'/'.@$scores->atype, $xlsformats['t']);
            } else {
                $xlsdoc->write_string($startrow, 3, 0, $xlsformats['t']);
            }
        }
        if (!empty($scores->ctype)) {
            $xlsdoc->write_string($startrow, 2, 'Type C', $xlsformats['t']);
            $xlsdoc->write_string($startrow, 3, sprintf('%0.2f', $scores->cratio), $xlsformats['t']);
            if ($scores->ctype) {
                $xlsdoc->write_string($startrow, 3, @$scores->ascore.'/'.@$scores->atype, $xlsformats['t']);
            } else {
                $xlsdoc->write_string($startrow, 3, 0, $xlsformats['t']);
            }
        }
    }

    public function overralcatscores($worksheet, $startrow, $scores, $xlsformats) {
    }

    /**
     * a raster for xls printing of a report structure header
     * with all the relevant data about a user.
     */
    public function header(&$xlsdoc, $userid, $courseid, $data, $xlsformats) {
        global $DB;

        $loginfo = examtraining_get_log_reader_info();

        $user = $DB->get_record('user', array('id' => $userid));
        $course = $DB->get_record('course', array('id' => $courseid));

        $row = 0;

        $xlsdoc->write_string($row, 0, get_string('progressionreport', 'report_examtraining'), $xlsformats['t']);
        $xlsdoc->merge_cells($row, 0, $row, 12);
        $row++;

        $xlsdoc->write_string($row, 0, get_string('user').' :', $xlsformats['ctr']);
        $xlsdoc->write_string($row, 1, fullname($user), $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $xlsdoc->write_string($row, 0, get_string('email').' :', $xlsformats['ctr']);
        $xlsdoc->write_string($row, 1, $user->email, $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $xlsdoc->write_string($row, 0, get_string('city').' :', $xlsformats['ctr']);
        $xlsdoc->write_string($row, 1, $user->city, $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $xlsdoc->write_string($row, 0, get_string('institution').' :', $xlsformats['ctr']);
        $xlsdoc->write_string($row, 1, $user->institution, $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $xlsdoc->write_string($row, 0, get_string('course', 'report_examtraining').' :', $xlsformats['ctr']);
        $xlsdoc->write_string($row, 1, $course->fullname, $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $xlsdoc->write_string($row, 0, get_string('from').' :', $xlsformats['ctr']);
        $xlsdoc->write_string($row, 1, userdate($data->from), $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $xlsdoc->write_string($row, 0, get_string('to').' :', $xlsformats['ctr']);
        $xlsdoc->write_string($row, 1, userdate($data->to), $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

        // Print group status.
        $xlsdoc->write_string($row, 0, get_string('groups').' :', $xlsformats['ctr']);
        $str = '';
        if (!empty($usergroups)) {
            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == groups_get_course_group($courseid)) {
                    $str = "[$str]";
                }
                $groupnames[] = $str;
            }
            $str = implode(', ', $groupnames);
        }
        $xlsdoc->write_string($row, 1, $str, $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $context = context_course::instance($courseid);
        $xlsdoc->write_string($row, 0, get_string('roles').' :', $xlsformats['ctr']);
        $xlsdoc->write_string($row, 1, strip_tags(get_user_roles_in_context($userid, $context)), $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $xlsdoc->write_string($row, 0, get_string('ratioa', 'report_examtraining'), $xlsformats['ctr']);
        $xlsdoc->write_string($row, 1, 0 + @$data->aratio.' %', $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $xlsdoc->write_string($row, 0, get_string('ratioc', 'report_examtraining'), $xlsformats['ctr']);
        $xlsdoc->write_string($row, 1, 0 + @$data->cratio.' %', $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $xlsdoc->write_string($row, 0, get_string('elapsed', 'report_examtraining').' :', $xlsformats['ctr']);
        $xlsdoc->write_number($row, 1, examtraining_reports_format_time(0 + @$data->elapsed, 'xls'), $xlsformats['ztl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $sql = "
            SELECT
                MIN(".$loginfo->timeparam.") as mintime
            FROM
                {".$loginfo->table."}
            WHERE
                userid = ?
        ";
        $firstcon = $DB->get_record_sql($sql, array($userid));
        $xlsdoc->write_string($row, 0, get_string('firstconnection', 'report_examtraining').' :', $xlsformats['ctr']);
        $mintime = ($firstcon->mintime) ? userdate($firstcon->mintime) : get_string('never');
        $xlsdoc->write_string($row, 1, $mintime, $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        $sql = "
            SELECT
                MAX(".$loginfo->timeparam.") as maxtime
            FROM
                {".$loginfo->table."}
            WHERE
                userid = ?
        ";
        $lastcon = $DB->get_record_sql($sql, array($userid));
        $xlsdoc->write_string($row, 0, get_string('lastconnection', 'report_examtraining').' :', $xlsformats['ctr']);
        $maxtime = ($lastcon->maxtime) ? userdate($lastcon->maxtime) : get_string('never');
        $xlsdoc->write_string($row, 1, $maxtime, $xlsformats['pl']);
        $xlsdoc->merge_cells($row, 1, $row, 12);
        $row++;

        // Jump a line.
        $row++;

        return $row;
    }

    /**
     *
     *
     */
    public function globalheader(&$xlsdoc, &$xlsformats, &$row) {

        $col = 0;

        $resultset[] = get_string('entity', 'report_examtraining'); // Groupname.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('id', 'report_examtraining'); // Userid.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('startdate', 'report_examtraining'); // Start date.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('lastname', 'report_examtraining'); // Last name.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('firstname', 'report_examtraining'); // First name.
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('timeelapsed', 'report_examtraining');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('timeelapsedcurweek', 'report_examtraining');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('answeredquestions', 'report_examtraining');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('answeredquestionscurweek', 'report_examtraining');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('ratioa', 'report_examtraining');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('ratioacurweek', 'report_examtraining');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('ratioc', 'report_examtraining');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('ratioccurweek', 'report_examtraining');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $resultset[] = get_string('examsuccess', 'report_examtraining');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;

        $data = get_string('examattempts', 'report_examtraining');
        $xlsdoc->write_string($row, $col, $data, $xlsformats['pl']);
        $col++;
    }

    /**
     * STATUS : SEEMS NOT USED
     * a raster for printing exam results in XSL.
     */
    public function exams(&$xlsdoc, $results, $startrow, $xlsformats, $userid) {
        global $COURSE;

        $examcontext = block_userquiz_monitor_get_block($COURSE->id)->config;

        $datestr = get_string('date', 'report_examtraining');
        $tryindexstr = get_string('tryindex', 'report_examtraining');
        $ratiostr = get_string('ratio', 'report_examtraining');
        $aratiostr = get_string('ratioa', 'report_examtraining');
        $cratiostr = get_string('ratioc', 'report_examtraining');
        $acountstr = get_string('counta', 'report_examtraining');
        $ccountstr = get_string('countc', 'report_examtraining');

        $xlsdoc->write_string($startrow, 0, get_string('examtries', 'report_examtraining'), $xlsformats['t']);
        $xlsdoc->merge_cells($startrow, 0, $startrow, 6);
        $startrow++;

        $xlsdoc->write_string($startrow, 0, $tryindexstr, $xlsformats['tt']);
        $xlsdoc->write_string($startrow, 1, $datestr, $xlsformats['tt']);
        $xlsdoc->write_string($startrow, 2, $ratiostr, $xlsformats['tt']);
        $xlsdoc->write_string($startrow, 3, $aratiostr, $xlsformats['tt']);
        $xlsdoc->write_string($startrow, 4, $cratiostr, $xlsformats['tt']);
        $xlsdoc->write_string($startrow, 5, $acountstr, $xlsformats['tt']);
        $xlsdoc->write_string($startrow, 6, $ccountstr, $xlsformats['tt']);
        $startrow++;

        ksort($results->attempts);
        $i = 1;
        $previous = null;
        if (!empty($results->attempts)) {
            foreach ($results->attempts as $attemptid => $attemptres) {

                // Fix ratios for exam because exam must always propose 100 questions.
                $attemptres->ratio = $attemptres->ratio * $attemptres->count_proposed / 100;

                $xlsdoc->write_string($startrow, 0, $i, $xlsformats['p']);
                $timevalue = examtraining_reports_format_time($attemptres->timefinish, 'xls');
                $xlsdoc->write_string($startrow, 1, $timevalue, $xlsformats['zt']);
                $xlsdoc->write_string($startrow, 2, ($attemptres->ratio + 0).' %', $xlsformats['p']);
                $xlsdoc->write_string($startrow, 3, (@$attemptres->ratio_A + 0).' %', $xlsformats['p']);
                $xlsdoc->write_string($startrow, 4, (@$attemptres->ratio_C + 0).' %', $xlsformats['p']);
                $xlsdoc->write_string($startrow, 5, @$attemptres->count_answered_A + 0, $xlsformats['p']);
                $xlsdoc->write_string($startrow, 6, @$attemptres->count_answered_A + 0, $xlsformats['p']);
                $startrow++;

                $i++;
            }
        } else {
            $xlsdoc->write_string($startrow, 0, get_string('examtries', 'report_examtraining'), $xlsformats['t']);
            $xlsdoc->merge_cells($startrow, 0, $startrow, 6);
            $startrow++;
            $xlsdoc->write_string($startrow, 0, get_string('noexamtries', 'report_examtraining'), $xlsformats['tt']);
            $startrow++;
        }

        // Jump a line.
        $startrow++;
        return $startrow;
    }

}