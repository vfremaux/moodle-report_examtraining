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

class report_examtraining_raw_renderer extends plugin_renderer_base {

    /**
     * a raster for printing in raw format
     * with all the relevant data about a user.
     */
    public function globalheader_raw($userid, $courseid, &$data, $from, $to) {
        global $CFG, $COURSE, $DB;
        static $dobfieldid = 0;
        static $pobfieldid = 0;
        static $c3fieldid = 0;

        $loginfo = examtraining_get_log_reader_info();

        if ($dobfieldid == 0) {
            $dobfieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'AMFDOB'));
        }
        if ($pobfieldid == 0) {
            $pobfieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'AMFPOB'));
        }

        if ($c3fieldid == 0) {
            $c3fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'c3'));
        }

        $examcontext = examtraining_get_context($courseid);

        $user = $DB->get_record('user', array('id' => $userid));
        if ($courseid != $COURSE->id) {
            $course = $DB->get_record('course', array('id' => $courseid));
        } else {
            $course = &$COURSE;
        }

        $resultset = array();
        $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

        if (!empty($usergroups)) {
            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == groups_get_course_group($courseid)) {
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

        $firstenroll = $DB->get_field_sql($sql, array($user->id));
        $resultset[] = ($firstenroll) ? date('d/m/Y', $firstenroll) : ''; // From date.
        $select = " userid = ? AND action = '".$loginfo->loggedin."' ";
        $firstlogin = $DB->get_field_select($loginfo->table, 'MIN('.$loginfo->timeparam.')', $select, array($user->id));
        $resultset[] = ($firstlogin) ? date('d/m/Y', $firstlogin) : ''; // Firstlogin.
        $select = " userid = ? AND action = '".$loginfo->loggedin."' ";
        $lastlogin = $DB->get_field_select($loginfo->table, 'MAX('.$loginfo->timeparam.')', $select, array($user->id));
        $resultset[] = ($lastlogin) ? date('d/m/Y', $lastlogin) : ''; // Firstlogin.
        $resultset[] = date('d/m/Y', $from); // From date.
        $resultset[] = date('d/m/Y', $to); // To date.
        $resultset[] = date('d/m/Y', $to - DAYSECS * 7); // Last week of period.
        $namestr = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->lastname))), 'ISO-8859-1', 'UTF-8');
        $namestr = mb_ereg_replace('/é|è|ë|ê/', 'E', $namestr);
        $namestr = mb_ereg_replace('/ä|a/', 'A', $namestr);
        $namestr = mb_ereg_replace('/ç/', 'C', $namestr);
        $namestr = mb_ereg_replace('/ü|ù|/', 'U', $namestr);
        $namestr = mb_ereg_replace('/î/', 'I', $namestr);
        $resultset[] = $namestr;
        $namestr = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->firstname))), 'ISO-8859-1', 'UTF-8');
        $namestr = mb_ereg_replace('/é|è|ë|ê/', 'E', $namestr);
        $namestr = mb_ereg_replace('/ä|a/', 'A', $namestr);
        $namestr = mb_ereg_replace('/ç/', 'C', $namestr);
        $namestr = mb_ereg_replace('/ü|ù|/', 'U', $namestr);
        $namestr = mb_ereg_replace('/î/', 'I', $namestr);
        $resultset[] = $namestr;

        $resultset[] = $user->email;

        $resultset[] = raw_format_duration(@$data->elapsed); // Elapsed time.
        $resultset[] = raw_format_duration(@$data->weekelapsed); // Elapsed time this week.

        $trainingstats = userquiz_get_user_globals($userid, $examcontext->trainingquiz, $from, $to);
        $weektrainingstats = userquiz_get_user_globals($userid, $examcontext->trainingquizzes, $to - DAYSECS * 7, $to);

        $resultset[] = 0 + @$trainingstats[$userid]->aanswered; // Answered A questions on training.
        $resultset[] = 0 + @$weektrainingstats[$userid]->aanswered; // Answered A questions on training this week.

        $resultset[] = 0 + @$trainingstats[$userid]->canswered; // Answered C questions on training.
        $resultset[] = 0 + @$weektrainingstats[$userid]->canswered; // Answered C questions on training this week.

        $resultset[] = ((0 + @$trainingstats[$userid]->ahitratio)).' %'; // Ratio A.
        $resultset[] = ((0 + @$weektrainingstats[$userid]->ahitratio)).' %'; // Ratio A.

        $resultset[] = ((0 + @$trainingstats[$userid]->chitratio)).' %'; // Ratio C.
        $resultset[] = ((0 + @$weektrainingstats[$userid]->chitratio)).' %'; // Ratio C.

        // $select = " userid = $userid AND blockid = {$examcontext->instanceid} AND attemptid = 0 ";
        // $resultset[] = 0 + get_field_select('userquiz_monitor_user_stats', 'coverageseen', $select).' %'; // knowledge covering
        // $resultset[] = 0 + get_field_select('userquiz_monitor_user_stats', 'coveragematched', $select).' %'; // knowledge covering

        $examstats = userquiz_get_user_globals($userid, $examcontext->examquiz, $from, $to);

        $matchedexams = 0;
        if ($stats = userquiz_get_attempts_stats($userid, $examcontext->examquiz, $from, $to)) {
            foreach ($stats as $attemptid => $attemptres) {
                if (($attemptres->ahitratio * 100 >= $examcontext->rateAserie) &&
                        ($attemptres->chitratio * 100 >= $examcontext->rateCserie)) {
                    $matchedexams++;
                }
            }
        }

        $resultset[] = 0 + $matchedexams; // Succeeded exam attempts.
        $resultset[] = 0 + @$examstats[$userid]->attempts; // Exam attempts.

        $modules = examtraining_get_module_count($userid, $from, $to);

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