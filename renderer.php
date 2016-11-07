<?php

defined('MOODLE_INTERNAL') || die();

class report_examtraining_renderer extends plugin_renderer_base {

    /**
    * a raster for html printing of a report structure.
    *
    * @param string ref $str a buffer for accumulating output
    * @param object $structure a course structure object.
    */
    function trainings_globals($userid, $from, $to, $height = 'large', &$stats) {
        global $CFG;

        $exam_context = examtraining_get_context();

        if (is_null($stats)){
            $stats = userquiz_get_user_globals($userid, $exam_context->testquizzes, $from, $to);
        }

        // Remote meeting barchen 03/05/2011
        // print_heading(get_string('overalhitstraining', 'report_examtraining'));

        jqplot_print_horiz_bar_headgraph($stats[$userid], get_string('overalhitstraining', 'report_examtraining'), 'overalhitstraining', $height);

        // $ratiostr = get_string('ratio', 'report_examtraining');
        $Aratiostr = get_string('ratioA', 'report_examtraining');
        $Cratiostr = get_string('ratioC', 'report_examtraining');
        $Acountstr = get_string('countA', 'report_examtraining');
        $Ccountstr = get_string('countC', 'report_examtraining');
        $table->head = array("<b>$Aratiostr</b>", "<b>$Cratiostr</b>", "<b>$Acountstr</b>", "<b>$Ccountstr</b>");
        $table->size = array('25%', '25%', '25%', '25%');
        $table->width = '90%';
        $table->align = array('center', 'center', 'center', 'center');
        $table->data[] = array((@$stats[$userid]->ahitratio + 0).' %', (@$stats[$userid]->chitratio + 0).' %', @$stats[$userid]->aanswered + 0, @$stats[$userid]->canswered + 0);

        return html_writer::table($table);
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    function trainings($userid, $from, $to){
        global $CFG, $OUTPUT;

        $exam_context = examtraining_get_context();
        $subcats = $DB->get_records('question_categories', array('parent' => $exam_context->rootcategory), 'id, name', 'sortorder');

        $str = '';

        if (!$stats = userquiz_get_weekly_globals($userid, $exam_context->testquizzes, $from, $to)) {
            $str .= $OUTPUT->heading(get_string('traininghits', 'report_examtraining'));
            $str .= get_string('notrainingactivity', 'report_examtraining');
            return $str;
        }

        // this prints an histogram on training result categories
        $str = '<center>';

        // preformat data
        $statsrawarr = array_values($stats);
        foreach ($statsrawarr as $stat) {
            $firstdayinyear = mktime(0, 0, 0, 1, 1, $statsrawarr[0]->year);
            $statdate = ($firstdayinyear + $stat->week * 7 * DAYSECS) * 1000;
            $data[0][] = $statdate;
            $data[1][] = $exam_context->rateAserie;
            $data[2][] = $exam_context->rateCserie;
            // $data[3][] = $stat->hitratio * 100;
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
        $str .= jqplot_print_timecurve_graph($data, get_string('globalprogress', 'report_examtraining'), 'globalprogress', $labels, true);
        $str .= "</center>";

        return $str;
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    function exams_summary($userid, $from, $to) {
        global $CFG;

        // print_heading(get_string('examtries', 'report_examtraining'));
        $exam_context = examtraining_get_context();

        if (!$stats = userquiz_get_user_globals($userid, $exam_context->examquizzes, $from, $to)){
            return "no records";
        }
        return jqplot_print_horiz_bar_headgraph($stats, get_string('examtries', 'report_examtraining'), 'examtries', true);
    }

    function pager($maxobjects, $offset, $page, $url) {
        global $CFG;

        if ($maxobjects <= $page) {
            return '';
        }

        $str = '';

        $current = ceil(($offset + 1) / $page);
        $pages = array();
        $off = 0;

        for ($p = 1 ; $p <= ceil($maxobjects / $page) ; $p++) {
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
    function exams($userid, $from, $to) {
        global $CFG, $COURSE;

        $exam_context = examtraining_get_context();
        $context = context_course::instance($COURSE->id);

        if (!$stats = userquiz_get_attempts_stats($userid, $exam_context->examquiz, $from, $to)) {
            // Remote meeting 03/05/2011 => keep silent here
            // print_heading(get_string('examtries', 'report_examtraining'));
            // print_string('noexamtries', 'report_examtraining');
            // echo '<br/>';
            // echo '<br/>';
            return;
        }

        // jqplot_print_vert_bar_headgraph($stats, get_string('examtries', 'report_examtraining'), 'examtries');

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
            $examurl = new moodle_url('/report/examtraining/index.php', array('id' => $COURSE->id, 'attemptid' => $attemptid, 'view' => 'userattempt'));
            if ($attemptres->ahitratio < 0.85 || $attemptres->chitratio < 0.75) {
                if (has_capability('report/examtraining:viewall', $context)) {
                    $str .= '<td class="examresults" align="center">'.$finishdate.'<br/><a href="'.$examurl.'"><img src="'.$OUTPUT->pix_url('bad', 'report_examtraining').'" style="margin-right:15px" /><br/>(A : '.($attemptres->ahitratio * 100).'%, C: '.($attemptres->chitratio * 100).'%)</a></td>';
                } else {
                    $str .= '<td class="examresults" align="center">'.$finishdate.'<br/><img src="'.$OUTPUT->pix_url('bad', 'report_examtraining').'" style="margin-right:15px" /><br/>(A : '.($attemptres->ahitratio * 100).'%, C: '.($attemptres->chitratio * 100).'%)</td>';
                }
            } else {
                if (has_capability('report/examtraining:viewall', $context)) {
                    $str .= '<td class="examresults" align="center">'.$finishdate.'<br/><a href="'.$examurl.'"><img src="'.$OUTPUT->pix_url('good', 'report_examtraining').'" style=\"margin-right:15px\" /><br/>(A : '.($attemptres->ahitratio * 100).'%, C: '.($attemptres->chitratio * 100).'%)</a></td>';
                } else {
                    $str .= '<td class="examresults" align="center">'.$finishdate.'<br/><img src="'.$OUTPUT->pix_url('good', 'report_examtraining').'" style="margin-right:15px" /><br/>(A : '.($attemptres->ahitratio * 100).'%, C: '.($attemptres->chitratio * 100).'%)</td>';
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
     *
     */
    function globalheader($userid, $courseid, &$data) {
        global $CFG, $COURSE, $DB, $OUTPUT;

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

        // print group status
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

        // Start printing the overall times

        $str .= '<br/>';
        $str .= get_string('equlearningtime', 'report_examtraining');
        $str .= examtraining_reports_format_time(0 + @$data->elapsed, 'html');

        // plug here specific details

        $str .= '</p></div></center>';

        return $str;
    }

    /**
     * prints a jqplot graph using 
     *
     */
    function coverage_vs_ratio(&$users, $courseid, $from, $to, $hits) {
        global $DB;

        $userids = implode("','", array_keys($users));

        $exam_context = examtraining_get_context();

        $coverage = $DB->get_records('userquiz_monitor_user_stats', array('blockid' => $exam_context->instanceid), 'userid', 'userid, coverageseen, coveragematched');
        $data = array();

        foreach ($users as $user) {
            $data[0][] = @$coverage[$user->id]->coveragematched;
            $data[1][] = (0 + @$hits[$user->id]->hitratio);
            $data[2][] = fullname($user);
        }

        jqplot_print_labelled_graph($data, get_string('grouplocation', 'report_examtraining'), 'examtries');
    }
}