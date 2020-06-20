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

require_once($CFG->dirroot.'/report/examtraining/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/classes/datacache.class.php');
require_once($CFG->dirroot.'/blocks/userquiz_monitor/locallib.php');

/**
 * The generic function to get all attempt sets in all conditions
 */
function userquiz_get_attempts($courseid, $userid = 0, $set = 'training', $options = [], $offset = 0, $limit = 0) {
    global $DB;

    $uqconfig = examtraining_get_context($courseid);
    $params = [];

    if ($set == 'training') {
        $quizlist = $uqconfig->trainingquizzes;
    } else if ($set == 'exam') {
        if (!empty($uqconfig->examquiz)) {
            $quizlist = [$uqconfig->examquiz];
        }
    } else {
        $quizlist = $uqconfig->trainingquizzes;
        if (!empty($uqconfig->examquiz)) {
            $quizlist[] = $uqconfig->examquiz;
        }
    }

    if (!empty($quizlist)) {
        list ($insql, $params) = $DB->get_in_or_equal($quizlist);
        $quizclause = " qa.quiz $insql AND ";
    }

    // Course clause.
    $courseclause = '';
    if (!empty($courseid)) {
        $courseclause = ' q.course = ? AND ';
        $params[] = $courseid;
    }

    // User clause.
    $userclause = '';
    if (!empty($userid)) {
        $userclause = ' qa.userid = ? AND ';
        $params[] = $userid;
    }

    // If new records only, choose never compiled attempts.
    $rangeclause = '';
    if (!empty($options['new'])) {
        $rangeclause = ' ua.datecompiled = 0 AND ';
    }

    // If some records only, restrict to those ids.
    $idlistclause = '';
    if (!empty($options['ids'])) {
        list($insql, $inparams) = $DB->get_in_or_equal($options['ids']);
        $idlistclause = " ua.id $insql AND ";
        foreach ($inparams as $p) {
            $params[] = $p;
        }
    }

    // Give a start id.
    $fromidclause = '';
    if (!empty($options['fromid'])) {
        $fromidclause = " ua.id >= ? AND ";
        $params[] = $fromid;
    }

    // Resolve the records range
    if (!empty($options['limit'])) {
        // Start over the last reached runpoint.
        $limit = $options['limit'];
        $start = $SESSION->examtrainingruns * $limit;
    } else {
        $start = 0;
        $limit = 0;
    }

    $sql = "
        SELECT
            qa.*,
            qca.categories as categories,
            ua.id as uaid
        FROM
            {quiz_attempts} qa,
            {quiz} q,
            {report_examtraining} ua,
            {qa_chooseconstraints_attempt} qca
        WHERE
            ua.uniqueid = qa.uniqueid AND
            q.id = qa.quiz AND
            qca.attemptid = qa.id AND
            qa.quiz = qca.quizid AND
            {$quizclause}
            {$courseclause}
            {$userclause}
            {$idlistclause}
            {$fromidclause}
            {$rangeclause}
            qa.timefinish != 0
         ORDER BY
            qa.id
    ";
    $attempts = $DB->get_records_sql($sql, $params, $offset, $limit);

    return $attempts;
}

/**
 * precompile all uncompiled results in userquiz attemps records
 * @param int $courseid the course to compile for. If 0, will compile for all courses
 * @param string $work the worker function to trig for each result.
 * @param array $options contextual calculation options
 */
function userquiz_precompile_results($courseid = 0, $work = 'userquiz_precompile_results_worker', $options = []) {
    global $CFG, $DB, $SESSION;

    // Setup session
    if (!isset($SESSION->examtrainingruns)) {
        $SESSION->examtrainingruns = 0;
    }

    if (!isset($SESSION->examtrainingtime)) {
        if (!empty($options['auto'])) {
            $SESSION->examtrainingtime = time();
        }
    }

    $attempts = userquiz_get_attempts($courseid, 0, $options);

    if (empty($options['running'])) {
        // The interactive "force continue running" signal is gone. Unmark it. this compile step will be the last.
        $out = "reseting runs config ";
        if (!empty($options['output'])) {
            echo $out.'<br/>';
        }
        set_config('runs', 0, 'report_examtraining');
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
                $parent = $DB->get_field('question_categories', 'parent', ['id' => $catarr[0]]);
                if (!$parent) {
                    $rootcategory = $catarr[0];
                } else {
                    while ($parent != 0) {
                        $rootcategory = $parent;
                        $parent = $DB->get_field('question_categories', 'parent', ['id' => $parent]);
                    }
                }
            }

            // Only mark compilation time if complete compilation is done.
            if (!empty($options['nocats'])) {
                $reportattempt = new StdClass;
                $reportattempt->datecompiled = time();
                $reportattempt->id = $attempt->uaid;
                $DB->update_record('report_examtraining', $attempt);
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
            $attemptcourse = $DB->get_field('quiz', 'course', ['id' => $attempt->quiz]);
            $coursecontext = context_course::instance($attemptcourse);
            $params = array('parentcontextid' => $coursecontext->id, 'blockname' => 'userquiz_monitor');
            $blockinstance = $DB->get_record('block_instances', $params);
            $theblock = block_instance('userquiz_monitor', $blockinstance);

            $attempt->serieaanswered = 0;
            $attempt->seriecanswered = 0;
            $attempt->serieamatched = 0;
            $attempt->seriecmatched = 0;

            $work($attempt, $rootcats[$rootcategory], $theblock, !empty($options['auto']), @$options['nocats']);
            $j++;
        }

        mtrace(" Compiled $j attempts ");

        $attemptsleft = userquiz_get_attempts($courseid, 0, $options);
        $attemptsleft = count($attemptsleft);

        // If nothing left, reset everything and go out.
        // backgroundrunsenabled is a "strong force out by config option" in case we side is stucked.
        if ($attemptsleft == 0 || empty($CFG->backgroundrunsenabled)) {
            $SESSION->examtrainingruns = 0;
            $SESSION->examtrainingtime = 0;
            $options['running'] = 0;
            return false; // Nothing more to compile.
        }

        $needrespawn = false;

        // Check if we can respawn : We can respawn if we are in autorunnung and maxruns has not be reached.
        if (!empty($options['auto'])) {
            // We have no limitation engaged in runs or limitation is NOT reached..
            if (empty($options['maxruns']) || @$SESSION->examtrainingruns < $options['maxruns']) {
                $SESSION->examtrainingruns++;
                $needrespawn = true;
            }

            // Check the running time condition.
            if (!empty($SESSION->examtrainingtime)) {
                // We can respawn if we have not spent all processing time.
                if (time() - $SESSION->examtrainingtime < $options['auto']) {
                    $needrespawn = $needrespawn && true;
                }
            }

            if ($needsrespawn) {
                redirect($url);
            }
        }
        return true;
    }

    return false;
}

/**
 * precompile all uncompiled results in userquiz attemps records
 * @param $id
 * @param $ids
 */
function userquiz_precompile_some_results($courseid = 0, array $ids = [], $work = 'userquiz_precompile_results_worker', $options = []) {
    global $DB;

    $attempts = userquiz_get_attempts($courseid, 0, ['ids' => $ids]);

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
                $parent = $DB->get_field('question_categories', 'parent', ['id' => $catarr[0]]);
                if (!$parent) {
                    $rootcategory = $catarr[0];
                } else {
                    while ($parent != 0) {
                        $rootcategory = $parent;
                        $parent = $DB->get_field('question_categories', 'parent', ['id' => $parent]);
                    }
                }
            }

            $DB->update_field('report_examtraining', 'datecompiled', time(), ['uniqueid' => $attempt->uniqeid]);

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
            $theblock = userquiz_block_from_attempt($attempt);

            $work($attempt, $rootcats[$rootcategory], $theblock, true);
            $j++;
        }
        if ($options['output']) {
            mtrace("Compiled $j records.");
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

    // Get all attempts not yet compiled.
    $sql = "
        SELECT
            COUNT(*)
        FROM
            {quiz_attempts} qa,
            {report_examtraining} ua
        WHERE
            ua.uniqueid = qa.uniqueid AND
            ua.datecompiled = 0 AND
            qa.timefinish > 0 AND
            ua.qcount > 0
    ";
    $attemptscount = $DB->count_records_sql($sql);

    $output = optional_param('output', 0, PARAM_INT);
    $output = $output || defined('CLI_SCRIPT');

    $out = "Found to compile : $attemptscount records\n";
    if ($output) {
        mtrace($out);
    } else {
        if (function_exists('debug_trace')) {
            debug_trace($out);
        }
    }

    // Force some output.

    $sql = "
        SELECT
            qa.*,
            qca.categories as categories
        FROM
            {quiz_attempts} qa,
            {report_examtraining} ua,
            {qa_chooseconstraints_attempt} qca
        WHERE
            qa.uniqueid = ua.uniqueid AND
            qca.attemptid = qa.uniqueid AND
            ua.datecompiled = 0 AND
            qa.timefinish != 0
    ";
    $attempts = $DB->get_records_sql($sql, []);

    // Only process finished attempts.
    if (!empty($attempts)) {

        // Set default value.
        $rootcats = array();
        $rootcats[0] = array();

        $j = 0;

        foreach ($attempts as $attempt) {

            $out = "Compiling attempt $attempt->id\n";
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
                        $parent = $DB->get_field('question_categories', 'parent', ['id' => $parent]);
                    }
                }
            }

            $DB->set_field('report_examtraining', 'datecompiled', time(), ['uniqueid' => $attempt->uniqueid]);

            if ($rootcategory && !isset($rootcats[$rootcategory])) {
                // Get rootcats at first level.
                $params = array('parent' => $rootcategory);
                if (!$cats = $DB->get_records('question_categories', $params, 'sortorder, id', 'id,name')) {
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
    } else {
        $out = "No attempt data\n";
        if ($output) {
            mtrace($out.'<br/>');
        } else {
            if (function_exists('debug_trace')) {
                debug_trace($out);
            }
        }
    }
    return false;
}

function userquiz_block_from_attempt($quizattempt) {
    global $DB;

    $attemptcourseid = $DB->get_field('quiz', 'course', array('id' => $quizattempt->quiz));
    $coursecontext = context_course::instance($attemptcourseid);
    $params = array('parentcontextid' => $coursecontext->id, 'blockname' => 'userquiz_monitor');
    $blockinstance = $DB->get_record('block_instances', $params);
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
    $slots = explode(',', $attempt->layout);
    $realinstances = [];
    foreach ($slots as $s) {
        if ($s != 0) {
            $realinstances[] = $DB->get_field('quiz_slots', 'questionid', ['quizid' => $attempt->quiz, 'slot' => $s]);
        }
    }

    echo "ROOT CATS<br/>";
    debug_print_for_user('admin', $rootcats);

    // Get real questions, analyse answers and make A and C counts (serie 1 / serie 2).
    $realinstancelist = implode(',', $realinstances);
    if ($questions = $DB->get_records_list('question', 'id', $realinstances)) {
        $out = "($attempt->id:$realinstancelist)";
        if ($verbose || $output) {
             echo $out;
        } else {
            if (function_exists('debug_trace')) {
                debug_trace($out);
            }
        }
    }
    $attempt->qcount = count($questions);

    // Initialize question attempts counters.
    $qattempts = array();

    // Compile answered and matched questions; getting as recordset for performance.
    if ($allstates = block_userquiz_monitor_get_all_user_records($attempt->uniqueid, $attempt->userid, null, true)) {
        if ($allstates->valid()) {
            $i = 0;
            foreach ($allstates as $state) {
                if ($verbose && $output) {
                    echo ".";
                } else {
                    if (function_exists('debug_trace')) {
                        debug_trace(".");
                    }
                }
                $question = $DB->get_record('question', ['id' => $state->question], 'id, defaultmark, category');
                $parent = $DB->get_field('question_categories', 'parent', ['id' => $question->category]);

                if (!$parent) {
                    if ($verbose || $output) {
                        echo "f";
                    } else {
                        if (function_exists('debug_trace')) {
                            debug_trace("f ");
                        }
                    }
                    continue; // Fix lost states.
                }

                // Climb up to the upper root cats.
                while (!in_array($parent, array_keys($rootcats)) && $parent != 0) {
                    $parent = $DB->get_field('question_categories', 'parent', ['id' => $parent]);
                }

                if (!array_key_exists($parent, $qattempts)) {
                    $qattempts[$parent] = new StdClass;
                }
                $qattempts[$parent]->qcount = @$qattempts[$parent]->qcount + 1;
                if ($question->defaultmark == '1000') {
                    $qattempts[$parent]->ccount = @$qattempts[$parent]->ccount + 1;
                    $attempt->seriecanswered++;
                    if ($state->grade > 0) {
                        $qattempts[$parent]->cmatched = @$qattempts[$parent]->cmatched + 1;
                        $attempt->seriecmatched++;
                    }
                } else {
                    $qattempts[$parent]->acount = @$qattempts[$parent]->acount + 1;
                    $attempt->serieaanswered++;
                    if ($state->grade > 0) {
                        $qattempts[$parent]->amatched = @$qattempts[$parent]->amatched + 1;
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
        echo "No states ";
        if ($verbose && $output) {
            echo 0;
        }
    }

    // Save back compilation.
    $recattempt = new Stdclass;
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
        foreach ($qattempts as $catid => $catattempt) {
            $catattempt->categoryid = $catid;
            $catattempt->userid = $attempt->userid;
            $catattempt->attemptid = $attempt->uniqueid;
            $catattempt->quizid = $attempt->quiz;
            $select = "
                categoryid = ? AND
                userid = ? AND
                attemptid = ? AND
                quizid = ?
            ";
            $params = array($catid, $attempt->userid, $attempt->uniqueid, $attempt->quiz);
            if ($oldrec = $DB->get_record_select('userquiz_monitor_cat_stats', $select, $params)) {
                $catattempt->id = $oldrec->id;
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
    unset($qattempts);
}

/**
 *
 *
 */
function userquiz_precompile_userstats_worker(&$attempt, &$rootcats, &$block, $verbose) {
    global $DB;

    $output = optional_param('output', 0, PARAM_INT);
    $datacache = \report_examtraining\datacache::instance();
    $datacache->set_questionfields('id, defaultmark, category');
    $datacache->set_categoryfields('id, parent, name');

    // Initialize.
    $attempts = [];
    $coverage = [];

    // Compile answered and matched questions.
    if ($allstates = block_userquiz_monitor_get_all_user_records($attempt->uniqueid, $attempt->userid, null, true)) {

        if ($allstates->valid()) {
            foreach ($allstates as $state) {

                if ($verbose) {
                    echo ".";
                }

                if (!$question = $datacache->get_question($state->question)) {
                    if ($verbose) {
                        echo "m";
                    }
                    continue;
                }

                if (!array_key_exists($question->id, $coverage)) {
                    $coverage[$question->id] = new Stdclass;
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
            $params = array('userid' => $attempt->userid, 'blockid' => $block->instance->id, 'questionid' => $qid);
            $rec = $datacache->get_coverage($params);
            $rec->usecount = 0 + $rec->usecount + @$qc->seen;
            $rec->matchcount = 0 + $rec->matchcount + @$qc->matched;
        }
        // At the end, update all cache records.
        $datacache->save_coverages();
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
function userquiz_precompile_coverage_ratios($courseid = 0) {
    global $DB, $COURSE;

    if ($courseid == 0) {
        $courseid = $COURSE->id;
        $parentcontextid = context_course::instance($courseid)->id;
        $params['parentcontextid'] = $parentcontextid;
    }

    $params['blockname'] = 'userquiz_monitor';
    $blocks = $DB->get_records('block_instances', $params);

    echo "<pre>";

    if ($blocks) {
        mtrace('Compiling for '.count($blocks));
        foreach ($blocks as $ablock) {
            $theblock = block_instance('userquiz_monitor', $ablock);

            list($insql, $params) = $DB->get_in_or_equal($theblock->config->trainingquizzes);

            // Get all attempts from the training context.
            $sql = "
                SELECT DISTINCT
                    qa.userid,
                    u.username
                FROM
                    {quiz_attempts} qa,
                    {user} u
                WHERE
                    u.id = qa.userid AND
                    quiz $insql
            ";

            if ($users = $DB->get_records_sql($sql, $params)) {
                mtrace("Having ".count($users)." users to compile coverage for.");
                foreach ($users as $user) {
                    mtrace("Compiling for {$user->username}...\n");
                    userquiz_precompile_question_coverage_worker($user->userid, $theblock);
                }
            } else {
                mtrace("No attempts to compile");
            }
        }
    } else {
        mtrace('No blocks to compile');
    }

    echo "</pre>";

    return false;
}

/**
 * Compile coverage for a single user.
 * @params int $userid
 * @param objectref &$block block instance in context
 */
function userquiz_precompile_question_coverage_worker($userid, &$block) {
    global $DB;
    static $i = 0;

    // For debug.
    $output = optional_param('output', 0, PARAM_INT);

    // Finally compute coverage rates on distinct questions.

    // Count all questions available.
    $allcats = new StdClass;
    count_questions_in_categories_rec($block->config->rootcategory, $allcats);
    $allquestions = $allcats->count;

    $params = array($block->instance->id, $userid);

    $sql = "
        SELECT
            COUNT(DISTINCT questionid)
        FROM
            {userquiz_monitor_coverage}
        WHERE
            blockid = ? AND
            userid = ? AND
            usecount > 0
    ";
    $seenquestions = $DB->get_field_sql($sql, $params);

    $sql = "
        SELECT
            COUNT(DISTINCT questionid)
        FROM
            {userquiz_monitor_coverage}
        WHERE
            blockid = ? AND
            userid = ? AND
            matchcount > 0
    ";
    $matchedquestions = $DB->get_field_sql($sql, $params);

    $newrec = false;
    $params = ['userid' => $userid, 'blockid' => $block->instance->id, 'attemptid' => 0 ];
    if (!$rec = $DB->get_record('userquiz_monitor_user_stats', $params)) {
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
 * Get result data by weeks.
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
            qa.uniqueid = ua.uniqueid AND
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
        $quizzesidlist = $quizzeslist;
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
 * get precompiled subcats data for reports.
 * @param int $userid
 * @param array $quizzeslist
 * @param int $from
 * @param int $to
 */
function userquiz_get_attempts_subcats($userid, $quizzeslist, $from, $to) {
    global $DB;

    $quizzesclause = '';
    $params = [$userid];
    $inparams = [];
    if (!empty($quizzeslist)) {
        if (is_array($quizzeslist)) {
            $quizzesids = $quizzeslist;
        } else {
            $quizzesids = explode(',', $quizzeslist);
        }
        list($insql, $inparams) = $DB->get_in_or_equal($quizzesids);
        $quizzesclause = " qa.quiz $insql AND ";
    }

    foreach ($inparams as $p) {
        $params[] = $p;
    }

    $toclause = '';
    if (!empty($to)) {
        $toclause = ' AND qa.timefinish <= ? ';
        $params[] = $to;
    }

    $fromclause = '';
    if (!empty($from)) {
        $fromclause = ' AND qa.timefinish >= ? ';
        $params[] = $from;
    }

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

    $stats = $DB->get_records_sql($sql, $params);

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
    $quizzesclause = (!empty($quizzesidlist)) ? " qa.quiz IN ('$quizzesidlist') " : '';

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