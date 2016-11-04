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

class report_examtraining_renderer extends plugin_renderer_base {

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    public function trainings_globals($userid, $from, $to, $height = 'large', &$stats) {

        $examcontext = examtraining_get_context();

        if (is_null($stats)) {
            $stats = userquiz_get_user_globals($userid, $examcontext->testquizzes, $from, $to);
        }

        $label = get_string('overalhitstraining', 'report_examtraining');
        jqplot_print_horiz_bar_headgraph($stats[$userid], $label, 'overalhitstraining', $height);

        $aratiostr = get_string('ratioA', 'report_examtraining');
        $cratiostr = get_string('ratioC', 'report_examtraining');
        $acountstr = get_string('countA', 'report_examtraining');
        $ccountstr = get_string('countC', 'report_examtraining');
        $table->head = array("<b>$aratiostr</b>", "<b>$cratiostr</b>", "<b>$acountstr</b>", "<b>$ccountstr</b>");
        $table->size = array('25%', '25%', '25%', '25%');
        $table->width = '90%';
        $table->align = array('center', 'center', 'center', 'center');
        $table->data[] = array((@$stats[$userid]->ahitratio + 0).' %',
                               (@$stats[$userid]->chitratio + 0).' %',
                               @$stats[$userid]->aanswered + 0,
                               @$stats[$userid]->canswered + 0);

        return html_writer::table($table);
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    public function trainings($userid, $from, $to) {
        global $DB, $OUTPUT;

        $examcontext = examtraining_get_context();
        $params = array('parent' => $examcontext->rootcategory);
        $subcats = $DB->get_records('question_categories', $params, 'id, name', 'sortorder');

        $str = '';

        if (!$stats = userquiz_get_weekly_globals($userid, $examcontext->testquizzes, $from, $to)) {
            $str .= $OUTPUT->heading(get_string('traininghits', 'report_examtraining'));
            $str .= get_string('notrainingactivity', 'report_examtraining');
            return $str;
        }

        // This prints an histogram on training result categories.
        $str = '<center>';

        // Preformat data.
        $statsrawarr = array_values($stats);
        foreach ($statsrawarr as $stat) {
            $firstdayinyear = mktime(0, 0, 0, 1, 1, $statsrawarr[0]->year);
            $statdate = ($firstdayinyear + $stat->week * 7 * DAYSECS) * 1000;
            $data[0][] = $statdate;
            $data[1][] = $examcontext->rateAserie;
            $data[2][] = $examcontext->rateCserie;
            $data[3][] = $stat->ahitratio * 100;
            $data[4][] = $stat->chitratio * 100;
        }
        $labels = array(
            array(
                'label' => get_string('athreshold', 'report_examtraining'),
                'lineWidth' => 2,
                'color' => '#FF6666',
                'showMarker' => 'false'
            ),
            array(
                'label' => get_string('cthreshold', 'report_examtraining'),
                'lineWidth' => 2,
                'color' => '#6666FF',
                'showMarker' => 'false'
            ),
            array(
                'label' => get_string('ratioa', 'report_examtraining'),
                'lineWidth' => 3,
                'color' => '#E00000',
                'showMarker' => 'true'
            ),
            array(
                'label' => get_string('ratioc', 'report_examtraining'),
                'lineWidth' => 3,
                'color' => '#0000E0',
                'showMarker' => 'true'
            ),
        );
        $label = get_string('globalprogress', 'report_examtraining');
        $str .= jqplot_print_timecurve_graph($data, $label, 'globalprogress', $labels, true);
        $str .= "</center>";

        return $str;
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    public function exams_summary($userid, $from, $to) {

        $examcontext = examtraining_get_context();

        if (!$stats = userquiz_get_user_globals($userid, $examcontext->examquizzes, $from, $to)) {
            return "no records";
        }
        $label = get_string('examtries', 'report_examtraining');
        return jqplot_print_horiz_bar_headgraph($stats, $label, 'examtries', true);
    }

    public function pager($maxobjects, $offset, $page, $url) {

        if ($maxobjects <= $page) {
            return '';
        }

        $str = '';

        $current = ceil(($offset + 1) / $page);
        $pages = array();
        $off = 0;

        for ($p = 1; $p <= ceil($maxobjects / $page); $p++) {
            if ($p == $current) {
                $pages[] = '<u>'.$p.'</u>';
            } else {
                $pages[] = '<a class="pagelink" href="'.$url.'&offset='.$off.'">'.$p.'</a>';
            }
            $off = $off + $page;
        }

        $str .= "<center>";
        $str .= implode(' - ', $pages);
        $str .= "</center>";

        return $str;
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    public function exams($userid, $from, $to) {
        global $COURSE, $OUTPUT;

        $examcontext = examtraining_get_context();
        $context = context_course::instance($COURSE->id);

        if (!$stats = userquiz_get_attempts_stats($userid, $examcontext->examquiz, $from, $to)) {
            return;
        }

        $str = $OUTPUT->heading(get_string('examtries', 'report_examtraining'));
        $str .= '<table width="100%" style="border:1px solid #A0A0A0;padding:3px">';
        $str .= '<tr><td width="150" align="left"><b>';
        $str .= get_string('examtries', 'report_examtraining');
        $str .= '</b></td><td align="left">';

        ksort($stats);
        $i = 1;
        $previous = null;
        echo '<table class="examresults"><tr>';
        foreach ($stats as $attemptid => $attemptres) {
            $finishdate = date("d/m/y", $attemptres->timefinish);
            $params = array('id' => $COURSE->id, 'attemptid' => $attemptid, 'view' => 'userattempt');
            $examurl = new moodle_url('/report/examtraining/index.php', $params);
            if ($attemptres->ahitratio < 0.85 || $attemptres->chitratio < 0.75) {
                if (has_capability('report/examtraining:viewall', $context)) {
                    $link = '<a href="'.$examurl.'">';
                    $link .= '<img src="'.$OUTPUT->pix_url('bad', 'report_examtraining').'" style="margin-right:15px" /><br/>';
                    $link .= '(A : '.($attemptres->ahitratio * 100).'%, C: '.($attemptres->chitratio * 100).'%)</a>';
                    $str .= '<td class="examresults" align="center">'.$finishdate.'<br/>'.$link.'</td>';
                } else {
                    $img = '<img src="'.$OUTPUT->pix_url('bad', 'report_examtraining').'" style="margin-right:15px" /><br/>';
                    $img .= '(A : '.($attemptres->ahitratio * 100).'%, C: '.($attemptres->chitratio * 100).'%)';
                    $str .= '<td class="examresults" align="center">'.$finishdate.'<br/>'.$img.'</td>';
                }
            } else {
                if (has_capability('report/examtraining:viewall', $context)) {
                    $pixurl = $OUTPUT->pix_url('good', 'report_examtraining');
                    $link = '<a href="'.$examurl.'"><img src="'.$pixurl.'" style="margin-right:15px" /><br/>';
                    $link .= '(A : '.($attemptres->ahitratio * 100).'%, C: '.($attemptres->chitratio * 100).'%)</a>';
                    $str .= '<td class="examresults" align="center">'.$finishdate.'<br/>'.$link.'</td>';
                } else {
                    $img = '<img src="'.$OUTPUT->pix_url('good', 'report_examtraining').'" style="margin-right:15px" /><br/>';
                    $img .= '(A : '.($attemptres->ahitratio * 100).'%, C: '.($attemptres->chitratio * 100).'%)';
                    $str .= '<td class="examresults" align="center">'.$finishdate.'<br/>'.$img.'</td>';
                }
            }
        }
        $str .= '</tr></table>';

        $str .= '</td></tr></table><br/>';

        return $str;
    }

    /**
     * a raster for html printing of a report structure global header
     * with all the relevant data about a user.
     */
    public function globalheader($userid, $courseid, &$data) {
        global $COURSE, $DB, $OUTPUT;

        $user = $DB->get_record('user', array('id' => $userid));
        if ($COURSE->id != $courseid) {
            $course = $DB->get_record('course', array('id' => $courseid));
        } else {
            $course = &$COURSE;
        }

        $str = '';

        $str .= $OUTPUT->user($user, $course);

        $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

        $str .= '<center>';
        $str .= '<div style="width:80%;text-align:left;padding:3px;" class="userinfobox">';

        // Print group status.
        if (!empty($usergroups)) {
            $str .= get_string('groups');
            $str .= ' : ';
            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == groups_get_course_group($courseid)) {
                    $str = '<b>'.$str.'</b>';
                }
                $groupnames[] = $str;
            }
            $str .= implode(', ', $groupnames);
        }
        $context = context_course::instance($courseid);
        $str .= '<br/>';
        $str .= get_string('roles');
        $str .= ' : ';
        $str .= get_user_roles_in_context($userid, $context);

        $examreporturl = new moodle_url('/report/examtraining/index.php', array('view' => 'user', 'id' => $courseid, 'userid' => $userid));
        $str .= '<br/><a href="'.$examreporturl.'">'.get_string('seedetails', 'report_examtraining').'</a>';

        // Start printing the overall times.

        $str .= '<br/>';
        $str .= get_string('equlearningtime', 'report_examtraining');
        $str .= examtraining_reports_format_time(0 + @$data->elapsed, 'html');

        // Plug here specific details.

        $str .= '</p></div></center>';

        return $str;
    }

    /**
     * prints a jqplot graph using
     *
     */
    public function coverage_vs_ratio(&$users, $courseid, $from, $to, $hits) {
        global $DB;

        $userids = implode("','", array_keys($users));

        $examcontext = examtraining_get_context();

        $params = array('blockid' => $examcontext->instanceid);
        $coverage = $DB->get_records('userquiz_monitor_user_stats', $params, 'userid', 'userid, coverageseen, coveragematched');
        $data = array();

        foreach ($users as $user) {
            $data[0][] = @$coverage[$user->id]->coveragematched;
            $data[1][] = (0 + @$hits[$user->id]->hitratio);
            $data[2][] = fullname($user);
        }

        jqplot_print_labelled_graph($data, get_string('grouplocation', 'report_examtraining'), 'examtries');
    }

    public function time_selector_form() {

        $str = '';

        $str .= '<center>';
        $str .= '<form action="#" name="selector" method="get">';
        $str .= '<input type="hidden" name="id" value="'.$course->id.'" />';
        $str .= '<input type="hidden" name="fromstart" value="" />';
        $str .= '<table width="90%">';
        $str .= '<tr valign="top">';
        $str .= '<td align="right">';
        $str .= get_string('from');
        $str .= ' : ';
        $str .= print_date_selector('startday', 'startmonth', 'startyear', $from);
        $str .= '</td>';
        $str .= '<td align="left">';
        $str .= '<input type="submit" name="go_btn" value="'.get_string('update').'" />';
        $jshandler = 'document.forms[\'selector\'].fromstart.value = 1;document.forms[\'selector\'].submit();';
        $buttonlabel = get_string('updatefromcoursestart', 'report_trainingsessions');
        $str .= '&nbsp;<input type="button" name="gostart_btn" value="'.$buttonlabel.'" onclick="'.$jshandler.'" />';
        $str .= '</td>';
        $str .= '</tr>';
        $str .= '</table>';
        $str .= '</form>';
        $str .= '</center>';

        return $str;
    }
}