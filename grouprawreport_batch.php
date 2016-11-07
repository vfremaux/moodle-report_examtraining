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

/**
 * This script handles the report generation in batch task for a single group. 
 * It may produce a group csv report.
 * groupid must be provided. 
 * This script should be sheduled in a redirect bouncing process for maintaining
 * memory level available for huge batches. 
 */
require('../../../config.php');
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$maxbatchduration = 4 * HOURSECS;

$id = required_param('id', PARAM_INT) ; // the course id
$from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying from when for XML generation

ini_set('memory_limit', '256M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);

// TODO : secure groupid access depending on proper capabilities

// calculate start time. Defaults ranges to "last week".

if ($from == -1) { // maybe we get it from parameters
    $from = time() - 7 * DAYSECS;
}

if ($to == -1) { // maybe we get it from parameters
    $to = time();
}

// compute target group

$groups = groups_get_all_groups($id);

$timesession = time();

$testmax = 5;
$i = 0;

foreach ($groups as $group) {

    // for unit test only
    // if ($i > $testmax) continue;
    $i++;

    $targetusers = groups_get_members($group->id);

    // filters teachers out
    foreach ($targetusers as $uid => $user) {
        if (has_capability('report/examtraining:isteacher', $context, $user->id)) {
            unset($targetusers[$uid]);
        }

        // check user for beeing certified
        $c3field = $DB->get_record('user_info_field', array('shortname' => 'C3'));
        if ($c3certified = $DB->get_field('user_info_data', 'data', array('userid' => $uid, 'fieldid' => $c3field->id))) {
            unset($targetusers[$uid]);
        }
    }

    if (!empty($targetusers)) {
        $current = time();
        if ($current > $timesession + $maxbatchduration) {
            die("Could not finish batch. Too long");
        }

        mtrace('compile_users for group: '.$group->name.'\n');

        $uri = new moodle_url('/report/examtraining/grouprawreport_batch_task.php');

        $rqfields = array();
        $rqfields[] = 'id='.$id;
        $rqfields[] = 'from='.$from;
        $rqfields[] = 'to='.$to;
        $rqfields[] = 'groupid='.$group->id;
        $rqfields[] = 'timesession='.$timesession;

        $rq = implode('&', $rqfields);

        $ch = curl_init($uri.'?'.$rq);

        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle Report Batch');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rq);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $res = xmlrpc_decode(curl_exec($ch));

        // check for curl errors
        $curlerrno = curl_errno($ch);
        if ($curlerrno != 0) {
            debugging("Request for $uri failed with curl error $curlerrno");
        } 
    
        // check HTTP error code
        $info = curl_getinfo($ch);
        if (!empty($info['http_code']) && ($info['http_code'] != 200)) {
            debugging("Request for $uri failed with HTTP code ".$info['http_code']);
        }

        curl_close($ch);

    } else {
        mtrace('no more certifiable users in this group: '.$group->name);
    }
}

