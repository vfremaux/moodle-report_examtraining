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

require_once($CFG->libdir.'/questionlib.php');
require_once($CFG->dirroot.'/report/examtraining/statscompilelib.php');
require_once($CFG->dirroot.'/report/examtraining/excelformats.php');

/**
 * Returns proper info to query log
 @return a $readerinfo object describing log and log fields
 */
function examtraining_get_log_reader_info() {

    $logmanager = get_log_manager();
    $readers = $logmanager->get_readers('\core\log\sql_reader');
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

/**
 * Status : check redundancy with other implementations.
 * @see block_userquiz_monitor/xlib.php §block_userquiz_mponitor_get_top_cats()
 * Counts all questions by type in a cat and its subtree.
 * Optimized and memory cached function.
 * @param int $rootcatid the top root category.
 * @param array $cats category records with question count ans subs.
s */
function examtraining_count_questions_in_cats_rec($rootcatid, &$cats) {
    global $DB;

    static $level = 0;
    static $countcache = array();

    $cats = new SdClass;

    if (!isset($countcache[$level])) {

        $sql = "
            SELECT
                COUNT(*)
            FROM
                {question} q,
                {question_bank_entries} qbe,
                {question_versions} qv
            WHERE
                qbe.id = q.id AND
                q.parent = 0 AND
                qv.quesitonid = q.id AND
                qv.questionbankentryid = qbe.id AND
                qbe.questioncategoryid = ? AND
        ";

        // Count real questions.
        $cats->qcount = $DB->count_records_sql($sql, [$rootcatid]);
        $cats->acount = $DB->count_records_sql($sql. ' AND defaultmark = 1 ', [$rootcatid]);
        $cats->ccount = $DB->count_records_sql($sql. ' AND defaultmark = 1000 ', [$rootcatid]);

        $childs = $DB->get_records('question_categories', ['parent' => $rootcatid], 'id,id');

        if ($childs) {
            $cats->subs = array();
            foreach ($childs as $subcat) {
                if ($subcat->id == $rootcatid) {
                    continue;
                }
                $subs = new StdClass;
                $level++;
                examtraining_count_questions_in_cats_rec($subcat->id, $subs);
                $level--;
                $cats->qcount += $subs->qcount;
                $cats->acount += $subs->acount;
                $cats->ccount += $subs->ccount;
                $cats->subs[$subcat->id] = $subs;
            }
            $rec = new Stdclass();
            $rec->qcount = $cats->qcount;
            $rec->acount = $cats->acount;
            $rec->ccount = $cats->ccount;
            $rec->subs = $cats->subs;
            $countcache[$level] = $rec;
        }
    } else {
        $cats->qcount = $countcache[$level]->qcount;
        $cats->acount = $countcache[$level]->acount;
        $cats->ccount = $countcache[$level]->ccount;
        $cats->subs = &$countcache[$level]->subs;
    }
}

/**
 * special time formating
 * @param int $timevalue a unix timestamp
 * @param stirng $mode the formatting mode. (html or anything else)
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
 * recursively get all question id list in all subtree
 * @param int $rootcatid
 * @param arrayref &$questionids an array that collects question ids.
 */
function examtraining_reports_get_questions_rec($rootcatid, &$questionids) {
    global $DB;
    static $level = 0;

    $sql = "
        SELECT
            q.*,
            qbe.questioncategoryid as category
        FROM
            {question} q,
            {question_bank_entries} qbe
        WHERE
            cbe.questioncategoryid = ? AND
            q.parent = 0
    ";
    $select = " category = ? AND parent = 0 ";
    if ($questions = $DB->get_records_sql($sql, [$rootcatid], 'id', 'id,name,category')) {
        foreach ($questions as $q) {
            if (!in_array($q->id, $questionids)) {
                $questionids[] = $q->id;
            }
        }
    }

    if ($subcats = $DB->get_records('question_categories', array('parent' => $rootcatid), 'sortorder,id', 'id, name')) {
        foreach ($subcats as $c) {
            $level++;
            examtraining_reports_get_questions_rec($c->id, $questionids);
            $level--;
        }
    }
}


/**
 * returns the list of the names of the groups of the user.
 * @param int $courseid the courseid
 * @param int $userid the userid
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

/**
 * formats a duration for rax output
 * @params int secs duration in seconds
 * @return printable duration expression.
 */
function examtraining_raw_format_duration($secs) {
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

/**
 * Processes front end url params values and assemble them for report driving
 * User Id IS NOT Processed here.
 * @param object $course
 */
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
                $input->from = mktime(0, 0, 1, $input->startmonth, $input->startday, $input->startyear);
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
                $input->to = mktime(23, 59, 59, $input->endmonth, $input->endday, $input->endyear);
            } else {
                print_error('Bad end date');
            }
        }
    }

    return $input;
}

/**
 * Allocates a color from an HTML color string into a php image raster
 * @param object ref &$im a php image (imagecreate or imagecreatetruecolor)
 * @param string $color an HTML color (#RRGGBB)
 * @return an index on created color.
 */
function examtraining_allocate_html_color(&$im, $color) {

    if ( preg_match( "/[#]?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i", $color, $matches)) {
        $red = hexdec( $matches[1] );
        $green = hexdec( $matches[2] );
        $blue = hexdec( $matches[3] );
    }
    return ImageColorAllocate($im, $red, $green, $blue);
}
