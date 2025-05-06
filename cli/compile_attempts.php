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
 * @package    report_examtraining
 * @category   report
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
        'attemptid'    => false,
        'uniqueid'    => false,
        'backwards'    => false,
        'clearall'    => false,
        'set'         => false,
        'output'    => false,
        'dryrun'    => false,
    ),
    array(
        'h' => 'help',
        'H' => 'host',
        'c' => 'courseid',
        'a' => 'attemptid',
        'u' => 'uniqueid',
        'b' => 'backwards',
        'C' => 'clearall',
        's' => 'set',
        'o' => 'output',
        'd' => 'dryrun',
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
    -h, --help       Print out this help
    -H, --host       The host, in case of a virtual vmoodle host
    -c, --courseid   the course id as context
    -b, --backwards  compile ordering from last attempt date backwards
    -a, --attemptid  one single attempt, for test purpose
    -u, --uniqueid  one single attempt, by uniqueid, for test purpose
    -C, --clearall   clear all stats, or only courseid stats if provided.
    -s, --set       'training', 'exam' or 'all'
    -o, --output    more output
    -d, --dryrun    Allow outputing com^pilation results without affecting the DB

    Examples:
    \$sudo -u www-data /usr/bin/php report/examtraining/cli/compile_attempts.php --courseid=109 --verbose
    \$sudo -u www-data /usr/bin/php report/examtraining/cli/compile_attempts.php --attemptid=110243 --verbose
    \$sudo -u www-data /usr/bin/php report/examtraining/cli/compile_attempts.php --clearall
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

require_once($CFG->dirroot.'/report/examtraining/statscompilelib.php');
$compiler = new \report_examtraining\stats\compiler();

$courseids = [];
if (!empty($options['courseid'])) {
    $courseid = $options['courseid'];
    if (!is_numeric($courseid)) {
        die("Course id required but not given as number. Given as : $courseid\n");
    }
    if (!$DB->get_record('course', ['id' => $courseid])) {
        die("Course does not exist with this id\n");
    }

    $coursecontext = context_course::instance($courseid);
    $uqms = $DB->get_records('block_instances', ['parentcontextid' => $coursecontext->id, 'blockname' => 'userquiz_monitor']);
    if (empty($uqms)) {
        die("This course has no userquiz_monitor instance to compile\n");
    }

    $courseids[] = $courseid;
} else {

    if (!empty($options['attemptid']) || !empty($options['uniqueid'])) {
        $attemptid = $options['attemptid'];
        $uniqueid = $options['uniqueid'];
        if ($uniqueid) {
            echo "Compiling single attempt (uniqueid) $uniqueid...\n";
        } else {
            echo "Compiling single attempt $attemptid...\n";
        }
        $compiler->precompile_results(0, $options, 'all');
        echo "Done.\n";
        exit(0);
    }

    // Get courses to process by userquiz_monitor instances.
    $uqms = $DB->get_records('block_instances', ['blockname' => 'userquiz_monitor']);
    if (empty($uqms)) {
        die("No userquiz_monitor instances to compile in this moodle\n");
    }

    foreach ($uqms as $uqm) {
        $context = $DB->get_record('context', ['id' => $uqm->parentcontextid]);
        $courseids[] = $context->instanceid;
    }
}

if (empty($options['set'])) {
    $options['set'] = 'all';
}

if (!empty($options['clearall'])) {
    // course level clear not implemented yet.
    $compiler->clear_results($options['courseid'] ?? 0);
    echo "Examtraining data cleared\n";
    die;
}

foreach ($courseids as $courseid) {

    if (!empty($options['output'])) {
        echo "Compiling course $courseid...\n";
    }

    if ($options['set'] == 'all' or $options['set'] == 'training') {
        if (!empty($options['output'])) {
            echo "Compiling trainings for course $courseid...\n";
        }
        $compiler->precompile_results($courseid, $options, 'training');
    }

    if ($options['set'] == 'all' or $options['set'] == 'exam') {
        if (!empty($options['output'])) {
            echo "Compiling exams for course $courseid...\n";
        }
        $compiler->precompile_exams($courseid, $options);
    }
}

echo "Done.\n";
exit(0);