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
namespace report_examtraining\stats;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/report/examtraining/locallib.php');
require_once($CFG->dirroot.'/blocks/userquiz_monitor/xlib.php');
require_once($CFG->dirroot.'/report/examtraining/classes/stub.class.php');

use StdClass;
use coding_exception;
use moodle_exception;
use context_course;

class compiler {

    static $counters = ['qcount', 'qmatched', 'acount', 'amatched', 'ccount', 'cmatched'];

    protected $stubs;

    /**
     * The generic function to get all attempt sets in all conditions.
     * @param int $courseid the course id
     * @param int $userid if 0, will compile for any user.
     * @param array $options an array of compilation options ('output', 'new', 'fromid', 'ids', 'limit')
     * @param string $set 'training' or 'exam'.
     */
    protected function get_attempts($courseid = 0, $userid = 0, $options = [], $set = 'training') {
        global $DB, $OUTPUT, $SESSION;

        $quizlist = $this->get_quiz_lists($courseid, $set);

        if (!empty($quizlist)) {
            list ($insql, $params) = $DB->get_in_or_equal($quizlist);
            $quizclause = " qa.quiz $insql AND ";
        } else {
            $quizclause = '';
            if (!empty($options['output'])) {
                mtrace("Empty quiz list.<br/>\n");
            }
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

        // New records only, choose never compiled attempts.
        $rangeclause = ' (ua.datecompiled = 0 OR ua.datecompiled IS NULL) AND ';

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

        if (!empty($options['limit'])) {
            $limit = $options['limit'];
        } else {
            $limit = 0;
        }

        // Note that report_examtraining table is scanned for "attempt" scope records.
        // @see $this->mark_attempt_in_db()
        /*
        $sql = "
            SELECT
                qa.*,
                q.course as courseid,
                qca.categories as categories,
                ua.id as uaid
            FROM
                {quiz} q,
                {qa_chooseconstraints_attempt} qca,
                {quiz_attempts} qa
            LEFT JOIN
                {report_examtraining} ua
            ON
                ua.uniqueid = qa.uniqueid AND
                ua.questionid IS NULL AND
                ua.categoryid IS NULL
            WHERE
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
        */
        $sql = "
            SELECT
                qa.*,
                q.course as courseid,
                ua.id as uaid
            FROM
                {quiz} q,
                {quiz_attempts} qa
            LEFT JOIN
                {report_examtraining} ua
            ON
                ua.uniqueid = qa.uniqueid AND
                ua.questionid IS NULL AND
                ua.categoryid IS NULL
            WHERE
                q.id = qa.quiz AND
                {$quizclause}
                {$courseclause}
                {$userclause}
                {$idlistclause}
                {$fromidclause}
                {$rangeclause}
                qa.timefinish != 0 AND
                qa.state = 'finished' AND
                qa.sumgrades > 0
             ORDER BY
                qa.id
        ";
        $attempts = $DB->get_records_sql($sql, $params, 0, $limit);

        return $attempts;
    }

    public function get_single_attempt($attemptid) {
        global $DB;

        $sql = "
            SELECT
                qa.*,
                q.course as courseid,
                ua.id as uaid
            FROM
                {quiz} q,
                {quiz_attempts} qa
            LEFT JOIN
                {report_examtraining} ua
            ON
                ua.uniqueid = qa.uniqueid AND
                ua.questionid IS NULL AND
                ua.categoryid IS NULL
            WHERE
                q.id = qa.quiz AND
                qa.id = ? AND
                qa.timefinish != 0
             ORDER BY
                qa.id
        ";
        $params = [$attemptid];
        $attempt = $DB->get_record_sql($sql, $params);
        if (!$attempt) {
            throw new moodle_exception("Bad attempt id $attemptid. Not found.");
        }
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);

        $trainingquizs = $this->get_quiz_lists($quiz->course, 'training');
        $examquizs = $this->get_quiz_lists($quiz->course, 'exam');
        $set = 'all';
        if (in_array($quiz->id, $trainingquizs)) {
            $set = 'training';
        }
        if (in_array($quiz->id, $trainingquizs)) {
            $set = 'exam';
        }

        return [$attempt, $set, $quiz->course];
    }

    /**
     * Get all quiz concerned by examtraining compilation
     * @param int $courseid find quizzes in this course
     * @param string $set 'exam' or 'training'.
     */
    public function get_quiz_lists($courseid, $set) {

        if (!empty($courseid)) {
            $cids[] = $courseid;
        } else {
            // 0 means all courses.
            $cids = block_userquiz_monitor_get_block_courses();
        }

        $quizlist = [];

        foreach ($cids as $cid) {

            if (!$theblock = block_userquiz_monitor_get_block($cid)) {
                continue;
            }

            $uqconfig = $theblock->config;
            $params = [];

            if ($set == 'training') {
                $quizzes = $uqconfig->trainingquizzes;
            } else if ($set == 'exam') {
                if (!empty($uqconfig->examquiz)) {
                    $quizzes = [$uqconfig->examquiz];
                }
            }

            // Agregate all quizzes for this block.
            if (!empty($quizzes)) {
                foreach ($quizzes as $qid) {
                    $quizlist[] = $qid;
                }
            }
        }

        return $quizlist;
    }

    /**
     * Precompile all uncompiled results in userquiz attempts records for exams. Calls worker functions per attempt.
     * Precompilation can be stepped with a max "worksize" in time or in number of samples.
     * @param int $courseid the course to compile for. If 0, will compile for all courses
     * @param array $options contextual calculation options
     */
    public function precompile_exams($courseid = 0, $options = []) {
        return $this->precompile_results($courseid, $options, 'exam');
    }

    /**
     * Precompile all uncompiled results in userquiz attempts records. Calls worker functions per attempt.
     * Precompilation can be stepped with a max "worksize" in time or in number of samples.
     * @param int $courseid the course to compile for. If 0, will compile for all courses
     * @param array $options contextual calculation options
     */
    public function precompile_results($courseid = 0, $options = [], $set = 'training') {
        global $CFG, $DB, $SESSION, $OUTPUT;

        $estimated = '--calculating--';

        // Setup session.
        if (!isset($SESSION->examtrainingruns)) {
            $SESSION->examtrainingruns = 0;
        }

        if (!isset($SESSION->examtrainingtime)) {
            if (!empty($options['auto'])) {
                $SESSION->examtrainingtime = time();
            }
        }

        if (!empty($options['uniqueid'])) {
            // We have an unique attempt request.
            // Get it an determine the set a posteriori.
            $attemptid = $DB->get_field('quiz_attempts', 'id', ['uniqueid' => $options['uniqueid']]);
            list($attempt, $set, $courseid) = $this->get_single_attempt($attemptid);
            $attempts[] = $attempt;
        } else if (!empty($options['attemptid'])) {
            // We have an unique attempt request.
            // Get it an determine the set a posteriori.
            list($attempt, $set, $courseid) = $this->get_single_attempt($options['attemptid']);
            $attempts[] = $attempt;
        } else {
            // All not yet compiled attempts.
            $attempts = $this->get_attempts($courseid, 0, $options, $set);
        }

        if (empty($attempts)) {
            return 0;
        }

        if (!empty($options['output'])) {
            $first = next($attempts);
            mtrace(" Found ".count($attempts)." attempts to compile from course $courseid <br/>Starting at attempt : {$first->uniqueid}\n");
        }

        if (empty($options['running'])) {
            // The interactive "force continue running" signal is gone. Unmark it. this compile step will be the last.
            if (!empty($options['output'])) {
                echo 'reseting runs config <br/>';
            }
            set_config('runs', 0, 'report_examtraining');
        }

        if (!$theblock = block_userquiz_monitor_get_block($courseid)) {
            return 0;
        }

        $topcats = block_userquiz_monitor_get_top_cats($theblock);
        $j = 0;
        $tj = 0; // Time estimation counter.
        $allcount = count($attempts);
        $time = time();

        foreach ($attempts as $attempt) {

            // Launch workers on attempts.
            $this->precompile_results_worker($attempt, $topcats, $theblock, $options, $set);
            $j++;
            $tj++;
            if ($tj == 100) {
                $time2 = time();
                $duration = $time2 - $time;
                $estimated = examtraining_raw_format_duration($duration / 100 * $allcount);
            }
            if ($tj == 500) {
                $time2 = time();
                $duration = $time2 - $time;
                $estimated = examtraining_raw_format_duration($duration / 500 * $allcount);
            }
            if ($tj == 1000) {
                $time2 = time();
                $duration = $time2 - $time;
                $estimated = examtraining_raw_format_duration($duration / 1000 * $allcount);
            }

            echo "\r $j / $allcount       estimated($estimated)                  ";
        }

        echo "\n";

        // Are there more attempts ? just count them to respawn.
        unset($options['limit']); // To know really how much.
        $attemptsleft = $this->get_attempts($courseid, 0, $options, $set); // Training implicit.
        $attemptsleft = count($attemptsleft);

        return $attemptsleft; // Number of attempts yet to compile.
    }

    /**
     * Clear all precompiled results in a course.
     * @TODO : make a more clever function that "retires" course data to all non course results, while keeping
     * other compiled results safe.
     * @param int $courseid At the moment, not used. Clears everything.
     */
    public function clear_results($courseid) {
        global $DB;

        if ($courseid) {
            $DB->delete_records('report_examtraining', ['course' => $courseid]);
        } else {
            $DB->delete_records('report_examtraining');
        }
    }

    /**
     * precompile a set of attempts records
     * @param int $courseid
     * @param array $ids list of attempt ids.
     * @param array $options compilation options
     */
    public function precompile_some_results($courseid = 0, array $ids = [], $options = []) {
        global $DB;

        $options['ids'] = $ids;
        $attempts = $this->get_attempts($courseid, 0, $options);

        // Process required attempts.
        if (empty($attempts)) {
            return false;
        }

        if (!$theblock = block_userquiz_monitor_get_block($courseid)) {
            return 0;
        }

        $examcontext = $theblock->config;
        $topcats = block_userquiz_monitor_get_top_cats($theblock);

        $j = 0;

        foreach ($attempts as $attempt) {

            $this->precompile_results_worker($attempt, $topcats, $theblock, $options);
            $groupid = $this->get_user_group($attempt->courseid, $attempt->userid);
            $dims = [
                'course' => $attempt->courseid,
                'blockid' => $theblock->instance->id,
                'groupid' => $groupid,
                'userid' => $attempt->userid,
                'uniqueid' => $attempt->uniqueid
            ];
            $this->mark_attempt_in_db($dims, $attempt->timefinish);
            if ($attempt->quiz == @$examcontext->examquiz) {
                $this->mark_exam($dims);
            }
            $j++;
        }
        if (!empty($options['output'])) {
            mtrace("Compiled $j records.");
        }
        return $j;
    }

    /**
     * precompile all uncompiled results by cron in userquiz attempts records for all courses
     *
     */
    public function cron_results() {
        global $DB;

        $output = optional_param('output', 0, PARAM_INT);
        $options['output'] = $output || defined('CLI_SCRIPT');

        // Get all uncompiled and finished attempts in all courses for all users.

        $attempts = $this->get_attempts(0, 0, $options);

        if (empty($attempts)) {
            if ($options['output']) {
                mtrace("No attempt data\n<br/>");
            }
            return 0;
        }

        $attemptscount = count($attempts);
        if ($options['output']) {
            mtrace("Found to compile : $attemptscount records\n<br/>");
        }

        // split results into course sets as each course will have its own block as compilation context.
        $courseattempts = [];
        foreach ($attempts as $attempt) {
            $courseattempts[$attempt->courseid][] = $attempt;
        }

        // Now compile per course.

        $t = 0;

        foreach ($courseattempts as $courseid => $attempts) {

            // Set default value.
            if (!$theblock = block_userquiz_monitor_get_block($courseid)) {
                continue;
            }

            $examcontext = $theblock->config;
            $topcats = block_userquiz_monitor_get_top_cats($theblock);

            $j = 0;

            foreach ($attempts as $attempt) {

                if ($options['output']) {
                    echo "c$attempt->id ";
                }

                $this->precompile_results_worker($attempt, $topcats, $theblock, $options);

                $groupid = $this->get_user_group($attempt->courseid, $attempt->userid);
                $dims = [
                    'course' => $courseid,
                    'blockid' => $theblock->instance->id,
                    'groupid' => $groupid,
                    'userid' => $attempt->userid,
                    'uniqueid' => $attempt->uniqueid
                ];
                $this->mark_attempt_in_db($dims, $attempt->timefinish);
                if ($attempt->quiz == @$examcontext->examquiz) {
                    $this->mark_exam($dims);
                }

                $j++;
                $t++;
            }

            if ($options['output']) {
                mtrace("Compiled $j attempts of course $courseid\n<br/>");
            }
        }

        if ($options['output']) {
            mtrace("Compiled $t attempts in total\n<br/>");
        }

        return $t;
    }

    /**
     * processes all states of a single attempt.
     * @param object ref &$attempt the attempt data
     * @param array ref &$topcats an array of categories that are just under the rootcat
     * @param object $theblock the userquiz block instance
     * @param boolean $options may contain 'output' and/or 'withcats'
     * @param string $set 'exam' or 'training'
     */
    protected function precompile_results_worker($attempt, &$topcats, $theblock, $options, $set) {
        global $DB;

        $isexam = ($set == 'exam');

        if (empty($attempt->layout)) {
            return false;
        }

        // Fix user behaviour generating null attempts.
        if ($attempt->sumgrades == 0) {
            if (!empty($options['output'])) {
                echo "Probable fake attempt. Skipping\n";
            }
            return false;
        }

        // Get user group if any. Returns 0 if no group.
        $groupid = $this->get_user_group($attempt->courseid, $attempt->userid);

        // Get real instances and discard all page jumps.
        $realinstances = [];
        $sql = "
            SELECT
                slot,
                questionid
            FROM
                {quiz_attempts} qa,
                {question_attempts} qua
            WHERE
                qa.uniqueid = qua.questionusageid AND
                qa.id = ?
        ";
        $slotquestions = $DB->get_records_sql($sql, [$attempt->id]);
        if ($slotquestions) {
            foreach ($slotquestions as $sq) {
                $realinstances[] = $sq->questionid;
            }
        }

        // the max number questions to answer in the attempt.
        $qsize = count($realinstances);

        // Get quiz questions
        $realinstancelist = implode(',', $realinstances);
        if ($questions = $DB->get_records_list('question', 'id', $realinstances)) {
            if (!empty($options['output'])) {
                 mtrace(" ($attempt->id:$realinstancelist) \n<br/>");
            }
        }

        // Real count of questions in the attempt. Note that questions ARE TO BE distinct as required by
        // attempts slot pick-up process.
        $qsize = count($realinstances);

        // Compile answered and matched questions for this attempt; getting as recordset for performance.
        if (!$allstates = block_userquiz_monitor_get_all_user_records($attempt->uniqueid, $attempt->userid, null, true)) {
            if (!empty($options['output'])) {
                echo "No states ";
            }
            return 0;
        }

        $this->stubs = [];
        $stubsareset = false;

        if ($allstates->valid()) {
            $i = 0;

            // Scan all individual question states in the attempt.
            foreach ($allstates as $state) {
                if (!empty($options['output'])) {
                    echo ".";
                }

                // Fetch real proposed question, after random pick-up.
                $question = $DB->get_record('question', ['id' => $state->question], 'id, defaultmark');
                $questionversion = $DB->get_record('question_versions', ['questionid' => $question->id]);
                if (!empty($questionversion)) {
                    $question->category = $DB->get_field('question_bank_entries', 'questioncategoryid', ['id' => $questionversion->questionbankentryid]);

                    // Climb up to the upper root cats.
                    $parent = $question->category;
                    while (!in_array($parent, array_keys($topcats)) && $parent != 0) {
                        $parent = $DB->get_field('question_categories', 'parent', ['id' => $parent]);
                    }
                } else {
                    echo "Unable to find category for question {$question->qtype} {$state->question} ";
                    $parent = 0;
                    $question->category = 0;
                }

                if ($parent == 0) {
                    if (!empty($options['output'])) {

                        $buf = "topcats : \n";
                        $buf .= implode(",", array_keys($topcats))."\n";
                        $buf .= "Searching for top parent of : ".$question->category."\n";
                        $buf .= "Attempt id : $attempt->id \n";
                        $buf .= "Attempt unique id : $attempt->uniqueid \n";
                        $buf .= "Question id : $question->id \n";
                        debug_trace($buf);

                        echo "f";
                    }
                    continue; // Lost states.
                }

                if (!empty($options['output'])) {
                    echo "Defining stubs for attempt ID/UID: ".$attempt->id.'/'.$attempt->uniqueid."\n";
                }

                // This confirms stubs or add new keys for the present state.
                $this->define_stubs($attempt, $theblock, $groupid, $parent, $question, $isexam);
                $stubsareset = true;

                // Check we have already seen this question in absolute.
                $dims = [
                    'categoryid' => $parent,
                    'questionid' => $question->id,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                $key = stub::forge_key($dims, 0);

                $questionisinstub = $this->stubs['byquestion'][$key]->has_data();

                // Check we have already seen this question in this training instance.
                $dims = [
                    'course' => $attempt->courseid,
                    'blockid' => $theblock->instance->id,
                    'categoryid' => $parent,
                    'questionid' => $question->id,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                $key = stub::forge_key($dims, 0);
                $questionisincoursestub = $this->stubs['byquestionperinstance'][$key]->has_data();

                // Ensure categoryperattemptperuser is timestamped.
                // NOT WORKING : not yet in DB.
                $this->set_stubs('bytopcatperattempt', 'attemptdate', $attempt->timefinish);
                if (!$questionisincoursestub) {
                    $this->set_stubs('bytopcatperattemptdistinct', 'attemptdate', $attempt->timefinish);
                }

                // Start agregate stubs.

                $matched = false;
                $seriematched = false;
                if ($question->defaultmark == '1000') {
                    $seriecount = 'ccount';
                    if ($state->grade > 0) {
                        $seriematched = 'cmatched';
                        $matched = true;
                    }
                } else {
                    $seriecount = 'acount';
                    if ($state->grade > 0) {
                        $seriematched = 'amatched';
                        $matched = true;
                    }
                }

                // Dispatch datas.
                $this->inc_stubs(null, 'qcount', ['questionid' => $question->id, 'categoryid' => $parent]);

                $this->inc_stubs(null, $seriecount, ['questionid' => $question->id, 'categoryid' => $parent]);

                if (!empty($seriematched)) {
                    $this->inc_stubs(null, $seriematched, ['questionid' => $question->id, 'categoryid' => $parent]);
                }

                if ($matched) {
                    $this->inc_stubs(null, 'qmatched', ['questionid' => $question->id, 'categoryid' => $parent]);
                }

                $i++;
            }

            // States are compiled.

            if (!empty($options['output'])) {
                mtrace("Compiled $i states \n</br>");
            }

            // Free some memory here ?
            unset($allstates);

            if ($stubsareset) {
                // Inc attempt counter in all global (non attempt specific) stubs.
                $dims = [
                    'userid' => $attempt->userid,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                $this->inc_stub('byuser', $dims, 'attempts');

                $dims = [
                    'categoryid' => $parent,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                $this->inc_stubs('bytopcat', 'attempts');

                $dims = [
                    'course' => $attempt->courseid,
                    'blockid' => $theblock->instance->id,
                    'categoryid' => $parent,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                $this->inc_stubs('bytopcatperinstance', 'attempts');

                $dims = [
                    'course' => $attempt->courseid,
                    'blockid' => $theblock->instance->id,
                    'categoryid' => $parent,
                    'isexam' => ($set == 'exam'),
                    'qdistinct' => 1
                ];
                $this->inc_stubs('bytopcatperinstancedistinct', 'attempts');

                $dims = [
                    'groupid' => $groupid,
                    'userid' => $attempt->userid,
                    'categoryid' => $parent,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                $this->inc_stubs('bytopcatperuseringroup', 'attempts');

                $dims = [
                    'categoryid' => $parent,
                    'questionid' => $question->id,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                $this->inc_stubs('byquestion', 'attempts');

                $dims = [
                    'course' => $attempt->courseid,
                    'blockid' => $theblock->instance->id,
                    'categoryid' => $parent,
                    'questionid' => $question->id,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                $this->inc_stubs('byquestionperinstance', 'attempts');

                $dims = [
                    'userid' => $attempt->userid,
                    'categoryid' => $parent,
                    'questionid' => $question->id,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                $this->inc_stubs('byquestionperuser', 'attempts');

                $dims = [
                    'course' => $attempt->courseid,
                    'blockid' => $theblock->instance->id,
                    'groupid' => $groupid,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                $this->inc_stubs('byinstancecoursegroup', 'attempts');

                // Set attempts size in attempt related dimensions.
                $this->set_stubs('qattempts', 'attempts', 1); // Should be one one here.
                $this->set_stubs('qattempts', 'qsize', $qsize);
                $this->set_stubs('qattempts', 'attemptdate', $attempt->timefinish);
                $this->set_stubs('bytopcatperattempt', 'attempts', 1); // Should be one one here.
                $this->set_stubs('bytopcatperattempt', 'qsize', $qsize);
                $this->set_stubs('bytopcatperattemptdistinct', 'attempts', 1); // Should be one one here.
                $this->set_stubs('bytopcatperattemptdistinct', 'qsize', $qsize);

                $dims = [
                    'course' => $attempt->courseid,
                    'blockid' => $theblock->instance->id,
                    'groupid' => $groupid,
                    'userid' => $attempt->userid,
                    'uniqueid' => $attempt->uniqueid,
                    'isexam' => $isexam,
                    'qdistinct' => 0
                ];
                if (empty($options['dryrun'])) {
                    $this->mark_attempt_in_db('qattempts', $dims, $attempt->timefinish);
                }
            }

            // All states of the attemps have been checked and aggregated in stubs. Now save attempt results in DB.
            if (empty($options['dryrun'])) {
                if (!empty($options['output'])) {
                    mtrace("Saving stubs...\n</br>");
                }
                $this->save_stubs();
            } else {
                if (!empty($options['output'])) {
                    mtrace("NOT Saving stubs (dryrun mode)...\n</br>");
                    print_r($this->stubs);
                }
            }
        }
    }

    /**
     * TO REWRITE
     * Get result data by weeks.
     * @param int $userid
     * @param int $courseid
     * @param int $from
     * @param int $to
     */
    public function get_weekly_globals($userid, $courseid, $from, $to, $distinct = 0) {
        global $DB;

        $fromclause = ($from) ? " ua.attemptdate > ? AND " : '';
        $toclause = ($to) ? " ua.attemptdate <= ? " : '';

        $sql = "
            SELECT
                CONCAT(YEAR(FROM_UNIXTIME(attemptdate)), '_', WEEK(FROM_UNIXTIME(attemptdate))),
                YEAR(FROM_UNIXTIME(attemptdate)) as year,
                WEEK(FROM_UNIXTIME(attemptdate)) as week,
                u.firstname,
                u.lastname,
                SUM(qcount) as qcount,
                SUM(qmatched) as qmatched,
                SUM(acount) as acount,
                SUM(ccount) as ccount,
                SUM(amatched) as amatched,
                SUM(cmatched) as cmatched
            FROM
                {report_examtraining} ua,
                {user} u
            WHERE
                u.id = ua.userid AND
                ua.userid = ? AND
                ua.course = ? AND
                questionid IS NULL AND
                categoryid IS NULL AND
                uniqueid IS NOT NULL AND
                qdistinct = ? AND
                $fromclause
                $toclause
            GROUP BY
                YEAR(FROM_UNIXTIME(attemptdate)),
                WEEK(FROM_UNIXTIME(attemptdate))
            ORDER BY
                YEAR(FROM_UNIXTIME(attemptdate)),
                WEEK(FROM_UNIXTIME(attemptdate))
        ";

        $params = [$userid, $courseid, $distinct, $from, $to];

        if ($stats = $DB->get_records_sql($sql, $params)) {
            $this->post_calculate_hit_ratios($stats);
        }

        return $stats;
    }

    /**
     * Get global counts for a user or a set of users.
     * if period is not used, we use a quick request on full range globalizers. If they are defined, 
     * the request diggs down into "per attempt" results to match time range.
     * @param int $userid the user
     * @param int $courseid the course
     * @param int $from start of compilation period
     * @param int $to end of compilation period
     */
    public function get_user_globals($userid, $courseid, $from = null, $to = null, $distinct = 0, $isexam = 0) {
        global $DB;

        $sqlparams = [];
        if (is_array($userid)) {
            list($insql, $userids) = $DB->get_in_or_equal($userid);
            $sqlparams = $userids;
            $userclause = " userid $insql AND ";
        } else {
            $sqlparams[] = $userid;
            $userclause = " userid = ? AND ";
        }
        $sqlparams[] = $courseid;
        $sqlparams[] = $distinct;

        if (is_null($from)) {
            $select = $userclause.' course = ? AND questionid IS NULL AND uniqueid IS NULL AND categoryid IS NULL AND qdistinct = ?';
            $stats = $DB->get_records_select('report_examtraining', $select, $sqlparams);
        } else {
            // Get all "per attempt" results in the required time range.
            // Nullify question axis.
            $sqlparams[] = $from;
            $sqlparams[] = $to;
            $sqlparams[] = $isexam;
            $sql = "
                SELECT
                    userid,
                    groupid,
                    SUM(qcount) as qcount,
                    SUM(qmatched) as qmatched,
                    SUM(acount) as acount,
                    SUM(amatched) as amatched,
                    SUM(ccount) as ccount,
                    SUM(cmatched) as cmatched
                 FROM
                    {report_examtraining}
                 WHERE
                    {$userclause}
                    course = ? AND
                    questionid IS NULL AND
                    categoryid IS NULL AND
                    uniqueid IS NOT NULL AND
                    qdistinct = ? AND
                    attemptdate > ? AND
                    attemptdate <= ? AND
                    stubtype = 'qattempts' AND
                    isexam = ?
                 GROUP BY
                    userid
            ";
            $stats = $DB->get_records_sql($sql, $sqlparams);
        }

        // Post calculate hit ratios.
        $this->post_calculate_hit_ratios($stats);

        return $stats;
    }

    /**
     * Get attempt count per user for top stats.
     * @param int $courseid the course
     * @param array $userids if userids is null, then consider all course scope. Otherwise it should be a group set.
     * @param int $isexam true for exams, false for training
     * @param int $from start of time range
     * @param int $to start of time range
     * @param string $orderby is 'DESC' or 'START'
     */
    public function get_attempt_count($courseid, $userids = null, $isexam = 0, $from = null, $to = null, $orderby = 'DESC', $limit = 15) {
        global $DB;

        $params = [$courseid];
        $params[] = $isexam;

        list($fromclause, $toclause) = $this->prepare_from_to_range($from, $to, $params);

        if (is_null($userids)) {
            // fastest query.
            $sql = "
                SELECT
                    userid,
                    groupid,
                    COUNT(*) as attempts
                FROM
                    {report_examtraining}
                WHERE
                    userid IS NOT NULL AND
                    course = ? AND
                    questionid IS NULL AND
                    categoryid IS NULL AND
                    uniqueid IS NOT NULL AND
                    isexam = ? AND
                    qdistinct = 0
                    {$fromclause}
                    {$toclause}
                GROUP BY
                    userid
                ORDER BY
                    attempts $orderby
                LIMIT 0, $limit
            ";
            $stats = $DB->get_records_sql($sql, $params);
            return $stats;
        } else {
            // else forge slower query.
            list($insql, $inparams) = $DB->get_in_or_equal($userids);

            $sql = "
                SELECT
                    userid,
                    groupid,
                    COUNT(*) as attempts
                FROM
                    {report_examtraining}
                WHERE
                    userid {$insql} AND
                    course = ? AND
                    questionid IS NULL AND
                    categoryid IS NULL AND
                    uniqueid IS NOT NULL
                    isexam = ? AND
                    qdistinct = 0
                    {$fromclause}
                    {$toclause}
                GROUP BY
                    userid
                ORDER BY
                    attempts $orderby
                LIMIT 0, $limit
            ";

            foreach ($params as $p) {
                $inparams[] = $p;
            }
            $stats = $DB->get_records_sql($sql, $inparams);
        }

        return $stats;
    }

    /**
     * get count of question attempted per suser
     * @param int $courseid the course
     * @param array $userids if userids is null, then consider all course scope. Otherwise it should be a group set.
     * @param int $from start of time range
     * @param int $to start of time range
     * @param string $orderby is 'DESC' or 'START'
     */
    public function get_by_function_count($courseid, $userids = null, $from = null, $to = null, $orderby = 'DESC') {
        global $DB;

        $num = 15;

        $params = [$courseid];
        list($fromclause, $toclause) = $this->prepare_from_to_range($from, $to, $params);

        $sql = "
            SELECT
                userid,
                groupid,
                SUM(qcount) as qcount
            FROM
                {report_examtraining}
            WHERE
                userid $insql AND
                course = ? AND
                questionid IS NULL AND
                categoryid IS NULL AND
                uniqueid IS NOT NULL AND
                qdistinct = 0 AND
                isexam = 0
                {$fromclause}
                {$toclause}
            GROUP BY
                userid
            ORDER BY
                qcount {$orderby}
            LIMIT
                0, {$num}
        ";

        $topquestions = $DB->get_records_sql($sql, $params);
        return $topquestions;
    }

    /**
     * Get top report per coverage. Coverage is equiv to matched questions on distinct question in all training space.
     * @param int $courseid the course
     * @param array $userids if userids is null, then consider all course scope. Otherwise it should be a group set.
     * @param int $from start of time range
     * @param int $to start of time range
     * @param string $orderby is 'DESC' or 'START'
     */
     public function get_per_coverage($courseid, $userids = null, $from = null, $to = null, $orderby = 'DESC') {
        global $DB;

        $num = 15;

        $params = [$courseid];
        list($fromclause, $toclause) = $this->prepare_from_to_range($from, $to, $params);

        $sql = "
            SELECT
                userid,
                groupid,
                SUM(qmatched) as qmatched
            FROM
                {report_examtraining}
            WHERE
                userid $insql AND
                course = ? AND
                questionid IS NULL AND
                categoryid IS NOT NULL AND
                qdistinct = 1 AND
                isexam = 0
            GROUP BY
                userid
            ORDER BY
                qmatched {$orderby}
            LIMIT
                0, {$num}
        ";

        $topmatched = $DB->get_records_sql($sql, $params3);
        return $topmatched;
     }

    /**
     * Get stats per top category
     */
    public function get_per_categories($courseid = 0, $group = null, $from = null, $to = null) {
        global $DB;

        $courseclause = '';
        $sqlparams = [];

        if ($courseid) {
            $sqlparams[] = $courseid;
            $courseclause = "course = ? AND ";
        } else {
            $courseclause = "course IS NOT NULL AND ";
        }

        if (!is_null($group)) {
            $targetusers = $DB->get_records('groups_members', ['groupid' => $group->id]);
            $userids = [];
            foreach ($targetusers as $gm) {
                $userids[] = $gm->userid;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($userids);
            if (!empty($inparams)) {
                foreach ($inparams as $p) {
                    $sqlparams[] = $p;
                }
            }
            $userclause = " userid $insql AND ";
        }

        if (is_null($from)) {
            // Quick query.
            $select = $courseclause.$userclause." categoryid IS NOT NULL AND questionid IS NULL AND uniqueid IS NULL AND userid IS NULL ";
            $stats = $DB->get_records_select('report_examtraining', $select, $sqlparams);

            // Post calculate hit ratios.
            $this->post_calculate_hit_ratios($stats);

            return $stats;
        }

        $sqlparams[] = $from;
        $sqlparams[] = $to;

        // general case, sum attempts.
        $sql = "
            SELECT
                categoryid,
                SUM(attempts) as attempts,
                AVG(qsize) as avgqsize,
                SUM(qcount) as qcount,
                SUM(qmatched) as qmatched,
                SUM(acount) as acount,
                SUM(amatched) as amatched,
                SUM(ccount) as ccount,
                SUM(cmatched) as cmatched
            FROM
                {report_examtraining}
            WHERE
                {$courseclause}
                {$userclause}
                questionid IS NULL AND
                categoryid IS NOT NULL AND
                userid IS NOT NULL AND
                uniqueid IS NOT NULL AND
                qdistinct = 0 AND
                attemptdate >= ? AND
                attemptdate <= ?
            GROUP BY
                categoryid
        ";

        $stats = $DB->get_records_sql($sql, $sqlparams);

        // Post calculate hit ratios.
        $this->post_calculate_hit_ratios($stats);

        return $stats;
    }

    /**
     * Get course or group level overall stats.
     */
    public function get_course_globals($courseid, $groupid = 0, $from = null, $to = null, $distinct = 0) {
        global $DB;

        $userclause = false;

        if ($groupid) {

            $targetusers = $DB->get_records('groups_members', ['groupid' => $groupid]);

            // Filters teachers out.
            $context = context_course::instance($courseid);
            $userids = [];
            foreach ($targetusers as $gmember) {
                if (has_capability('report/examtraining:isteacher', $context, $gmember->userid)) {
                    unset($targetusers[$gmember->userid]);
                }
                $userids[] = $gmember->userid;
            }

            $sqlparams = [];
            if (!empty($userids)) {
                list($insql, $sqlparams) = $DB->get_in_or_equal($userids);
                $userclause = " userid $insql AND ";
            } else {
                return [];
            }
        }

        $sqlparams[] = $courseid;
        $sqlparams[] = $distinct;

        if (!$userclause) {
            if (is_null($from)) {
                $userclause = 'userid IS NULL AND groupid IS NULL AND ';
            } else {
                $userclause = 'userid IS NOT NULL AND groupid IS NOT NULL AND ';
            }
        }

        $fields =  '
            course,
            groupid,
            SUM(attempts) as attempts,
            SUM(qcount) as qcount,
            SUM(qmatched) as qmatched,
            SUM(acount) as acount,
            SUM(amatched) as amatched,
            SUM(ccount) as ccount,
            SUM(cmatched) as cmatched
        ';

        if (is_null($from)) {
            $select = $userclause.' course = ? AND questionid IS NULL AND uniqueid IS NULL AND categoryid IS NULL AND qdistinct = ?';
            $stats = $DB->get_records_select('report_examtraining', $select, $sqlparams, 'id', $fields);
        } else {
            // Get all "per attempt" results in the required time range.
            // Nullify question axis.
            $sqlparams[] = $from;
            $sqlparams[] = $to;
            $sql = "
                SELECT
                    {$fields}
                 FROM
                    {report_examtraining}
                 WHERE
                    {$userclause}
                    course = ? AND
                    questionid IS NULL AND
                    categoryid IS NULL AND
                    uniqueid IS NOT NULL AND
                    qdistinct = ? AND
                    attemptdate > ? AND
                    attemptdate <= ?
            ";
            $stats = $DB->get_records_sql($sql, $sqlparams);
            // one single result expected in all cases.
        }

        // Post calculate hit ratios.
        $this->post_calculate_hit_ratios($stats);

        return $stats;
    }

    /**
     * Get group workratio values which are counters / group size
     */
    public function get_groups_workratio($courseid, $from = null, $to = null) {
        global $DB;

        $sql = "
            SELECT
                groupid,
                attempts,
                qcount,
                qmatched,
                acount,
                amatched,
                ccount,
                cmatched
            FROM
                {report_examtraining}
            WHERE
                userid IS NULL AND
                groupid IS NOT NULL AND
                questionid IS NULL AND
                categoryid IS NULL AND
                course = ? AND
                qdistinct = 0
        ";
        $stats = $DB->get_records_sql($sql, [$courseid]);

        $groups = $DB->get_records('groups', ['courseid' => $courseid]);
        if (!empty($groups)) {
            foreach ($groups as &$g) {
                $gsize = $DB->count_records('groups_members', ['groupid' => $g->id]);
                if (array_key_exists($g->id, $stats)) {
                    $g->wqratio = $stats[$g->id]->qcount / $gsize; // workratio in questions
                    $g->wattratio = $stats[$g->id]->attempts / $gsize; // workratio in attempts
                } else {
                    $g->wqratio = 0; // workratio in questions
                    $g->wattratio = 0; // workratio in attempts
                }
            }
        }

        return $groups;
    }

    /**
     * get hit ratio per attempts
     *
     */
    public function get_attempts_stats($userid, $courseid, $from = null, $to = null, $distinct = 0, $set = 'training') {
        global $DB;

        $params = [$userid];
        $params[] = $courseid;
        $params[] = $distinct;

        if ($set == 'training') {
            $params[] = 0;
        } else {
            $params[] = 1;
        }

        if (is_null($from)) {
            // Quick way when getting full range.
            $select = ' stubtype = "qattempts" AND userid = ? AND course = ? AND uniqueid IS NOT NULL AND categoryid IS NULL AND questionid IS NULL AND qdistinct = ? AND isexam = ?';
            $stats = $DB->get_record_select('report_examtraining', $select, $params);
            $this->post_calculate_hit_ratios($stats);
            return $stats;
        }

        $fromclause = " AND ua.attemptdate >= $from ";
        $toclause = " AND ua.attemptdate <= $to ";

        $sql = "
            SELECT
                ua.uniqueid,
                ua.attemptdate,
                u.firstname,
                u.lastname,
                ua.userid as userid,
                ua.groupid as groupid,
                ua.qcount,
                ua.qmatched,
                ua.acount,
                ua.amatched,
                ua.ccount,
                ua.cmatched
            FROM
                {report_examtraining} ua,
                {user} u
            WHERE
                u.id = ua.userid AND
                ua.userid = ? AND
                ua.course = ? AND
                ua.questionid IS NULL AND
                ua.categoryid IS NULL AND
                ua.uniqueid IS NOT NULL AND
                ua.qdistinct = ? AND
                ua.isexam = ?
                $fromclause
                $toclause
            ORDER BY
                ua.attemptdate
        ";

        $stats = $DB->get_records_sql($sql, $params);

        $this->post_calculate_hit_ratios($stats);

        return $stats;
    }

    /**
     * get precompiled subcats results for one user and per category.
     * @param int $userid the user
     * @param int $courseid the course
     * @param int $from start of timerange
     * @param int $to end of timerange
     */
    public function get_attempts_subcats($userid, $courseid, $from = null, $to = null, $distinct = 0) {
        global $DB;

        $sqlparams = [];

        if ($userid == 0) {
            if (is_null($from)) {
                $userclause = ' userid IS NULL AND groupid IS NULL AND ';
            } else {
                $userclause = ' userid IS NOT NULL AND groupid IS NOT NULL AND ';
            }
        } else {
            $userclause = ' userid = ? AND ';
            $sqlparams[] = $userid;
        }

        $sqlparams[] = $courseid;

        if (is_null($from)) {
            // Quicker way when getting all range.
            $sqlparams[] = $distinct;
            $select = $userclause.' course = ? AND questionid IS NULL AND uniqueid IS NULL AND categoryid IS NOT NULL AND qdistinct = ?';
            $stats = $DB->get_records_select('report_examtraining', $select, $sqlparams, '', 'categoryid, qcount, qmatched, acount, amatched, ccount, cmatched');

            $this->post_calculate_hit_ratios($stats);

            return $stats;
        }

        $sqlparams[] = $distinct;

        $fromclause = ' AND attemptdate >= ? ';
        $sqlparams[] = $from;

        $toclause = ' AND attemptdate <= ? ';
        $sqlparams[] = $to;

        // Get "per category per attempt" precompiled sums.
        $sql = "
            SELECT
                categoryid,
                userid,
                groupid,
                SUM(qcount) as qcount,
                SUM(qmatched) as qmatched,
                SUM(acount) as acount,
                SUM(amatched) as amatched,
                SUM(ccount) as ccount,
                SUM(cmatched) as cmatched
            FROM
                {report_examtraining}
            WHERE
                {$userclause}
                course = ? AND
                questionid IS NULL AND
                categoryid IS NOT NULL AND
                uniqueid IS NOT NULL AND
                qdistinct = ?
                $fromclause
                $toclause
            GROUP BY
                categoryid
        ";

        /*
        echo $sql;
        print_object($sqlparams);
        */

        $stats = $DB->get_records_sql($sql, $sqlparams);

        $this->post_calculate_hit_ratios($stats);

        return $stats;
    }

    /*
     * Get attempt count per size for a user
     *
     */
    public function get_attempts_per_size($userid, $from = NULL, $to = NULL) {
        global $DB, $COURSE;

        $params = [$userid];
        $params[] = $COURSE->id;

        $fromclause = '';
        if (!is_null($from)) {
            $fromclause = ' AND ua.attemptdate > ? ';
            $params[] = $from;
        }

        $toclause = '';
        if (is_null($to)) {
            $toclause = ' AND ua.attemptdate <= ? ';
            $params[] = $to;
        }

        // Compute attempts "per module size".

        $sql = "
            SELECT
                qsize,
                COUNT(*) as acount
            FROM
                {report_examtraining} ua
            WHERE
                userid = ? AND
                course = ? AND
                questionid IS NULL AND
                categoryid IS NULL
                {$fromclause}
                {$toclause}
            GROUP BY
                qsize
            ORDER BY
                qsize
        ";

        $stats = $DB->get_records_sql_menu($sql, $params);
        return $stats;
    }

    /**
     * calculate hit ratios.
     * @param array &$stats
     */
     protected function post_calculate_hit_ratios(&$stats) {
        if (!empty($stats)) {
            foreach ($stats as &$stat) {
                if (!isset($stat->qmatched)) {
                    print_object($stat);
                    debugging("missing attribs");
                }
                $stat->qratio = ($stat->qcount != 0) ? floor($stat->qmatched / $stat->qcount * 100) : 0;
                $stat->aratio = ($stat->acount != 0) ? floor($stat->amatched / $stat->acount * 100) : 0;
                $stat->cratio = ($stat->ccount != 0) ? floor($stat->cmatched / $stat->ccount * 100) : 0;
            }
        }
    }

    // Stub manipulation functions.

    /**
     * Increments a counter in all stubs. Assumes the stub object has been initilized.
     * @param array $stub a stat aggregation stub.
     * @param array $counter the counter field.
     * @param array $filter, allow restriction of effect to those stubs who match the filter.
     */
    private function inc_stubs($type, $counter, $filter = []) {

        // Scan all.
        if (is_null($type)) {
            foreach ($this->stubs as $stubtype => $stubs) {
                foreach ($stubs as $key => $stub) {

                    if (!empty($filter)) {
                        foreach ($filter as $dim => $value) {
                            if ($stub->has_dim($dim)) {
                                if (!($stub->get_dim($dim) == $value)) {
                                    continue 2;
                                }
                            }
                        }
                    }

                    $stub->inc_value($counter);
                }
            }
        } else {
            // Scan given type.
            foreach ($this->stubs[$type] as $stub) {

                if (!empty($filter)) {
                    foreach($filter as $dim => $value) {
                        if ($stub->has_dim($dim)) {
                            if (!($stub->get_dim($dim) == $value)) {
                                continue 2;
                            }
                        }
                    }
                }

                $stub->inc_value($counter);
            }
        }
    }

    /**
     * Increments a counter in one specific stub.
     * @param array $name a stat aggregation stub's name.
     * @param array $counter the counter field.
     */
    private function inc_stub($name, $dims, $counter, $distinct = 0) {

        $key = stub::forge_key($dims, $distinct);

        if (array_key_exists($name, $this->stubs)) {
            if (array_key_exists($key, $this->stubs[$name])) {
                $this->stubs[$name][$key]->inc_value($counter);
            }
        }
    }

    /**
     * adds some extra data in a stub. Assumes the stub object has been initialized.
     * @param array $stub a stat aggregation stub.
     * @param array $key the stub record field key.
     * @param array $value the stub record value.
     * @param array $dimensions dimensions name and values in order top to down.
     */
    private function set_stub($name, $key, $counter, $value) {

        if (is_null($this->stubs)) {
            throw new coding_exception("Stubs not yet initialized. set_stub() called too early.");
        } 

        if (array_key_exists($name, $this->stubs)) {
            if (array_key_exists($key, $this->stubs[$name])) {
                $this->stubs[$name][$key]->set_value($counter, $value);
            }
        }
    }

    /**
     * Set a value for all stub instances of a type, or all instances.
     * @param string $type
     * @param string $counter
     * @param mixed $value
     * @param array $filter, allow restriction of effect to those stubs who match the filter.
     */
    private function set_stubs($type, $counter, $value, $filter = []) {

        if (is_null($type)) {
            foreach ($this->stubs as $stubtype => $stubs) {
                foreach ($stubs as $key => $stub) {

                    if (!empty($filter)) {
                        foreach($filter as $dim => $value) {
                            if ($stub->has_dim($dim)) {
                                if (!($stub->get_dim($dim) == $value)) {
                                    continue 2;
                                }
                            }
                        }
                    }

                    $stub->set_value($counter, $value);
                }
            }
        } else {
            if (array_key_exists($type, $this->stubs)) {
                foreach (array_values($this->stubs[$type]) as $stub) {

                    if (!empty($filter)) {
                        foreach($filter as $dim => $value) {
                            if ($stub->has_dim($dim)) {
                                if (!($stub->get_dim($dim) == $value)) {
                                    continue 2;
                                }
                            }
                        }
                    }
                    $stub->set_value($counter, $value);
                }
            }
        }
    }

    /**
     * Get counter value in a stub. Assumes the stub object has been initilized.
     * @param array $stub a stat aggregation stub.
     * @param array $counter the counter field.
     * @param array $dimensions dimensions name and values in order top to down.
     * @return counter value.
     */
    private function get_stub($name, $dims, $counter, $distinct = 0) {

        $key = stub::forge_key($dims, $distinct);

        if (array_key_exists($name, $this->stubs)) {
            if (array_key_exists($key, $this->stubs[$name])) {
                return $this->stubs[$name][$key]->get_value($counter);
            }
        }
    }

    /**
     * Defines compilation stubs with their dimensions.
     * This is done for each state, in order to create new context stubs.
     * @param object $attempt
     * @param object $theblock
     * @param int $groupid
     * @param int $parent parent category
     * @param int $question specific question context for each state
     * @param bool $isexam compilation set.
     */
    private function define_stubs($attempt, $theblock, $groupid, $parent, $question, $isexam = 0) {
        static $counter = 0;

        // Initialize question attempts counters per compiled dimensions.
        $name = 'qattempts';
        $desc = 'per attempt. non unique question';
        $dims = [
            'course' => $attempt->courseid,
            'blockid' => $theblock->instance->id,
            'groupid' => $groupid,
            'userid' => $attempt->userid,
            'uniqueid' => $attempt->uniqueid,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims, 0);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'bytopcat';
        $desc = 'per category, for all users. non unique question';
        $dims = [
            'categoryid' => $parent,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'bytopcatperinstance';
        $desc = 'per category and course, for all users. non unique question';
        $dims = $dims = [
            'course' => $attempt->courseid,
            'blockid' => $theblock->instance->id,
            'categoryid' => $parent,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'bytopcatperinstancedistinct';
        $desc = 'per category and course, for all users. unique question';
        $dims = $dims = [
            'course' => $attempt->courseid,
            'blockid' => $theblock->instance->id,
            'categoryid' => $parent,
            'isexam' => $isexam,
            'qdistinct' => 1
        ];
        $stub = stub::instance($name, $desc, $dims, 1);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'bytopcatperattempt';
        $desc = 'per category and attempt, for each users. non unique question';
        $dims = [
            'course' => $attempt->courseid,
            'blockid' => $theblock->instance->id,
            'groupid' => $groupid,
            'userid' => $attempt->userid,
            'categoryid' => $parent,
            'uniqueid' => $attempt->uniqueid,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'bytopcatperattemptdistinct';
        $desc = 'per category and course, for each users. unique question';
        $dims = [
            'course' => $attempt->courseid,
            'blockid' => $theblock->instance->id,
            'groupid' => $groupid,
            'userid' => $attempt->userid,
            'categoryid' => $parent,
            'uniqueid' => $attempt->uniqueid,
            'isexam' => $isexam,
            'qdistinct' => 1
        ];
        $stub = stub::instance($name, $desc, $dims, 1);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'bytopcatperuseringroup';
        $desc= 'per category for each user. non unique question';
        $dims = [
            'groupid' => $groupid,
            'userid' => $attempt->userid,
            'categoryid' => $parent,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'byquestion';
        $desc = 'per question for all users and courses. unique question';
        $dims = [
            'categoryid' => $parent,
            'questionid' => $question->id,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'byquestionperinstance';
        $desc = 'per question for all users per course. unique question per course';
        $dims = [
            'course' => $attempt->courseid,
            'blockid' => $theblock->instance->id,
            'categoryid' => $parent,
            'questionid' => $question->id,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'byquestionperuser';
        $desc = 'per question for each user all courses. non unique question';
        $dims = [
            'userid' => $attempt->userid,
            'categoryid' => $parent,
            'questionid' => $question->id,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'bytraininginstance';
        $desc = 'per training instance, for all users. non unique question';
        $dims = [
            'course' => $attempt->courseid,
            'blockid' => $theblock->instance->id,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'bytraininginstancedistinct';
        $desc = 'per training instance all users. unique question';
        $dims = [
            'course' => $attempt->courseid,
            'blockid' => $theblock->instance->id,
            'isexam' => $isexam,
            'qdistinct' => 1
        ];
        $stub = stub::instance($name, $desc, $dims, 1);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'byuser';
        $desc = 'all training instances in any course. non unique question';
        $dims = [
            'userid' => $attempt->userid,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims);
        $this->stubs[$name][$stub->get_key()] = $stub;

        $name = 'byinstancecoursegroup';
        $desc = 'per training instance (course and block) and group. non unique question';
        $dims = [
            'course' => $attempt->courseid,
            'blockid' => $theblock->instance->id,
            'groupid' => $groupid,
            'isexam' => $isexam,
            'qdistinct' => 0
        ];
        $stub = stub::instance($name, $desc, $dims);
        $this->stubs[$name][$stub->get_key()] = $stub;

    }


    /**
     * Save aall stubs to DB
     */
    private function save_stubs() {
        if (!empty($this->stubs)) {
            foreach (array_values($this->stubs) as $stubs) {
                foreach (array_values($stubs) as $stub) {
                    $stub->save();
                }
            }
        }
    }


    /**
     * Mark an attempt has been compiled in "attempt" scope record.
     * Attempt scope record has only course, blockid and uniqueid dimensions set. All other are nulled.
     * @param array $dimensions a keyed array of expected non nulled dimensions
     */
    private function mark_attempt_in_db($stubtype, $dimensions, $attemptdate) {
        global $DB;

        $key = stub::forge_key($dimensions);

        if (!array_key_exists($stubtype, $this->stubs)) {
            throw new coding_exception("Stub type $stubtype not initialized. ");
        }

        if (!array_key_exists($key, $this->stubs[$stubtype])) {
            throw new coding_exception("Missing key $key in stub type $stubtype. May be caused by mismatched dimensions ");
        }

        list($select, $params) = $this->stubs[$stubtype][$key]->get_dimension_select_sql($dimensions);
        $DB->set_field_select('report_examtraining', 'datecompiled', time(), $select, $params);
        $DB->set_field_select('report_examtraining', 'attemptdate', $attemptdate, $select, $params);
    }

    /**
     * Prepare SQL snippets and data for $from and $to.
     */
    protected function prepare_from_to_range($from, $to, &$params) {
        $fromclause = '';
        if (!is_null($from)) {
            $fromclause = ' AND attemptdate >= ? ';
            $params[] = $from;
        }

        if (!is_null($to)) {
            if ($to == 0) {
                $to = time();
            }
            $toclause = ' AND attemptdate <= ? ';
            $params[] = $to;
        }

        return [$fromclause, $toclause];
    }

    /**
     * Get best fit group to attach to user.
     * Heuristic is :
     * 1. If no group, return 0
     * 2. If only one group. Take it.
     * 3. If multiple groups, take first with idnumber set (and not escaped)
     * 4. Take first group by id with group name not lead underscored (escaped).
     */
     protected function get_user_group($courseid, $userid) {
        global $DB;
        static $USERGROUPS = [];

        // Cache some results for the same userid.
        if (array_key_exists($userid, $USERGROUPS)) {
            return $USERGROUPS[$userid];
        }

        $sql = "
            SELECT
                gm.groupid,
                g.idnumber,
                g.name
            FROM
                {groups} g,
                {groups_members} gm
            WHERE
                gm.groupid = g.id AND
                g.courseid = ? AND
                gm.userid = ?
            ORDER BY
                gm.timeadded
        ";
        $groups = $DB->get_records_sql($sql, [$courseid, $userid]);

        $groupid = 0;
        $idnumberedgroupid = 0;
        if (!empty($groups)) {
            // We scan groups and get last "groupid" and "idnumbreredgroupid".
            // If idnumbreredgroupid exists, take it.
            foreach ($groups as $g) {
                if (strstr($g->name, '_') === 0) {
                    // discard escaped groups by a leading underscore.
                    continue;
                }
                $groupid = $g->groupid;
                if (!empty($g->idnumber)) {
                    // take first with idnumber
                    $idnumberedgroupid = $g->groupid;
                }
            }
            if ($idnumberedgroupid) {
                // idnumbrered groupid superseeds non idnumbrerd if there is any.
                $groupid = $idnumberedgroupid;
            }
        }

        $USERGROUPS[$userid] = $groupid; // feed cache.

        return $groupid;
     }
}