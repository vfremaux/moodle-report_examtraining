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
            $rec = new Stdclass();
            $rec->count = $cats->count;
            $rec->count_a = $cats->count_a;
            $rec->count_c = $cats->count_c;
            $rec->subs = $cats->subs;
            $countcache[$level] = $rec;
        }
    } else {
        $cats->count = $countcache[$level]->count;
        $cats->count_a = $countcache[$level]->count_a;
        $cats->count_c = $countcache[$level]->count_c;
        $cats->subs = &$countcache[$level]->subs;
    }
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
 * There should be only one per course.
 * @params int $courseid the course.
 * @param bool $passthru blocking call if true and block does not exist.
 * @return the userquiz monitor config in the course.
 */
function examtraining_get_context($courseid = 0, $passthru = false) {
    global $COURSE, $DB;

    if (!$courseid) {
        $courseid = $COURSE->id;
    }

    $coursecontext = context_course::instance($courseid);
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
function examtraining_get_module_count($userid, $from, $to) {
    global $DB;

    $examcontext = examtraining_get_context();
    list($insql, $inparams) = $DB->get_in_or_equal($examcontext->trainingquizzes);

    $params = [$userid];
    foreach ($inparams as $p) {
        $params[] = $p;
    }

    $fromclause = '';
    if (!empty($from)) {
        $fromclause = ' AND qa.timefinish > ? ';
        $params[] = $from;
    }

    $toclause = '';
    if (empty($to)) {
        $toclause = ' AND qa.timefinish < ? ';
        $params[] = $to;
    }

    // Compute attempts "per module size".

    $sql = "
        SELECT
            qcount,
            COUNT(qa.id) as acount
        FROM
            {quiz_attempts} qa
        LEFT JOIN
            {report_examtraining} ua
        ON
            qa.uniqueid = ua.uniqueid
        WHERE
            userid = ? AND
            quiz {$insql}
            {$fromclause}
            {$toclause}
        GROUP BY
            qcount
        ORDER BY
            qcount
    ";

    return $DB->get_records_sql_menu($sql, $params);
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
    $datacache = \report_examtraining\datacache::instance();
    $datacache->set_questionfields('id, defaultmark, category');
    $datacache->set_categoryfields('id, parent');

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
            if ($statesrs = block_userquiz_monitor_get_all_user_records($attempt->uniqueid, $userid, null, true)) {
                if ($statesrs->valid()) {
                    foreach ($statesrs as $state) {

                        // Compute answers in states against question answers determining question type.
                        if (!$question = $datacache->get_question($state->question)) {
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
                        $currentcat = $datacache->get_category($question->category);

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
                            $currentcat = $datacache->get_category($currentcat->parent);
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
        $itemsc = 0 + @$results->categories[$examcontext->rootcategory]->count_proposed_C;
        $itemsa = 0 + @$results->categories[$examcontext->rootcategory]->count_proposed_A;
        $results->items = $itemsa + $itemsc;
        $donec = 0 + @$results->categories[$examcontext->rootcategory]->count_matched_C;
        $donea = 0 + @$results->categories[$examcontext->rootcategory]->count_matched_A;
        $results->done = $donec + $donea;

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
    global $USER, $CFG;
    global $QUESTIONSCACHE;

    // Init structure.

    $examcontext = examtraining_get_context();

    // We get all states.
    $quizzeslist = implode("','", $examcontext->testquizzes);
    $examquizzeslist = str_replace(',', "','", $examcontext->examquizzes);

    $results = new StdClass;
    if (!isset($QUESTIONSCACHE)) {
        $QUESTIONSCACHE = [];
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
