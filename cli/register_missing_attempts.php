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
 * @package    report_trainingsessions
 * @category   report
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @subpackage  cli
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
 * This script is to be used from PHP command line and will create a set
 * of Virtual VMoodle automatically from a CSV nodelist description.
 * Template names can be used to feed initial data of new VMoodles.
 * The standard structure of the nodelist is given by the nodelist-dest.csv file.
 */

global $CLI_VMOODLE_PRECHECK;

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);
$CLI_VMOODLE_PRECHECK = true; // Force first config to be minimal.

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/lib/clilib.php'); // Cli only functions.

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'help'        => false,
        'host'        => false,
        'courseid'    => false,
    ),
    array(
        'h' => 'help',
        'H' => 'host',
        'c' => 'courseid',
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("$unrecognized is not a recognized option\n");
}

if ($options['help']) {
    $help = "Command line examtraing fixer.
    Registers all missing userquiz_monitor associated quizzes into examtraining reports for compilation.

    Options:
    -h, --help      Print out this help
    -H, --host      The host, in case of a virtual vmoodle host
    -c, --courseid  the course id as context

    Example:
    \$sudo -u www-data /usr/bin/php report/examtraining/cli/regoster_missing_attempts.php
"; // TODO: localize - to be translated later when everything is finished.

    echo $help;
    die;
}

if (!empty($options['host'])) {
    // Arms the vmoodle switching.
    echo('Arming for '.$options['host']."\n"); // Mtrace not yet available.
    define('CLI_VMOODLE_OVERRIDE', $options['host']);
}

// Replay full config whenever. If vmoodle switch is armed, will switch now config.

if (!defined('MOODLE_INTERNAL')) {
    include(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
    echo('Config check : playing for '.$CFG->wwwroot."\n");
}

if (!empty($options['courseid'])) {
    $courseid = $options['courseid'];
    if (empty($courseid)) {
        die("Course id required but not given\n");
    }
    if (!$DB->get_record('course', ['id' => $courseid])) {
        die("Course does not exist with this id\n");
    }

    $coursecontext = context_course::instance($courseid);
    $uqms = $DB->get_records('block_instances', ['parentcontextid' => $coursecontext->id, 'blockname' => 'userquiz_monitor']);
} else {
    $uqms = $DB->get_records('block_instances', ['blockname' => 'userquiz_monitor']);
}

$n = count($uqms);

if ($n == 0) {
    die("No userquiz monitors to process\n");
}

mtrace("Starting CLI examtraining registering for $n monitors\n");
foreach ($uqms as $uqm) {
    $block = block_instance('userquiz_monitor', $uqm);

    $collectquizzes = [];

    if (!empty($block->config->trainingquizzes)) {
        $collectquizzes = $collectquizzes + $block->config->trainingquizzes;
    }

    if (!empty($block->config->examquiz)) {
        $collectquizzes[] = $block->config->examquiz;
    }
}

if (empty($collectquizzes)) {
    die("No quiz collected un userquiz monitors\n");
}

foreach ($collectquizzes as $qid) {
    $attempts = $DB->get_records('quiz_attempts', ['quiz' => $qid]);
    if (empty($attempts)) {
        mtrace("Quiz : {$qid} : No attempts found.\n");
    } else {
        $n = count($attempts);
        mtrace("Quiz : {$qid} : Registering $n attempts.\n");
        foreach ($attempts as $a) {
            $params = ['uniqueid' => $a->uniqueid];
            if (!$DB->record_exists('report_examtraining', $params)) {
                $rec = new Stdclass;
                $rec->uniqueid = $a->uniqueid;
                $rec->qcount = $DB->count_records('quiz_slots', ['quizid' => $qid]);
                $rec->serieaanswered = 0;
                $rec->seriecanswered = 0;
                $rec->serieamatched = 0;
                $rec->seriecmatched = 0;
                $rec->datecompiled = 0;
                $DB->insert_record('report_examtraining', $rec);
            }
        }
    }
}
