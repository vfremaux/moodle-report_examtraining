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
 * @package     report_examtraining
 * @category    report
 * @copyright   2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/blocks/userquiz_monitor/block_userquiz_monitor_lib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

/**
 * precompile all uncompiled results in userquiz attemps records
 *
 */
function userquiz_precompile_results($id = 0, $work = 'userquiz_precompile_results_worker', $nocats = true,
                                     $range = true, $fromid = '') {
    global $CFG, $DB;

    $rangeclause = ($range) ? ' ua.datecompiled = 0 AND ' : '';

    $fromidclause = ($fromid) ? " ua.id >= $fromid AND " : '';

    $sql = "
        SELECT
            COUNT(*)
        FROM
            {quiz_attempts} qa,
            {report_examtraining} ua
        WHERE
            ua.uniqueid = qa.uniqueid AND
            $fromidclause
            $rangeclause
            qa.timefinish != 0
    ";

    $attemptscount = $DB->count_records_sql($sql, array());

    $limit = optional_param('limit', 0, PARAM_INT);
    $auto = optional_param('auto', 0, PARAM_INT);
    $maxruns = optional_param('maxruns', 0, PARAM_INT);
    $running = optional_param('running', 0, PARAM_INT);
    $output = optional_param('output', 0, PARAM_INT);

    if (!$running) {
        $out = "reseting runs config ";
        if ($output) {
            echo $out.'<br/>';
        } else {
            debug_trace($out);
        }
        set_config('runs', 0);
    }

    if ($limit) {
        $out = "processing $limit out of $attemptscount records ({$CFG->runs} out of runlimit = $maxruns)\n$rangeclause";
        if ($output) {
            mtrace($out.'<br/>');
        } else {
            debug_trace($out);
        }
        $start = ($range) ? 0 : $CFG->runs * $limit;
        $sql = "
            SELECT
                *
            FROM
                {quiz_attempts} qa,
                {report_examtraining} ua
            WHERE
                ua.uniqueid = qa.uniqueid,
                $fromidclause
                $rangeclause
                qa.timefinish != 0
        ";
        $attempts = $DB->get_records_sql($sql, array(), 'id', '*', $start, $limit);
    } else {
        $out = "processing $attemptscount records";
        if ($output) {
            mtrace($out.'<br/>');
        } else {
            debug_trace($out);
        }
        $sql = "
            SELECT
                *
            FROM
                {quiz_attempts} qa,
                {report_examtraining} ua,
                {qa_chooseconstraints_attempt} qca
            WHERE
                ua.uniqueid = qa.uniqueid AND
                qca.attemptid = qa.uniqueid AND
                qa.quiz = qca.quizid AND
                $fromidclause
                $rangeclause
                qa.timefinish != 0
        ";
        $attempts = $DB->get_records_sql($sql, array());
    }

    // Only process finished attempts.
    if (!empty($attempts)) {

        // Set default value.
        $rootcats = array();
        $rootcats[0] = array();

        $j = 0;

        foreach ($attempts as $attempt) {

            // Get rootcat heuristic for attempt. We try getting this cat from the first cat in the list.

            $rootcategory = 0;
            if ($attempt->categories) {
                $catarr = explode(',', $attempt->categories);
                $parent = $DB->get_field('question_categories', 'parent', 'id', $catarr[0]);
                if (!$parent) {
                    $rootcategory = $catarr[0];
                } else {
                    while ($parent != 0) {
                        $rootcategory = $parent;
                        $parent = get_field('question_categories', 'parent', 'id', $parent);
                    }
                }
            }

            // Only mark compilation time if complete complation is done.
            if (!$nocats) {
                $attempt->datecompiled = time();
                $DB->update_record('quiz_attempts', $attempt);
            }

            if ($rootcategory && !isset($rootcats[$rootcategory])) {
                // Get rootcats.
                $params = array('parent' => $rootcategory);
                if (!$cats = $DB->get_records('question_categories', $params, 'sortorder, id', 'id,name')) {
                    // This may be a bad case.... lost cat or something similar.
                    continue;
                }
                $rootcats[$rootcategory] = $cats;
            }

            // Block id (userquiz_monitor) is deduced from attempt's course heuristic.
            $attemptcourse = $DB->get_field('quiz', 'course', array('id' => $attempt->quiz));
            $params = array('pageid' => $attemptcourse, 'blockname' => 'userquiz_monitor');
            $blockinstance = $DB->get_record('block_instances', $params);
            $theblock = block_instance('userquiz_monitor', $blockinstance);

            $attempt->serieaanswered = 0;
            $attempt->seriecanswered = 0;
            $attempt->serieamatched = 0;
            $attempt->seriecmatched = 0;

            $work($attempt, $rootcats[$rootcategory], $theblock, !$auto, $nocats);
            $j++;
        }

        $out = " compiled $j attempts ";
        if ($output) {
            echo $out.'<br/>';
        } else {
            if (function_exists('debug_trace')) {
                debug_trace($out);
            }
        }

        if (!empty($CFG->backgroundrunsenabled) && $limit && $auto) {
            sleep($auto);
            if (!$maxruns || @$CFG->runs < $maxruns) {
                if ($maxruns) {
                    set_config('runs', 1 + @$CFG->runs);
                }
                if ($output) {
                    redirect($url);
                } else {
                    header("Location: $url");
                    die;
                }
            } else {
                set_config('runs', 0);
            }
        }
        return true;
    }
    return false;
}

/**
 * precompile all uncompiled results in userquiz attemps records
 */
function userquiz_precompile_some_results($id = 0, $ids, $work = 'userquiz_precompile_results_worker') {
    global $DB;

    list($insql, $inparams) = $DB->in_or_equal('id', $ids);

    $sql = "
        SELECT
            *
        FROM
            {quiz_attempts} qa,
            {report_examtraining} ua,
            {qa_chooseconstraints_attempt} qca
        WHERE
            qa.uniqueid = ua.uniqueid AND
            qca.attemptid = qa.uniqueid AND
            qca.quizid = qa.quiz
            ua.id $insql
    ";

    $attempts = $DB->get_records_sql('quiz_attempts', $inparams, 'id', $ids);

    $output = optional_param('output', 0, PARAM_INT);

    // Process required attempts.
    if (!empty($attempts)) {

        // Set default value.
        $rootcats = array();
        $rootcats[0] = array();

        $j = 0;

        foreach ($attempts as $attempt) {

            $attempt->serieaanswered = 0;
            $attempt->seriecanswered = 0;
            $attempt->serieamatched = 0;
            $attempt->seriecmatched = 0;
            $attempt->qcount = 0;

            // Get rootcat heuristic for attempt. We try getting this cat from the first cat in the list.
            $rootcategory = 0;
            if ($attempt->categories) {
                $catarr = explode(',', $attempt->categories);
                $parent = $DB->get_field('question_categories', 'parent', array('id' => $catarr[0]));
                if (!$parent) {
                    $rootcategory = $catarr[0];
                } else {
                    while ($parent != 0) {
                        $rootcategory = $parent;
                        $parent = $DB->get_field('question_categories', 'parent', array('id' => $parent));
                    }
                }
            }

            $DB->update_field('userquiz_attempts', 'datecompiled', time(), array('uniqueid' => $attempt->uniqeid));

            if ($rootcategory && !isset($rootcats[$rootcategory])) {
                // Get rootcats.
                $params = array('parent' => $rootcategory);
                if (!$cats = $DB->get_records('question_categories', $params, 'sortorder, id', 'id,name')) {
                    // This may be a bad case.... lost cat or something similar.
                    continue;
                }
                $rootcats[$rootcategory] = $cats;
            }

            // Block id (userquiz_monitor) is deduced from attempt's course heuristic.
            $theblock = userquiz_bloc_from_attempt($attempt);

            $work($attempt, $rootcats[$rootcategory], $theblock, true);
            $j++;
        }
        $out = "complied $j records";
        if ($output) {
            echo $out.'<br/>';
        } else {
            if (function_exists('debug_trace')) {
                debug_trace($out);
            }
        }
        return true;
    }
    return false;
}

/**
 * precompile all uncompiled results by cron in userquiz attempts records
 *
 */
function userquiz_cron_results() {
    global $DB;

    $sql = "
        SELECT
            *
        FROM
            {quiz_attempt} qa
            {report_examtraining} ua
        WHERE
            ua.uniqueid = qa.uniqueid AND
            ua.datecompiled = 0 AND
            qa.timefinish != 0
    ";

    $attemptscount = $DB->count_records_sql($sql);

    $output = optional_param('output', 0, PARAM_INT);

    $out = "processing $attemptscount records";
    if ($output) {
        mtrace($out.'<br/>');
    } else {
        if (function_exists('debug_trace')) {
            debug_trace($out);
        }
    }

    // Force some output.

    $sql = "
        SELECT
            *
        FROM
            {quiz_attempts} qa,
            {report_examtraining} ua
        WHERE
            qa.uniqueid = ua.uniqueid AND
            ua.datecompiled = 0 AND
            qa.timefinish != 0
    ";
    $attempts = $DB->get_records_sql($sql, null, '', '*');

    // Only process finished attempts.
    if (!empty($attempts)) {

        // Set default value.
        $rootcats = array();
        $rootcats[0] = array();

        $j = 0;

        foreach ($attempts as $attempt) {

            $out = " compiling attempt $attempt->id ";
            if ($output) {
                echo $out.'<br/>';
            } else {
                if (function_exists('debug_trace')) {
                    debug_trace($out);
                }
            }
            // Get rootcat heuristic for attempt. We try getting this cat from the first cat in the list.
            $rootcategory = 0;
            if ($attempt->categories) {
                $catarr = explode(',', $attempt->categories);
                $parent = $DB->get_field('question_categories', 'parent', array('id' => $catarr[0]));
                if (!$parent) {
                    $rootcategory = $catarr[0];
                } else {
                    while ($parent != 0) {
                        $rootcategory = $parent;
                        $parent = $DB->get_field('question_categories', 'parent', array('id' => $parent));
                    }
                }
            }

            $DB->update_field('report_examtraining', 'datecompiled', time(), array('uniqueid' => $attempt->uniqueid));

            if ($rootcategory && !isset($rootcats[$rootcategory])) {
                // Get rootcats.
                if (!$cats = $DB->get_records('question_categories', array('parent' => $rootcategory), 'sortorder, id', 'id,name')) {
                    // This may be a bad case.... lost cat or something similar.
                    continue;
                }
                $rootcats[$rootcategory] = $cats;
            }

            // Block id (userquiz_monitor) is deduced from attempt's course heuristic.
            $theblock = userquiz_block_from_attempt($attempt);

            // Reset attempt stats.
            $attempt->serieaanswered = 0;
            $attempt->seriecanswered = 0;
            $attempt->serieamatched = 0;
            $attempt->seriecmatched = 0;
            $attempt->qcount = 0;

            userquiz_precompile_results_worker($attempt, $rootcats[$rootcategory], $theblock, false);
            userquiz_precompile_userstats_worker($attempt, $rootcats[$rootcategory], $theblock, false);

            $j++;
        }

        $out = " compiled $j attempts";
        if ($output) {
            mtrace($out.'<br/>');
        } else {
            if (function_exists('debug_trace')) {
                debug_trace($out);
            }
        }

        userquiz_precompile_coverage_ratios(); // Recompile all ratios (for all blocks).
        return $attemptscount;
    }
    return false;
}

function userquiz_bloc_from_attempt($quizattempt) {
    global $DB;

    $attemptcourse = $DB->get_field('quiz', 'course', array('id' => $attempt->quiz));
    $coursecontext = context_course::instance($attemptcourse->id);
    $blockinstance = $DB->get_record('block_instances', array('parentcontextid' => $coursecontext->id, 'blockname', 'userquiz_monitor'));
    $theblock = block_instance('userquiz_monitor', $blockinstance);

    return $theblock;
}

/**
 *
 *
 */
function userquiz_precompile_results_worker(&$attempt, &$rootcats, $block, $verbose, $nocats = false) {
    global $DB;

    $output = optional_param('output', 0, PARAM_INT);

    if (empty($attempt->layout)) {
        return false;
    }

    // Get real instances and discard all page jumps.
    $questioninstances = explode(',', $attempt->layout);
    foreach ($questioninstances as $instance) {
        if ($instance == 0) {
            continue;
        }
        $realinstances[] = $instance;
    }
    $realinstancelist = implode(',', $realinstances);

    // Get real questions, analyse answers and make A and C counts (serie 1 / serie 2).
    if (!$questions = $DB->get_records_list('question', 'id', $realinstancelist)) {
        $out = "($attempt->id:$realinstancelist)";
        if ($verbose && $output) {
             echo $out;
        } else {
            if (function_exists('debug_trace')) {
                debug_param($out);
            }
        }
    }
    $attempt->qcount = count($questions);

    // Initialize.
    $attempts = array();

    // Compile answered and matched questions.
    if ($allstates = get_all_user_records($attempt->uniqueid, $attempt->userid, null, true)) {

        if (!$allstates->valid()) {
            $i = 0;
            foreach ($allstates as $state) {
                if ($verbose && $output) {
                    echo ".";
                } else {
                    if (function_exists('debug_trace')) {
                        debug_trace(".");
                    }
                }
                $question = $DB->get_record_select('question', " id = $state->question ", array(), 'id, defaultgrade, category');
                $parent = $DB->get_field('question_categories', 'parent', array('id' => $question->category));

                if (!$parent) {
                    if ($verbose && $output) {
                        echo "f";
                    } else {
                        if (function_exists('debug_trace')) {
                            debug_trace("f ");
                        }
                    }
                    continue; // Fix lost states.
                }

                while (!in_array($parent, array_keys($rootcats)) && $parent != 0) {
                    $parent = $DB->get_field('question_categories', 'parent', array('id' => $parent));
                }

                $attempts[$parent]->qcount = @$attempts[$parent]->qcount + 1;
                if ($question->defaultmark == '1000') {
                    $attempts[$parent]->ccount = @$attempts[$parent]->ccount + 1;
                    $attempt->seriecanswered++;
                    if ($state->grade > 0) {
                        $attempts[$parent]->cmatched = @$attempts[$parent]->cmatched + 1;
                        $attempt->seriecmatched++;
                    }
                } else {
                    $attempts[$parent]->acount = @$attempts[$parent]->acount + 1;
                    $attempt->serieaanswered++;
                    if ($state->grade > 0) {
                        $attempts[$parent]->amatched = @$attempts[$parent]->amatched + 1;
                        $attempt->serieamatched++;
                    }
                }
                $i++;
            }
            $out = "Compiled $i states ";
            if ($verbose && $output) {
                echo $out;
            } else {
                if (function_exists('debug_trace')) {
                    debug_trace($out);
                }
            }
        }
        // Free some memory here ?
        unset($allstates);
    } else {
        if ($verbose && $output) {
            echo 0;
        }
    }

    // Save back compilation.
    $recattempt->id = $DB->get_field('report_examtraining', 'id', array('uniqueid' => $attempt->uniqueid));
    $recattempt->uniqueid = $attempt->uniqueid;
    $recattempt->qcount = 0 + @$attempt->qcount;
    $recattempt->serieaanswered = 0 + @$attempt->serieaanswered;
    $recattempt->seriecanswered = 0 + @$attempt->seriecanswered;
    $recattempt->serieamatched = 0 + @$attempt->serieamatched;
    $recattempt->seriecmatched = 0 + @$attempt->seriecmatched;
    $recattempt->datecompiled = time();

    if ($DB->update_record('report_examtraining', $recattempt)) {
        if ($verbose && $output) {
            echo 'u';
        } else {
            if (function_exists('debug_trace')) {
                debug_trace('u ');
            }
        }
    }

    if (!$nocats) {
        foreach ($attempts as $catid => $catattempt) {
            $catattempt = new StdClass;
            $catattempt->categoryid = $catid;
            $catattempt->userid = $attempt->userid;
            $catattempt->attemptid = $attempt->uniqueid;
            $catattempt->quizid = $attempt->quizid;
            $select = "
                categoryid = ? AND
                userid = ? AND
                attemptid = ? AND
                quiz = ?
            ";
            $params = array($catid, $attempt->userid, $attempt->uniqueid, $attempt->quiz);
            if ($exists = $DB->get_record_select('userquiz_monitor_cat_stats', $select, $params)) {
                if ($DB->update_record('userquiz_monitor_cat_stats', $catattempt)) {
                    if ($verbose && $output) {
                        echo 'u';
                    } else {
                        if (function_exists('debug_trace')) {
                            debug_trace('u ');
                        }
                    }
                }
            } else {
                if ($DB->insert_record('userquiz_monitor_cat_stats', $catattempt)) {
                    if ($verbose && $output) {
                        echo 'i';
                    } else {
                        if (function_exists('debug_trace')) {
                            debug_trace('i ');
                        }
                    }
                }
            }
        }
    }

    // Free as much memory as possible.
    unset($attempts);
}

/**
 *
 *
 */
function userquiz_precompile_userstats_worker(&$attempt, &$rootcats, &$block, $verbose) {
    global $DB;

    $output = optional_param('output', 0, PARAM_INT);

    if (empty($attempt->layout)) {
        return false;
    }

    // Get real instances and discard all page jumps.
    $questioninstances = explode(',', $attempt->layout);
    foreach ($questioninstances as $instance) {
        if ($instance == 0) {
            continue;
        }
        $realinstances[] = $instance;
    }
    $realinstancelist = implode(',', $realinstances);

    // Initialize.
    $attempts = array();

    // Compile answered and matched questions.
    if ($allstates = get_all_user_records($attempt->uniqueid, $attempt->userid, null, true)) {

        if ($allstates->valid()) {
            foreach ($allstates as $state) {

                if ($verbose) {
                    echo ".";
                }

                if (!$question = $DB->get_record('question', array('id' => $state->question), 'id, defaultmark, category')) {
                    continue;
                }

                // Question has matched, unconditionnaly (or partially), we can mark it for match stats.
                if ($state->grade > 0) {
                    $coverage[$question->id]->matched = 1;
                }
                $coverage[$question->id]->seen = 1;
            }
        }
    }

    // Get root category from block instance config.

    // Finally record results.

    // Records coverage map for all used questions.
    if (!empty($coverage)) {
        foreach ($coverage as $qid => $qc) {
            $newrec = false;
            $params = array('userid' => $attempt->userid, 'blockid' => $block->instance->id);
            if (!$rec = $DB->get_record('userquiz_monitor_coverage', $params, 'questionid', $qid)) {
                $rec = new StdClass;
                $rec->questionid = $qid;
                $rec->userid = $attempt->userid;
                $rec->blockid = $block->instance->id;
                $newrec = true;
            }
            $rec->usecount = 0 + @$rec->usecount + @$qc->seen;
            $rec->matchcount = 0 + @$rec->matchcount + @$qc->matched;
            if ($newrec) {
                if ($DB->insert_record('userquiz_monitor_coverage', $rec)) {
                    if ($verbose && $output) {
                        echo 'ci';
                    } else {
                        if (function_exists('debug_trace')) {
                            debug_trace('ci ');
                        }
                    }
                }
            } else {
                if ($DB->update_record('userquiz_monitor_coverage', $rec)) {
                    if ($verbose && $output) {
                        echo 'cu';
                    } else {
                        if (function_exists('debug_trace')) {
                            debug_trace('cu ');
                        }
                    }
                }
            }
        }
    }
    if ($verbose && $output) {
        echo '<br/>';
    } else {
        debug_trace("\n\n");
    }
}

/**
 * enter by searching blocks instances that are userquiz_monitors,
 * than fetch blockinstances for each and $testquizzes list in config
 * Fetch all distinct users in each block, than call a worker thread
 * to process those results in userquiz_monitor_coverage
 *
 */
function userquiz_precompile_coverage_ratios() {
    global $DB;

    $blocks = $DB->get_records('block_instances', array('blockname' => 'userquiz_monitor'));

    foreach ($blocks as $ablock) {
        $theblock = block_instance('userquiz_monitor', $ablock);

        list($insql, $params) = $DB->get_in_or_equal($theblock->config->trainingquizzes);

        $sql = "
            SELECT DISTINCT
                userid,
                quiz
            FROM
                {quiz_attempts}
            WHERE
                quiz $insql
        ";
        if ($users = $DB->get_records_sql($sql, $params)) {
            foreach ($users as $user) {
                userquiz_precompile_question_coverage_worker($user->userid, $theblock);
            }
        }
    }

    return false;
}

/**
 *
 *
 */
function userquiz_precompile_question_coverage_worker($userid, &$block) {
    global $DB;
    static $i = 0;

    $output = optional_record('output', 0, PARAM_INT);

    // Finally compute coverage rates on distinct questions.

    // Count all questions available.
    $allcats = new StdClass;
    count_questions_in_categories_rec($block->config->rootcategory, $allcats);
    $allquestions = $allcats->count;

    $params = array($block->instance->id, $userid);

    $sql = "
        SELECT COUNT(DISTINCT
            questionid)
        FROM
            {userquiz_monitor_coverage}
        WHERE
            blockid = ? AND
            userid = ? AND
            usecount > 0
    ";
    $seenquestions = $DB->get_field_sql($sql, $params);

    $sql = "
        SELECT COUNT(DISTINCT
            questionid)
        FROM
            {userquiz_monitor_coverage}
        WHERE
            blockid = ? AND
            userid = ? AND
            matchcount > 0
    ";
    $matchedquestions = $DB->get_field_sql($sql, $params);

    $newrec = false;
    $select = " userid = ? AND blockid = ? AND attemptid = 0 ";
    if (!$rec = $DB->get_record_select('userquiz_monitor_user_stats', $select, array($userid, $block->instance->id))) {
        $rec = new StdClass;
        $rec->userid = $userid;
        $rec->blockid = $block->instance->id;
        $rec->attemptid = 0; // Userquiz direct id.
        $newrec = true;
    }

    if ($output) {
        debug_trace("[$userid, $seenquestions, $matchedquestions, $allquestions]\n");
    }

    $rec->coverageseen = $seenquestions / $allquestions * 100;
    $rec->coveragematched = $matchedquestions / $allquestions * 100;
    if ($newrec) {
        $DB->insert_record('userquiz_monitor_user_stats', $rec);
        if ($output) {
            echo 'i';
        } else {
            if (function_exists('debug_trace')) {
                debug_trace('i ');
            }
        }
    } else {
        $DB->update_record('userquiz_monitor_user_stats', $rec);
        if ($output) {
            echo 'u';
        } else {
            if (function_exists('debug_trace')) {
                debug_trace('u ');
            }
        }
    }
    if ($i && ($i % 25 == 0)) {
        if ($verbose && $output) {
            echo '<br/>';
        } else {
            if (function_exists('debug_trace')) {
                debug_trace("\n");
            }
        }
    }
    $i++;
}

/**
 *
 */
function userquiz_get_weekly_globals($userid, $quizzeslist, $from, $to) {
    global $DB;

    if (is_array($quizzeslist)) {
        $quizzesidlist = implode("','", $quizzeslist);
    } else {
        $quizzesidlist = str_replace(',', "','", $quizzeslist);
    }
    $quizzesclause = (!empty($quizzesidlist)) ? " quiz IN ('$quizzesidlist') AND " : '';

    $fromclause = ($from) ? " qa.timefinish > $from AND " : '';
    $toclause = ($to) ? " qa.timefinish < $to AND " : '';

    $sql = "
        SELECT
            CONCAT(YEAR(FROM_UNIXTIME(timefinish)), '_', WEEK(FROM_UNIXTIME(timefinish))),
            YEAR(FROM_UNIXTIME(timefinish)) as year,
            WEEK(FROM_UNIXTIME(timefinish)) as week,
            u.firstname,
            u.lastname,
            SUM(qcount) as qcount,
            SUM(serieaanswered) as aanswered,
            SUM(seriecanswered) as canswered,
            SUM(serieamatched) as amatched,
            SUM(seriecmatched) as cmatched,
            SUM(serieaanswered) + SUM(seriecanswered) as answered,
            SUM(serieamatched) + SUM(seriecmatched) as matched
        FROM
            {quiz_attempts} qa,
            {report_examtraining} ua,
            {user} u
        WHERE
            qa.id = ua.uniqueid AND
            u.id = qa.userid AND
            userid = ? AND
            $quizzesclause
            $fromclause
            $toclause
            timefinish != 0
        GROUP BY
            YEAR(FROM_UNIXTIME(timefinish)),
            WEEK(FROM_UNIXTIME(timefinish))
        ORDER BY
            YEAR(FROM_UNIXTIME(timefinish)),
            WEEK(FROM_UNIXTIME(timefinish))
    ";

    if ($stats = $DB->get_records_sql($sql, array($userid))) {
        foreach ($stats as $stat) {
            $stat->hitratio = ($stat->answered != 0) ? sprintf("%0.2f", $stat->matched / $stat->answered) : 0;
            $stat->ahitratio = ($stat->aanswered != 0) ? sprintf("%0.2f", $stat->amatched / $stat->aanswered) : 0;
            $stat->chitratio = ($stat->canswered != 0) ? sprintf("%0.2f", $stat->cmatched / $stat->canswered) : 0;
        }
    }

    return $stats;
}

/**
 *
 */
function userquiz_get_user_globals($userid, $quizzeslist, $from, $to) {
    global $DB;

    if (is_array($quizzeslist)) {
        $quizzesidlist = implode("','", $quizzeslist);
    } else {
        $quizzesidlist = str_replace(',', ',', $quizzeslist);
    }
    $quizzesclause = (!empty($quizzesidlist)) ? " quiz IN ('$quizzesidlist') AND " : '';

    $fromclause = ($from) ? " qa.timefinish > $from AND " : '';
    $toclause = ($to) ? " qa.timefinish < $to AND " : '';

    if (is_array($userid)) {
        $userclause = " userid IN ('".implode("','", $userid)."') AND ";
    } else {
        $userclause = " userid = $userid AND ";
    }

    $sql = "
        SELECT
            qa.userid,
            SUM(qcount) as qcount,
            SUM(serieaanswered) as aanswered,
            SUM(seriecanswered) as canswered,
            SUM(serieamatched) as amatched,
            SUM(seriecmatched) as cmatched,
            SUM(serieaanswered) + SUM(seriecanswered) as answered,
            SUM(serieamatched) + SUM(seriecmatched) as matched,
            COUNT(*) as attempts
        FROM
            {quiz_attempts} qa
        LEFT JOIN
            {report_examtraining} ua
        ON
            ua.uniqueid = qa.uniqueid
        WHERE
            $userclause
            $quizzesclause
            $fromclause
            $toclause
            timefinish != 0
        GROUP BY
            qa.userid
    ";

    if ($stats = $DB->get_records_sql($sql)) {
        foreach ($stats as $stat) {
            $stat->hitratio = ($stat->answered != 0) ? floor($stat->matched / $stat->answered * 100) : 0;
            $stat->ahitratio = ($stat->aanswered != 0) ? floor($stat->amatched / $stat->aanswered * 100) : 0;
            $stat->chitratio = ($stat->canswered != 0) ? floor($stat->cmatched / $stat->canswered * 100) : 0;
        }
    }
    return $stats;
}

/**
 *
 *
 */
function userquiz_get_attempts_subcats($userid, $quizzeslist, $from, $to) {
    global $DB;

    $toclause = ($to != 0) ? " AND qa.timefinish <= $to " : '';
    $fromclause = ($from != 0) ? " AND qa.timefinish >= $from " : '';

    if (is_array($quizzeslist)) {
        $quizzesidlist = implode(',', $quizzeslist);
    } else {
        $quizzesidlist = str_replace(',', "','", $quizzeslist);
    }
    $quizzesclause = (!empty($quizzesidlist)) ? " cs.quizid IN ('$quizzesidlist') AND " : '';

    $sql = "
        SELECT
            cs.*,
            YEAR(FROM_UNIXTIME(qa.timefinish)) as year,
            WEEK(FROM_UNIXTIME(qa.timefinish)) as week
        FROM
            {userquiz_monitor_cat_stats} cs,
            {quiz_attempts} qa
        WHERE
            qa.uniqueid = cs.attemptid AND
            qa.userid = ? AND
            $quizzesclause
            qa.timefinish != 0
            $toclause
            $fromclause
        ORDER BY
            YEAR(FROM_UNIXTIME(timefinish)),
            WEEK(FROM_UNIXTIME(timefinish))
    ";

    $stats = $DB->get_records_sql($sql, array($userid));

    return $stats;
}

/**
 * aggegates all attempts for each category
 */
function userquiz_get_user_subcats($userid, $quizzeslist, $from = 0, $to = 0) {
    global $DB;

    $toclause = ($to != 0) ? " AND qa.timefinish <= $to " : '';
    $fromclause = ($from != 0) ? " AND qa.timefinish >= $from " : '';

    if (is_array($quizzeslist)) {
        $quizzesidlist = implode(',', $quizzeslist);
    } else {
        $quizzesidlist = str_replace(',', "','", $quizzeslist);
    }
    $quizzesclause = (!empty($quizzesidlist)) ? " cs.quizid IN ('$quizzesidlist') " : '';

    $sql = "
        SELECT
            categoryid,
            SUM(cs.qcount) as qcount,
            SUM(cs.acount) as acount,
            SUM(cs.ccount) as ccount,
            SUM(cs.amatched) as amatched,
            SUM(cs.cmatched) as cmatched
        FROM
            {userquiz_monitor_cat_stats} cs,
            {quiz_attempts} qa
        WHERE
            qa.uniqueid = cs.attemptid AND
            cs.userid = ? AND
            $quizzesclause
            $fromclause
            $toclause
        GROUP BY
            cs.categoryid
    ";

    $stats = $DB->get_records_sql($sql, array($userid));

    return $stats;
}

/**
 * get hit ratio per attempts
 *
 */
function userquiz_get_attempts_stats($userid, $quizzeslist, $from = 0, $to = 0) {
    global $DB;

    $toclause = ($to != 0) ? " AND qa.timefinish <= $to " : '';
    $fromclause = ($from != 0) ? " AND qa.timefinish >= $from " : '';

    if (is_array($quizzeslist)) {
        $quizzesidlist = implode(',', $quizzeslist);
    } else {
        $quizzesidlist = str_replace(',', "','", $quizzeslist);
    }
    $quizzesclause = (!empty($quizzesidlist)) ? " qa.quiz IN ('$quizzesidlist') AND " : '';

    $sql = "
        SELECT
            ua.uniqueid,
            qa.timefinish,
            u.firstname,
            u.lastname,
            u.id as userid,
            ua.qcount,
            ua.serieaanswered as aanswered,
            ua.seriecanswered as canswered,
            ua.serieamatched as amatched,
            ua.seriecmatched as cmatched,
            ua.serieaanswered + seriecanswered as answered,
            ua.serieamatched + seriecmatched as matched
        FROM
            {quiz_attempts} qa
        JOIN
            {user} u
        ON
            u.id = qa.userid
        LEFT JOIN
            {report_examtraining} ua
        ON
            ua.uniqueid = qa.uniqueid
        WHERE
            qa.userid = ? AND
            $quizzesclause
            qa.timefinish != 0
            $toclause
            $fromclause
        ORDER BY
            qa.timefinish
    ";

    if ($stats = $DB->get_records_sql($sql, array($userid))) {
        foreach ($stats as $stat) {
            $stat->hitratio = ($stat->answered != 0) ? sprintf("%0.2f", $stat->matched / $stat->answered) : 0;
            $stat->ahitratio = ($stat->aanswered != 0) ? sprintf("%0.2f", $stat->amatched / $stat->aanswered) : 0;
            $stat->chitratio = ($stat->canswered != 0) ? sprintf("%0.2f", $stat->cmatched / $stat->canswered) : 0;
        }
    }

    return $stats;
}