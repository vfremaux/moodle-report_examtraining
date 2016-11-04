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

require_once($CFG->dirroot.'/blocks/userquiz_monitor/block_userquiz_monitor_lib.php');
require_once($CFG->libdir.'/questionlib.php');
require_once($CFG->dirroot.'/report/examtraining/statscompilelib.php');
require_once($CFG->dirroot.'/report/examtraining/excelformats.php');

/**
 * Returns proper info to query log
 */
function examtraining_get_log_reader_info() {

    $logmanager = get_log_manager();
    $readers = $logmanager->get_readers('\core\log\sql_select_reader');
    $reader = reset($readers);

    if (empty($reader)) {
        echo 'No reader';
        return null; // No log reader found.
    }

    if ($reader instanceof \logstore_standard\log\store) {
        $readerinfo = new StdClass;
        $readerinfo->courseparam = 'courseid';
        $readerinfo->table = 'logstore_standard_log';
        $readerinfo->timeparam = 'timecreated';
        $readerinfo->loggedin = 'loggedin';
    } else if ($reader instanceof \logstore_legacy\log\store) {
        $readerinfo = new StdClass;
        $readerinfo->courseparam = 'course';
        $readerinfo->table = 'log';
        $readerinfo->timeparam = 'time';
        $readerinfo->loggedin = 'loggin';
    } else {
        return null;
    }

    return $readerinfo;
}

function count_questions_in_categories_rec($rootcatid, &$cats) {
    global $DB;

    static $level = 0;
    static $countcache = array();

    if (!isset($countcache[$level])) {

        // Count real questions.
        $select = " category = ? AND parent = 0 AND hidden = 0 ";
        $cats->count = $DB->count_records_select('question', $select, array($rootcatid));
        $select = "category = ? AND parent = 0 AND defaultmark = 1 AND hidden = 0 ";
        $cats->count_a = $DB->count_records_select('question', $select, array($rootcatid));
        $select = "category = ? AND parent = 0 AND defaultmark = 1000 AND hidden = 0 ";
        $cats->count_c = $DB->count_records_select('question', $select, array($rootcatid));

        $childs = $DB->get_records('question_categories', array('parent' => $rootcatid), 'id,id');

        if ($childs) {
            $cats->subs = array();
            foreach ($childs as $subcat) {
                if ($subcat->id == $rootcatid) {
                    continue;
                }
                $subs = new StdClass;
                $level++;
                count_questions_in_categories_rec($subcat->id, $subs);
                $level--;
                $cats->count += $subs->count;
                $cats->count_a += $subs->count_a;
                $cats->count_c += $subs->count_c;
                $cats->subs[$subcat->id] = $subs;
            }
            $countcache[$level]->count = $cats->count;
            $countcache[$level]->count_a = $cats->count_a;
            $countcache[$level]->count_c = $cats->count_c;
            $countcache[$level]->subs = $cats->subs;
        }
    } else {
        $cats->count = $countcache[$level]->count;
        $cats->count_a = $countcache[$level]->count_a;
        $cats->count_c = $countcache[$level]->count_c;
        $cats->subs = &$countcache[$level]->subs;
    }
}

// RASTERS FOR OUTPUT ***************************************************************.

/**
 * a raster for printing training results in an XLS sheet.
 */
function examtraining_reports_print_trainings_xls(&$xlsdoc, $startrow, $xlsformats, $userid, $courseid, &$results) {
    global $CFG;

    include_once($CFG->dirroot.'/report/examtraining/xlsrenderer.php');
    $renderer = new report_examtraining_xls_renderer($xlsdoc, $startrow, $xlsformats, $userid, $courseid, $results);
    return $renderer->trainings();
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function examtraining_reports_print_exams_summary_html($userid, $from, $to) {
    global $PAGE;

    $renderer = $PAGE->get_renderer('report_examtraining');
    echo $renderer->exams_summary($userid, $from, $to);
}

/**
 * a raster for printing exam results in XSL.
 */
function examtraining_reports_print_exams_xls(&$xlsdoc, $startrow, $xlsformats, $userid) {

    $examcontext = examtraining_get_context();

    $datestr = get_string('date', 'report_examtraining');
    $tryindexstr = get_string('tryindex', 'report_examtraining');
    $ratiostr = get_string('ratio', 'report_examtraining');
    $aratiostr = get_string('ratioA', 'report_examtraining');
    $cratiostr = get_string('ratioC', 'report_examtraining');
    $acountstr = get_string('countA', 'report_examtraining');
    $ccountstr = get_string('countC', 'report_examtraining');

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

/**
 * a raster for html printing of a radar.
 *
 * @param array $data 12 categories mastering array
 */
function examtraining_reports_print_radar_html($userid, $from, $to) {
    global $DB, $OUTPUT;

    $examcontext = examtraining_get_context();

    // Get mastering indicators in subcategories.
    $subcats = new Stdclass;
    $subcatdata = count_questions_in_categories_rec($examcontext->rootcategory, $subcats);
    $quizzes = implode(',', $examcontext->testquizzes);
    $matched = userquiz_get_user_subcats($userid, $quizzes, $from, $to);

    // For each root cat, calculate the hitratio.
    $maincats = $DB->get_records('question_categories', 'parent', $examcontext->rootcategory, 'sortorder', 'id, name');
    $radardata = array();
    $radarheaders = array();
    if ($matched) {
        foreach ($maincats as $id => $cat) {
            if (!empty($matched[$cat->id]->qcount)) {
                $overalratio = (@$matched[$cat->id]->amatched + @$matched[$cat->id]->cmatched) / $matched[$cat->id]->qcount * 100;
            } else {
                $overalratio = 0;
            }
            $radardata[] = $overalratio;
            $radarheaders[] = substr($cat->name, 0, 14);
        }
    }

    echo $OUTPUT->heading(get_string('mastering', 'report_examtraining'));
    echo '<center>';

    $radararg = implode(',', $radardata);
    $headersarg = implode(',', $radarheaders);
    $params = array('radar' => $radararg, 'headers' => $headersarg);
    $generatorurl = new moodle_url('/report/examtraining/gdgenerators/radargraph.php', $params);
    echo '<img src="'.$generatorurl.'" width="500" height="500" />';
    echo '</center>';
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function examtraining_reports_print_knowledge_covering_html($userid, $courseid, $from, $to) {
    global $OUTPUT;

    echo $OUTPUT->heading(get_string('knowledgecovering', 'report_examtraining'));
    echo '<center>';
    $params = array('userid' => $userid, 'course' => $courseid, 'from' => $from, 'to' => $to);
    $generatorurl = new moodle_url('/report/examtraining/gdgenerators/knowledgetag.php', $params);
    echo '<img src="'.$generatorurl.'" width="300" height="300" />';
    echo $OUTPUT->box(get_string('knowledgecoveringlegend', 'report_examtraining'));
    echo '</center>';
}

/**
 * a raster for html printing of a report structure header
 * with all the relevant data about a user.
 * @param int $userid
 * @param int $courseid
 */
function examtraining_reports_print_header_html($userid, $courseid, $data, $isshort = false) {
    global $DB;

    $user = $DB->get_record('user', 'id', $userid);
    $course = $DB->get_record('course', 'id', $courseid);

    echo '<table width="100%" style="border:1px solid #A0A0A0">';
    echo '<tr><td align="left" width="20%">';
    echo fullname($user);
    echo '</td><td align="center" width="20%">';

    // Get group.
    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');
    // Print group status.

    if (!empty($usergroups)) {
        foreach ($usergroups as $group) {
            $str = $group->name;
            if ($group->id == get_current_group($courseid)) {
                $str = "<b>$str</b>";
            }
            $groupnames[] = $str;
        }
        echo implode(', ', $groupnames);
    }

    echo '</td><td align="center" width="20%">';
    echo "<a href=\"mailto:{$user->email}\">$user->email</a>";
    echo '</td><td align="center" width="20%">';
    echo $user->city;
    echo '</td><td align="right" width="20%">';
    if ($isshort) {
        $params = array('view' => 'user', 'id' => $courseid, 'userid' => $userid);
        $url = new moodle_url('/report/examtraining/index.php', $params);
        echo '<a href="'.$url.'">'.get_string('seedetails', 'report_examtraining').'</a>';
    }
    echo '</td></tr>';
    echo '</table>';
}

function examtraining_reports_print_times_html($userid, &$data) {
    global $DB, $OUTPUT;

    $loginfo = examtraining_get_log_reader_info();

    echo $OUTPUT->heading(get_string('times', 'report_examtraining'));

    $select = " action = ? AND userid = ? ";
    $params = array($loginfo->loggedin, $userid);
    $firstaccess = 0 + $DB->get_field_select($loginfo->table, 'MIN('.$loginfo->timeparam.')', $select, $params);
    $lastaccess = 0 + $DB->get_field_select($loginfo->table, 'MAX('.$loginfo->timeparam.')', $select, $params);
    $cnx->count = $DB->count_records_select($loginfo->table, $select, $params);
    $tendaysbefore = time() - DAYSECS * 10;
    $select = "  action = ? AND userid = ? AND ".$loginfo->timeparam." > ? ";
    $cnx->lastcount = $DB->count_records_select($loginfo->table, $select, array($loginfo->loggedin, $userid, $tendaysbefore));

    // First row.
    echo '<table width="100%" style="border:1px solid #A0A0A0;padding:2px" cellspacing="2">';
    echo '<tr>';
    echo '<td align="left"><b>';
    print_string('firstaccess', 'report_examtraining');
    echo ' : </b></td>';
    echo '<td align="left">';
    echo userdate($firstaccess);
    echo '</td>';
    echo '<td align="left"><b>';
    print_string('lastaccess', 'report_examtraining');
    echo ' : </b></td>';
    echo '<td align="left">';
    echo userdate($lastaccess);
    echo '</td>';
    echo '</tr>';

    // Second row.
    echo '<tr>';
    echo '<td align="left"><b>';
    print_string('connections', 'report_examtraining');
    echo ' : </b></td><td colspan="3" align="left">';
    echo get_string('connectionscount', 'report_examtraining', $cnx);
    echo '</td>';
    echo '</tr>';

    // Third row.
    // Start printing the overall times.
    echo '<tr>';
    echo '<td align="left"><b>';
    print_string('equlearningtime', 'report_examtraining');
    echo '</b></td><td colspan="3" align="left">';
    echo examtraining_reports_format_time(0 + @$data->elapsed, 'html');
    echo '</td>';
    echo '</tr>';

    echo '</table>';
}

/**
 *
 *
 */
function examtraining_reports_print_globalheader_xls(&$xlsdoc, &$xlsformats, &$row) {

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
 * special time formating
 *
 */
function examtraining_reports_format_time($timevalue, $mode = 'html') {

    if ($timevalue) {
        if ($mode == 'html') {
            return ceil($timevalue / HOURSECS).' '.get_string('hours', 'report_examtraining');
        } else {
            // For excel time format we need have a fractional day value.
            return  $timevalue / DAYSECS;
        }
    } else {
        return get_string('visited', 'report_examtraining');
    }
}

/**
 * a raster for xls printing of a report structure header
 * with all the relevant data about a user.
 */
function examtraining_reports_print_header_xls(&$xlsdoc, $userid, $courseid, $data, $xlsformats) {
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

    $xlsdoc->write_string($row, 0, get_string('ratioA', 'report_examtraining'), $xlsformats['ctr']);
    $xlsdoc->write_string($row, 1, 0 + @$data->ahitratio.' %', $xlsformats['pl']);
    $xlsdoc->merge_cells($row, 1, $row, 12);
    $row++;

    $xlsdoc->write_string($row, 0, get_string('ratioC', 'report_examtraining'), $xlsformats['ctr']);
    $xlsdoc->write_string($row, 1, 0 + @$data->chitratio.' %', $xlsformats['pl']);
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
 * initializes a new amf_ with static formats
 * @param int $userid
 * @param int $startrow
 * @param array $xlsformats
 * @param object $workbook
 * @return the initialized xlsdoc.
 */
function examtraining_reports_init_worksheet($userid, &$xlsformats, &$workbook, $columndef = null) {
    global $DB;

    $user = $DB->get_record('user', array('id' => $userid));
    $sheettitle = mb_convert_encoding(fullname($user), 'ISO-8859-1', 'UTF-8');
    $xlsdoc =& $workbook->add_worksheet($sheettitle);
    $xlsdoc->hide_gridlines();

    if (is_null($columndef)) {
        $xlsdoc->set_column(0, 0, 48);
        $xlsdoc->set_column(1, 6, 11);
    } else {
        foreach ($columndef as $def) {
            list($start, $end, $width) = $def;
            $xlsdoc->set_column($start, $end, $width);
        }
    }

    return $xlsdoc;
}

/**
 * get participating objects to this training context, knowing the course.
 * We have to fetch the userquiz_monitor block configuration matching this course.
 */
function examtraining_get_context($courseid = 0, $passthru = false) {
    global $COURSE, $DB;

    if (!$courseid) {
        $courseid = $COURSE->id;
    }

    $coursecontext = context_course::instance($COURSE->id);
    $params = array('blockname' => 'userquiz_monitor', 'parentcontextid' => $coursecontext->id);
    if (!$instance = $DB->get_record('block_instances', $params)) {
        if (!$passthru) {
            print_error('no userquiz monitor here', 'block_userquiz_monitor');
        }
        return false;
    }

    $theblock = block_instance('userquiz_monitor', $instance);
    $theblock->config->instanceid = $instance->id;
    return $theblock->config;
}

/**
 * recursively get all question ids
 */
function examtraining_reports_get_questions_rec($catid, &$questionids) {
    global $DB;
    static $level = 0;

    $select = " category = ? AND parent = 0 ";
    if ($questions = $DB->get_records_select('question', $select, array($catid), 'id', 'id,name,category')) {
        foreach ($questions as $q) {
            if (!in_array($q->id, $questionids)) {
                $questionids[] = $q->id;
            }
        }
    }

    if ($subcats = $DB->get_records('question_categories', array('parent' => $catid), 'sortorder,id', 'id, name')) {
        foreach ($subcats as $c) {
            $level++;
            examtraining_reports_get_questions_rec($c->id, $questionids);
            $level--;
        }
    }
}

/*
 * Overloads weblib.php function to get it more usable
 *
 */

/**
 * Prints form items with the names $day, $month and $year
 *
 * @param string $day   fieldname
 * @param string $month  fieldname
 * @param string $year  fieldname
 * @param int $currenttime A default timestamp in GMT
 * @param boolean $return
 */
function examtraining_print_date_selector($day, $month, $year, $currenttime = 0, $return = false, $from = 1970, $to = 2020) {

    if (!$currenttime) {
        $currenttime = time();
    }
    $currentdate = usergetdate($currenttime);

    for ($i = 1; $i <= 31; $i++) {
        $days[$i] = $i;
    }
    for ($i = 1; $i <= 12; $i++) {
        $months[$i] = userdate(gmmktime(12, 0, 0, $i, 15, 2000), "%B");
    }
    for ($i = $from; $i <= $to; $i++) {
        $years[$i] = $i;
    }

    // Build or print result.

    $result = '';

    /*
     * Note: There should probably be a fieldset around these fields as they are
     * clearly grouped. However this causes problems with display. See Mozilla
     * bug 474415
     */
    $result .= '<label class="accesshide" for="menu'.$day.'">'.get_string('day','form').'</label>';
    $result .= html_writer::select($days,   $day,   $currentdate['mday']);
    $result .= '<label class="accesshide" for="menu'.$month.'">'.get_string('month','form').'</label>';
    $result .= html_writer::select($months, $month, $currentdate['mon']);
    $result .= '<label class="accesshide" for="menu'.$year.'">'.get_string('year','form').'</label>';
    $result .= html_writer::select($years,  $year,  $currentdate['year']);

    if ($return) {
        return $result;
    } else {
        echo $result;
    }
}

/**
 *
 *
 */
function examtraining_print_questionstats($orderby) {
    global $COURSE, $USER, $DB;

    $examcontext = examtraining_get_context();

    $sql = "
        SELECT
            qc.questionid,
            q.name,
            SUM(usecount > 0) as usecount,
            SUM(matchcount > 0) as matchcount
        FROM
            {userquiz_monitor_coverage} qc,
            {question} q
        WHERE
            qc.questionid = q.id AND
            blockid = ?
        GROUP BY
            questionid
        ORDER BY
            q.name
    ";

    $questionlines = $DB->get_records_sql($sql, array($examcontext->instanceid));

    $i = 1;
    foreach ($questionlines as $elm) {
        $data[0][0][] = $i;
        $data[0][1][] = $elm->usecount;
        $data[0][2][] = preg_replace('/\s.*$/', '', $elm->name);
        $data[1][$i] = $elm->matchcount;
        $data[2][$i] = round(($elm->usecount - $elm->matchcount) / $elm->usecount * 100);
        $i++;
    }

    jqplot_print_questionuse_graph($data, get_string('questionusage', 'report_examtraining'), 'quse');

    $data = array();
    for ($i = 0; $i <= 20; $i++) {
        $data[''.($i * 5)] = 0;
    }

    foreach ($questionlines as $elm) {
        $errorratio = round(($elm->usecount - $elm->matchcount) / $elm->usecount * 100);
        $data[''.(round($errorratio / 20 * 4) * 5)] = @$data[''.(round($errorratio / 20 * 4)) * 5] + 1;
        $i++;
    }

    jqplot_print_simple_bargraph($data, get_string('errorratio', 'report_examtraining'), 'qerrors');

    $sql = "
        SELECT
            qc.questionid,
            q.name,
            q.createdby,
            (SUM(usecount > 0) - SUM(matchcount > 0)) / SUM(usecount > 0) * 100". sql_as()." errorrate,
            SUM(usecount) as totaluse
        FROM
            {userquiz_monitor_coverage} qc,
            {question} q
        WHERE
            qc.questionid = q.id AND
            blockid = ?
        GROUP BY
            questionid
        HAVING
            SUM(usecount) > 0
        ORDER BY
            errorrate $orderby
        LIMIT 0, 50
    ";

    if ($errorquestions = $DB->get_records_sql($sql, array($examcontext->instanceid))) {

        $errorratestr = get_string('errorrate', 'report_examtraining');
        $qnamestr = get_string('qname', 'report_examtraining');
        $totalusestr = get_string('totaluse', 'report_examtraining');

        $table = new html_table();
        $table->head = array("<b>$errorratestr</b<", "<b>$totalusestr</b>", "<b>$qnamestr</b>");
        $table->align = array('left', 'center', 'left');
        $table->width = '100%';
        $table->size = array('10%', '10%', '80%');

        foreach ($errorquestions as $errq) {
            $qlink = new moodle_url('/question/question.php', array('id' => $errq->questionid, 'courseid' => $COURSE->id));
            $caneditq = has_capability('moodle/question:editall', context_course::instance($COURSE->id)) ||
                    ($errq->createdby = $USER->id &&
                            has_capability('moodle/question:editmine', context_course::instance($COURSE->id)));
            $qname = ($caneditq) ? '<a href="'.$qlink.'">'.$errq->name.'</a>' : $errq->name;
            $table->data[] = array($errq->errorrate, $errq->totaluse, $qname);
        }
        print_table($table);
    }
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function examtraining_reports_print_modules_html($userid, $from, $to) {

    $modulestr = get_string('seriesize', 'report_examtraining');
    $attemptsstr = get_string('series', 'report_examtraining');

    $modulecount = examtraining_get_module_count($userid, $from, $to);

    jqplot_print_modules_bargraph($modulecount, get_string('permodule', 'report_examtraining'), 'permodule');
}

function examtraining_get_module_count($userid, $from, $to) {
    global $DB;

    $examcontext = examtraining_get_context();
    $testquizzes = implode("','", $examcontext->trainingquizzes);

    $fromclause = ($from) ? " AND qa.timefinish > $from " : '';
    $toclause = ($to) ? " AND qa.timefinish < $to " : '';

    // Compute attempts "per module size".

    $sql = "
        SELECT
            qcount,
            COUNT(qa.id) as acount
        FROM
            {quiz_attempts} qa
        LEFT JOIN
            {userquiz_attempts} ua
        ON
            qa.uniqueid = ua.uniqueid
        WHERE
            userid = ? AND
            quiz IN ('$testquizzes ')
            $fromclause
            $toclause
        GROUP BY
            qcount
        ORDER BY
            qcount
    ";

    return $DB->get_records_sql_menu($sql, array($userid));
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 * TODO : Mark for obolescence.
 */
function examtraining_reports_print_assiduity_html($userid, $from, $to) {
    global $DB;

    $modulestr = get_string('assiduity', 'report_examtraining');
    $attemptsstr = get_string('attempts', 'userquiz');

    $examcontext = examtraining_get_context();

    $fromclause = ($from) ? " AND qa.timefinish > $from " : '';
    $toclause = ($to) ? " AND qa.timefinish < $to " : '';

    // Compute attempts "per day".

    $sql = "
        SELECT
            timefinish * 1000,
            COUNT(id) as acount
        FROM
            {quiz_attempts} qa
        WHERE
            userid = ?
            $fromclause
            $toclause
        GROUP BY
            DAY(FROM_UNIXTIME(qa.timefinish))
        ORDER BY
            qa.timefinish
    ";

    if ($assiduity = $DB->get_records_sql_menu($sql, array($userid))) {

        $labels = array(
            array(
                'label' => get_string('assiduity', 'report_examtraining'),
                'lineWidth' => 4,
                'color' => '#40E040',
                'showMarker' => 'false'
            ),
        );

        $assiduityarr[] = array_keys($assiduity);
        $assiduityarr[] = array_values($assiduity);

        $lbl1 = get_string('assiduity', 'report_examtraining');
        $lbl2 = get_string('attemptquantity', 'report_examtraining');
        jqplot_print_timecurve_bars($assiduityarr, $lbl1, 'assiduity', $labels, $lbl2);
    }
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function examtraining_reports_print_assiduity2_html($userid, $from, $to) {
    global $DB;

    $modulestr = get_string('assiduity', 'report_examtraining');
    $attemptsstr = get_string('attempts', 'userquiz');

    $examcontext = examtraining_get_context();

    $fromclause = ($from) ? " AND qa.timefinish > $from " : '';
    $toclause = ($to) ? " AND qa.timefinish < $to " : '';

    // Compute attempts "per day".

    $sql = "
        SELECT
            UNIX_TIMESTAMP(DATE(FROM_UNIXTIME(timefinish))) as daystamp,
            COUNT(id) as acount
        FROM
            {quiz_attempts} qa
        WHERE
            userid = ?
            $fromclause
            $toclause
        GROUP BY
            DAY(FROM_UNIXTIME(timefinish))
        ORDER BY
            daystamp
    ";

    if ($assiduity = $DB->get_records_sql_menu($sql, array($userid))) {

        $dateticks = array_keys($assiduity);

        $firstdate = $dateticks[0];
        $lastdate = $dateticks[count($dateticks) - 1];

        // Rebuild an unholed array for bargraphs.
        $stamp = $firstdate;
        $i = 0;
        $attemptstable = array();
        while ($stamp < $lastdate && ($i < 500)) {
            $attemptstable[date('Y-m-d', $stamp)] = 0 + @$assiduity[$stamp];
            $stamp += DAYSECS;
            $i++;
        }

        $label = get_string('assiduity', 'report_examtraining');
        jqplot_print_assiduity_bargraph($attemptstable, array_keys($attemptstable), $label, 'assiduity');
    }
}

/**
 * returns the lost of the groups of the user.
 */
function examtraining_get_grouplist($courseid, $userid) {
    global $DB;

    $groupnames = array();

    $usergroupings = groups_get_user_groups($courseid, $userid);
    foreach ($usergroupings as $grouping) {
        foreach ($grouping as $groupid) {
            $groupnames[] = $DB->get_field('groups', 'name', array('id' => $groupid));
        }
    }

    return implode(', ', $groupnames);
}

function examtraining_compute_results($userid, $from, $to, $part, $attemptid = 0) {
    global $USER, $CFG, $DB;
    global $qcategories;
    global $questions;

    // Init structure.

    $examcontext = examtraining_get_context();

    // We get all states.
    if ($part == 'training') {
        $quizzeslist = implode("','", $examcontext->trainingquizzes);
    } else {
        $quizzeslist = str_replace(',', "','", $examcontext->examquiz);
    }

    // Category cache.
    if (empty($questions)) {
        $questions = $DB->get_records('question', array(), 'id', 'id,defaultgrade,category');
    }
    if (empty($qcategories)) {
        $qcategories = get_records('question_categories', array(), 'id', 'id,parent');
    }

    // Prefetch categories structure.

    $cats = new StdClass;
    $totalquestions = count_questions_in_categories_rec($examcontext->rootcategory, $cats);

    // Compute results.

    $results = new StdClass;
    $results->categories = array();
    $results->attempts = array();
    $results->items = 0;
    $results->done = 0;

    if (empty($attemptid)) {
        $select = " userid = ? AND timefinish > ? AND timefinish < ? AND quiz IN ('$quizzeslist') ";
        $attempts = $DB->get_records_select('quiz_attempts', $select, array($userid, $from, $to));
    } else {
        $select = " id = ? ";
        $attempts = $DB->get_records_select('quiz_attempts', $select, array($attemptid));
    }

    if ($attempts) {
        foreach ($attempts as $attempt) {
            if ($statesrs = get_all_user_records($attempt->uniqueid, $userid, null, true)) {
                if ($statesrs->valid()) {
                    foreach ($statesrs as $state) {

                        // Compute answers in states against question answers determining question type.
                        if (!$question = &$questions[$state->question]) {
                            continue;
                        }
                        $cattype = ($question->defaultmark == 1) ? 'A' : 'C';
                        $ht = "hastype_$cattype";
                        $ca = "count_answered_$cattype";
                        $cp = "count_proposed_$cattype";
                        $cm = "count_matched_$cattype";
                        $cat = "count_answered";
                        $cpt = "count_proposed";
                        $cmt = "count_matched";

                        // Aggregate upper category till rootcategory.
                        $currentcat = &$qcategories[$question->category];

                        if ($state->grade > 0) {
                            @$results->attempts[$attempt->id]->{$cm}++;
                            @$results->attempts[$attempt->id]->{$cmt}++;
                        }
                        if (strstr($state->answer, ':') !== false) {
                            @$results->attempts[$attempt->id]->{$ca}++;
                            @$results->attempts[$attempt->id]->{$cat}++;
                        }
                        @$results->attempts[$attempt->id]->{$cp}++;
                        @$results->attempts[$attempt->id]->{$cpt}++;
                        $results->attempts[$attempt->id]->timefinish = $attempt->timefinish;
                        $results->modules[$attempt->userquiz][$attempt->id] = 1; // To count frequency of use of questionset.
    
                        do {
                            $previouscatid = $currentcat->id;
                            $results->categories[$currentcat->id]->{$ht} = 1;
                            if ($state->grade > 0) {
                                $results->categories[$currentcat->id]->{$cm}++;
                                $results->categories[$currentcat->id]->{$cmt}++;
                            }
                            if (strstr($state->answer, ':') !== false) {
                                $results->categories[$currentcat->id]->{$ca}++;
                                $results->categories[$currentcat->id]->{$cat}++;
                            }
                            $results->categories[$currentcat->id]->{$cp}++;
                            $results->categories[$currentcat->id]->{$cpt}++;
                            $currentcat = &$qcategories[$currentcat->parent];
                        } while ($currentcat && ($previouscatid != $examcontext->rootcategory));
                    }
                }
                $statesrs->close();
            }
        }
    }

    // Post compute ratios.
    if (!empty($results->categories)) {
        foreach (array_keys($results->categories) as $catid) {
            if (@$results->categories[$catid]->count_answered_A) {
                $ratio = @$results->categories[$catid]->count_matched_A / $results->categories[$catid]->count_answered_A;
                $results->categories[$catid]->hitratio_A = round($ratio * 100);
            } else {
                $results->categories[$catid]->hitratio_A = 0;
            }
            if (@$results->categories[$catid]->count_proposed_A) {
                $ratio = @$results->categories[$catid]->count_matched_A / $results->categories[$catid]->count_proposed_A;
                $results->categories[$catid]->ratio_A = round($ratio * 100);
            } else {
                $results->categories[$catid]->ratio_A = 0;
            }
            if (@$results->categories[$catid]->count_answered_C) {
                $ratio = @$results->categories[$catid]->count_matched_C / $results->categories[$catid]->count_answered_C;
                $results->categories[$catid]->hitratio_C = round($ratio * 100);
            } else {
                $results->categories[$catid]->hitratio_C = 0;
            }
            if (@$results->categories[$catid]->count_proposed_C) {
                $ratio = @$results->categories[$catid]->count_matched_C / $results->categories[$catid]->count_proposed_C;
                $results->categories[$catid]->ratio_C = round($ratio * 100);
            } else {
                $results->categories[$catid]->ratio_C = 0;
            }
            if (@$results->categories[$catid]->count_answered) {
                $ratio = @$results->categories[$catid]->count_matched / $results->categories[$catid]->count_answered;
                $results->categories[$catid]->hitratio = round($ratio * 100);
            } else {
                $results->categories[$catid]->hitratio = 0;
            }
            if (@$results->categories[$catid]->count_proposed) {
                $ratio = @$results->categories[$catid]->count_matched / $results->categories[$catid]->count_proposed;
                $results->categories[$catid]->ratio = round($ratio * 100);
            } else {
                $results->categories[$catid]->ratio = 0;
            }
            if ($catid != $examcontext->rootcategory) {
                $cat = get_record('question_categories', 'id', $catid);
                if ($cat->parent == $examcontext->rootcategory) {
                    if (@$cats->subs[$catid]->count > 0) {
                        $ratio = (0 + @$results->categories[$catid]->count_matched) / $cats->subs[$catid]->count;
                        $results->categories[$catid]->mastering = $ratio * 40;
                        $results->masteringdata[$catid] = min(100, $results->categories[$catid]->mastering);
                        $results->masteringheaders[$catid] = shorten_text($cat->name, 15);
                    } else {
                        $results->categories[$catid]->mastering = 0;
                        $results->masteringdata[$catid] = 0;
                        $results->masteringheaders[$catid] = shorten_text($cat->name, 15);
                    }
                }
            }
        }
    }
    if (!empty($results->attempts)) {
        foreach (array_keys($results->attempts) as $attemptid) {
            if (@$results->attempts[$attemptid]->count_answered_A) {
                $ratio = @$results->attempts[$attemptid]->count_matched_A / $results->attempts[$attemptid]->count_answered_A;
                $results->attempts[$attemptid]->hitratio_A = round($ratio * 100);
            } else {
                $results->attempts[$attemptid]->hitratio_A = 0;
            }
            if (@$results->attempts[$attemptid]->count_proposed_A) {
                $ratio = @$results->attempts[$attemptid]->count_matched_A / $results->attempts[$attemptid]->count_answered_A;
                $results->attempts[$attemptid]->ratio_A = round($ratio * 100);
            } else {
                $results->attempts[$attemptid]->ratio_A = 0;
            }
            if (@$results->attempts[$attemptid]->count_answered_C) {
                $ratio = @$results->attempts[$attemptid]->count_matched_C / $results->attempts[$attemptid]->count_answered_C;
                $results->attempts[$attemptid]->hitratio_C = round($ratio * 100);
            } else {
                $results->attempts[$attemptid]->hitratio_C = 0;
            }
            if (@$results->attempts[$attemptid]->count_proposed_C) {
                $ratio = @$results->attempts[$attemptid]->count_matched_C / $results->attempts[$attemptid]->count_proposed_C;
                $results->attempts[$attemptid]->ratio_C = round($ratio * 100);
            } else {
                $results->attempts[$attemptid]->ratio_C = 0;
            }
            if (@$results->attempts[$attemptid]->count_answered) {
                $ratio = @$results->attempts[$attemptid]->count_matched / $results->attempts[$attemptid]->count_answered;
                $results->attempts[$attemptid]->hitratio = round($ratio * 100);
            } else {
                $results->attempts[$attemptid]->hitratio = 0;
            }
            if (@$results->attempts[$attemptid]->count_proposed) {
                $ratio = @$results->attempts[$attemptid]->count_matched / $results->attempts[$attemptid]->count_proposed;
                $results->attempts[$attemptid]->ratio = round($ratio * 100);
            } else {
                $results->attempts[$attemptid]->ratio = 0;
            }
        }
    }

    if (isset($results->categories[$examcontext->rootcategory])) {
        $results->items = @$results->categories[$examcontext->rootcategory]->count_proposed_C + @$results->categories[$examcontext->rootcategory]->count_proposed_A;
        $results->done = @$results->categories[$examcontext->rootcategory]->count_matched_C + @$results->categories[$examcontext->rootcategory]->count_matched_A;

        if ($cats->count > 0) {
            $ratio = (0 + @$results->categories[$examcontext->rootcategory]->count_matched) / $cats->count;
            $results->categories[$examcontext->rootcategory]->mastering = $ratio * 40;
        } else {
            $results->categories[$examcontext->rootcategory]->mastering = 0;
        }
        if ($cats->count_a > 0) {
            $ratio = (0 + @$results->categories[$examcontext->rootcategory]->count_matched_A) / $cats->count_a;
            $results->categories[$examcontext->rootcategory]->mastering_A = $ratio * 40;
        } else {
            $results->categories[$examcontext->rootcategory]->mastering_A = 0;
        }
        if ($cats->count_c > 0) {
            $ratio = (0 + @$results->categories[$examcontext->rootcategory]->count_matched_C) / $cats->count_c;
            $results->categories[$examcontext->rootcategory]->mastering_C = $ratio * 40;
        } else {
            $results->categories[$examcontext->rootcategory]->mastering_C = 0;
        }
    }
    return $results;
}

/**
 *
 *
 */
function examtraining_compute_global_results($userid, $from, $to) {
    global $USER;
    global $CFG;
    global $questions;

    // Init structure.

    $examcontext = examtraining_get_context();

    // We get all states.
    $quizzeslist = implode("','", $examcontext->testquizzes);
    $examquizzeslist = str_replace(',', "','", $examcontext->examquizzes);

    $results = new StdClass;
    if (!isset($questions)) {
        $questions = $DB->get_records('question', array(), 'id,defaultgrade,category');
    }

    $examselect = "
        userid = ? AND
        timefinish > ? AND
        timefinish < ? AND
        quiz IN ('$examquizzeslist')
    ";
    if ($exams = $DB->get_records_select('quiz_attempts', $examselect, array($userid, $from, $to))) {
        $results->exams = count($exams);
    } else {
        $results->exams = 0;
    }

    $select = "
        userid = ? AND
        timefinish > ? AND
        timefinish < ? AND
        userquiz IN ('$quizzeslist')
    ";
    $distinctquestions = array();
    if ($attempts = $DB->get_records_select('quiz_attempts', $select, array($userid, $from, $to))) {
        $results->attempts = count($attempts);
        foreach ($attempts as $attempt) {
            if ($statesrs = get_all_user_records($attempt->id, $userid, null, true)) {
                if ($statesrs->valid()) {
                    foreach ($staters as $state) {
                        // Compute answers in states against question answers determining question type.
                        $question = &$questions[$state->question];

                        if (!$question) {
                            continue;
                        }
                        $cattype = ($question->defaultmark == 1) ? 'A' : 'C';
                        $ht = "hastype_$cattype";
                        $ca = "count_answered_$cattype";
                        $cp = "count_proposed_$cattype";
                        $cm = "count_matched_$cattype";
                        $cat = "count_answered";
                        $cpt = "count_proposed";
                        $cmt = "count_matched";

                        // Aggregate on globalizers.
                        if ($state->grade > 0) {
                            @$results->{$cm}++;
                            @$results->{$cmt}++;
                        }
                        if (strstr($state->answer, ':') !== false) {
                            @$results->{$ca}++;
                            @$results->{$cat}++;
                        }
                        @$results->{$cp}++;
                        @$results->{$cpt}++;
                    }
                }
                $$statesrs->close();
            }
        }
    }

    // Post compute ratios.
    if (!empty($results->count_proposed)) {
        $results->ratio = round( (0 + @$results->count_matched) / $results->count_proposed * 100);
    } else {
        $results->ratio = 0;
    }
    if (!empty($results->count_proposed_A)) {
        $results->ratio_A = round((0 + @$results->count_matched_A) / $results->count_proposed_A * 100);
    } else {
        $results->ratio_A = 0;
    }
    if (!empty($results->count_proposed_C)) {
        $results->ratio_C = round((0 + @$results->count_matched_C) / $results->count_proposed_C * 100);
    } else {
        $results->ratio_C = 0;
    }
    $results->items = @$results->count_proposed;
    $results->done = @$results->count_matched;

    $questionids = array();
    examtraining_reports_get_questions_rec($examcontext->rootcategory, $questionids);
    $questioncount = count($questionids);
    if ($questioncount) {
        $results->knowledge_covering_ratio = round(count($distinctquestions) / $questioncount * 100);
    }

    return $results;
}

function raw_format_duration($secs) {
    $min = floor($secs / 60);
    $hours = floor($min / 60);
    $days = floor($hours / 24);

    $hours = $hours - $days * 24;
    $min = $min - ($days * 24 * 60 + $hours * 60);
    $secs = $secs - ($days * 24 * 60 * 60 + $hours * 60 * 60 + $min * 60);

    if ($days) {
        return $days.' '.get_string('days')." $hours ".get_string('hours')." $min ".get_string('min')." $secs ".get_string('secs');
    }
    if ($hours) {
        return $hours.' '.get_string('hours')." $min ".get_string('min')." $secs ".get_string('secs');
    }
    if ($min) {
        return $min.' '.get_string('min')." $secs ".get_string('secs');
    }
    return $secs.' '.get_string('secs');
}

function examtraining_reports_print_questiondetail_xls(&$xlsdoc, $startrow, $effg, $xlsformats) {
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

function examtraining_reports_print_catscores_xls(&$xlsdoc, $startrow, $scores, $xlsformats) {

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

function examtraining_reports_print_overralcatscores_xls($worksheet, $startrow, $scores, $xlsformats) {
}

function examtraining_reports_input($course) {
    $input = new StdClass();

    $input->startday = optional_param('startday', -1, PARAM_INT); // From (-1 is from course start).
    $input->startmonth = optional_param('startmonth', -1, PARAM_INT); // From (-1 is from course start).
    $input->startyear = optional_param('startyear', -1, PARAM_INT); // From (-1 is from course start).
    $input->endday = optional_param('endday', -1, PARAM_INT); // To (-1 is till now).
    $input->endmonth = optional_param('endmonth', -1, PARAM_INT); // To (-1 is till now).
    $input->endyear = optional_param('endyear', -1, PARAM_INT); // To (-1 is till now).
    $input->fromstart = optional_param('fromstart', 0, PARAM_INT); // Force reset to course startdate.
    $input->from = optional_param('from', -1, PARAM_INT); // Alternate way of saying from when for XML generation.
    $input->to = optional_param('to', -1, PARAM_INT); // Alternate way of saying from when for XML generation.
    $input->offset = optional_param('offset', 0, PARAM_INT);

    // Calculate effective start time.

    if ($input->from == -1) {
        // Maybe we get it from parameters.
        if ($input->startday == -1 || $input->fromstart) {
            $input->from = $course->startdate;
        } else {
            if ($input->startmonth != -1 && $input->startyear != -1) {
                $input->from = mktime(0, 0, 8, $input->startmonth, $input->startday, $input->startyear);
            } else {
                print_error('Bad start date');
            }
        }
    }

    if ($input->to == -1) {
        // Maybe we get it from parameters.
        if ($input->endday == -1) {
            $input->to = time();
        } else {
            if ($input->endmonth != -1 && $input->endyear != -1) {
                $input->to = mktime(0, 0, 8, $input->endmonth, $input->endday, $input->endyear);
            } else {
                print_error('Bad end date');
            }
        }
    }

    return $input;
}