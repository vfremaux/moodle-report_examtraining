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
namespace report_examtraining\output;

defined('MOODLE_INTERNAL') || die();

class raw_renderer extends \plugin_renderer_base {

    /**
     * a raster for printing in raw format
     * with all the relevant data about a user.
     * @param int $userid the reported user id
     * @param int $courseid the current course
     * @param array &$data the global results
     * @param int $from from timestamp
     * @param int $to to timestamp
     */
    public function globalheader($userid, $courseid, &$data, $from, $to) {
        global $CFG, $COURSE, $DB;
        static $dobfieldid = 0;
        static $pobfieldid = 0;
        static $c3fieldid = 0;

        $loginfo = examtraining_get_log_reader_info();
        $compiler = new \report_examtraining\stats\compiler();

        if ($dobfieldid == 0) {
            $dobfieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'AMFDOB'));
        }
        if ($pobfieldid == 0) {
            $pobfieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'AMFPOB'));
        }

        if ($c3fieldid == 0) {
            $c3fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'c3'));
        }

        $examcontext = block_userquiz_monitor_get_block($courseid)->config;

        $user = $DB->get_record('user', ['id' => $userid]);
        if ($courseid != $COURSE->id) {
            $course = $DB->get_record('course', ['id' => $courseid]);
        } else {
            $course = &$COURSE;
        }

        $resultset = [];

        $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

        if (!empty($usergroups)) {
            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == groups_get_course_group($course)) {
                    $str = "$str";
                }
                $groupnames[] = $str;
            }
            $resultset[] = implode(', ', $groupnames); // Entity.
        } else {
            $resultset[] = "Hors groupe"; // Entity.
        }

        $resultset[] = $user->id; // Userid.
        $resultset[] = $user->username; // Username.

        // Lastname
        /*
        $namestr = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->lastname))), 'ISO-8859-1', 'UTF-8');
        $namestr = mb_ereg_replace('/é|è|ë|ê/', 'E', $namestr);
        $namestr = mb_ereg_replace('/ä|a/', 'A', $namestr);
        $namestr = mb_ereg_replace('/ç/', 'C', $namestr);
        $namestr = mb_ereg_replace('/ü|ù|/', 'U', $namestr);
        $namestr = mb_ereg_replace('/î/', 'I', $namestr);
        */
        $namestr = $user->lastname;
        $resultset[] = $namestr;

        // Firstname
        /*
        $namestr = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->firstname))), 'ISO-8859-1', 'UTF-8');
        $namestr = mb_ereg_replace('/é|è|ë|ê/', 'E', $namestr);
        $namestr = mb_ereg_replace('/ä|a/', 'A', $namestr);
        $namestr = mb_ereg_replace('/ç/', 'C', $namestr);
        $namestr = mb_ereg_replace('/ü|ù|/', 'U', $namestr);
        $namestr = mb_ereg_replace('/î/', 'I', $namestr);
        */
        $namestr = $user->firstname;
        $resultset[] = $namestr;

        // Email
        $resultset[] = $user->email;

        $sql = "
            SELECT
                MIN(timestart) first
            FROM
                {user_enrolments} ue,
                {enrol} e
            WHERE
                ue.enrolid = e.id AND
                ue.timestart != 0 AND
                userid = ?
        ";

        // First enrol date
        $firstenroll = $DB->get_field_sql($sql, array($user->id));
        $resultset[] = ($firstenroll) ? date('d/m/Y', $firstenroll) : '';

        // First login date
        $select = " userid = ? AND action = '".$loginfo->loggedin."' ";
        $firstlogin = $DB->get_field_select($loginfo->table, 'MIN('.$loginfo->timeparam.')', $select, array($user->id));
        $resultset[] = ($firstlogin) ? date('d/m/Y', $firstlogin) : ''; // Firstlogin.

        // Last login date
        $select = " userid = ? AND action = '".$loginfo->loggedin."' ";
        $lastlogin = $DB->get_field_select($loginfo->table, 'MAX('.$loginfo->timeparam.')', $select, array($user->id));
        $resultset[] = ($lastlogin) ? date('d/m/Y', $lastlogin) : ''; // Firstlogin.

        // Report period start
        $resultset[] = date('d/m/Y', $from); // From date.

        // Report period end
        $resultset[] = date('d/m/Y', $to); // To date.

        // Week Report period start
        $resultset[] = date('d/m/Y', $to - DAYSECS * 7); // Last week of period.

        // Elapsed in course
        $resultset[] = examtraining_raw_format_duration(@$data->elapsed); // Elapsed time.

        // Elapsed in course in last week
        $resultset[] = examtraining_raw_format_duration(@$data->weekelapsed); // Elapsed time this week.

        // Userquiz monitor quiz results.
        // Try using a direct method less optimized
        $trainingstats = $compiler->get_user_globals($userid, $course->id, $from, $to);
        $weektrainingstats = $compiler->get_user_globals($userid, $course->id, $to - DAYSECS * 7, $to);

        // Block direct alternative : 
        /*
        require_once($CFG->dirroot.'/report/examtraining/quickfixlib.php');
        $trainingstats[$userid] = report_examtraining_get_user_block_results($examcontext, $userid);
        $weektrainingstats[$userid] = report_examtraining_get_user_block_results($examcontext, $userid, true);
        */

        $resultset[] = 0 + @$trainingstats[$userid]->acount; // Answered A questions on training.
        $resultset[] = 0 + @$weektrainingstats[$userid]->acount; // Answered A questions on training this week.

        $resultset[] = 0 + @$trainingstats[$userid]->ccount; // Answered C questions on training.
        $resultset[] = 0 + @$weektrainingstats[$userid]->ccount; // Answered C questions on training this week.

        $resultset[] = ((0 + @$trainingstats[$userid]->aratio)).' %'; // Ratio A.
        $resultset[] = ((0 + @$weektrainingstats[$userid]->aratio)).' %'; // Ratio A.

        $resultset[] = ((0 + @$trainingstats[$userid]->cratio)).' %'; // Ratio C.
        $resultset[] = ((0 + @$weektrainingstats[$userid]->cratio)).' %'; // Ratio C.

        // $select = " userid = $userid AND blockid = {$examcontext->instanceid} AND attemptid = 0 ";
        // $resultset[] = 0 + get_field_select('userquiz_monitor_user_stats', 'coverageseen', $select).' %'; // knowledge covering
        // $resultset[] = 0 + get_field_select('userquiz_monitor_user_stats', 'coveragematched', $select).' %'; // knowledge covering

        $examstats = $compiler->get_user_globals($userid, $course->id, $from, $to, 0, 'exam');

        $matchedexams = 0;
        if ($stats = $compiler->get_attempts_stats($userid, $course->id, $from, $to, 0, 'exam')) {
            foreach ($stats as $attemptid => $attemptres) {
                if (($attemptres->aratio * 100 >= $examcontext->rateAserie) &&
                        ($attemptres->cratio * 100 >= $examcontext->rateCserie)) {
                    $matchedexams++;
                }
            }
        }

        $resultset[] = 0 + $matchedexams; // Succeeded exam attempts.
        $resultset[] = 0 + @$examstats[$userid]->attempts; // Exam attempts.

        $modules = $compiler->get_attempts_per_size($userid, $from, $to);

        for ($i = 1; $i < 10; $i++) {
            $modules[$i] = 0 + @$modules[$i];
        }

        for ($i = 1; $i <= 10; $i++) {
            $modules[$i * 10] = 0 + @$modules[$i * 10];
        }

        ksort($modules);
        foreach ($modules as $qcount => $modulecont) {
            $resultset[] = ($modulecont) ? $modulecont : ''; // Succeeded exam attempts.
        }

        // Get special fields.
        // Date of birth.
        if ($dobfieldid) {
            $dob = $DB->get_field('user_info_data', 'data', array('fieldid' => $dobfieldid, 'userid' => $user->id));
            $resultset[] = $dob;
        } else {
            $resultset[] = '[N.C.]';
        }

        // Place of birth.
        if ($pobfieldid) {
            $pob = $DB->get_field('user_info_data', 'data', array('fieldid' => $pobfieldid, 'userid' => $user->id));
            $resultset[] = $pob;
        } else {
            $resultset[] = '[N.C.]';
        }

        // C3 completion state.
        if ($c3fieldid) {
            $c3 = $DB->get_field('user_info_data', 'data', array('fieldid' => $c3fieldid, 'userid' => $user->id));
            $resultset[] = ($c3) ? 'Certified' : '';
        } else {
            $resultset[] = '[N.C.]';
        }

        return mb_convert_encoding(implode(';', $resultset), 'ISO-8859-1', 'UTF-8')."\n";
    }
}