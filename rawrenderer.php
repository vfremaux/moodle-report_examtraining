<?php

class report_examtraining_raw_renderer extends plugin_renderer_base {

    /**
     * a raster for printing in raw format 
     * with all the relevant data about a user.
     *
     */
    function globalheader_raw($userid, $courseid, &$data, $from, $to) {
        global $CFG, $COURSE, $DB;
        static $DOBfieldid = 0;
        static $POBfieldid = 0;
        static $C3fieldid = 0;

        $loginfo = examtraining_get_log_reader_info();

        if ($DOBfieldid == 0) {
            $DOBfieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'AMFDOB'));
        }
        if ($POBfieldid == 0) {
            $POBfieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'AMFPOB'));
        }

        if ($C3fieldid == 0) {
            $C3fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'c3'));
        }

        $exam_context = examtraining_get_context($courseid);

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
            $resultset[] = implode(', ', $groupnames); // entity
        } else {
            $resultset[] = "Hors groupe"; // entity
        }

        $resultset[] = $user->id; // userid
        $resultset[] = $user->username; // username

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
        $resultset[] = ($firstenroll) ? date('d/m/Y', $firstenroll) : '' ; // from date
        $firstlogin = $DB->get_field_select($loginfo->table, 'MIN('.$loginfo->timeparam.')', " userid = ? AND action = '".$loginfo->loggedin."' ", array($user->id));
        $resultset[] = ($firstlogin) ? date('d/m/Y', $firstlogin) : '' ; // firstlogin
        $lastlogin = $DB->get_field_select($loginfo->table, 'MAX('.$loginfo->timeparam.')', " userid = ? AND action = '".$loginfo->loggedin."' ", array($user->id));
        $resultset[] = ($lastlogin) ? date('d/m/Y', $lastlogin) : '' ; // firstlogin
        $resultset[] = date('d/m/Y', $from); // from date
        $resultset[] = date('d/m/Y', $to); // to date
        $resultset[] = date('d/m/Y', $to - DAYSECS * 7); // last week of period
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

        $resultset[] = raw_format_duration(@$data->elapsed); // elapsed time
        $resultset[] = raw_format_duration(@$data->weekelapsed); // elapsed time this week

        // $context = get_context_instance(CONTEXT_COURSE, $courseid);
        // $roles = get_user_roles_in_context($userid, $context);
        // $resultset[] = $roles;

        $trainingstats = userquiz_get_user_globals($userid, $exam_context->trainingquizzes, $from, $to);
        $weektrainingstats = userquiz_get_user_globals($userid, $exam_context->trainingquizzes, $to - DAYSECS * 7, $to);

        $resultset[] = 0 + @$trainingstats[$userid]->aanswered; // answered A questions on training
        $resultset[] = 0 + @$weektrainingstats[$userid]->aanswered; // answered A questions on training this week

        $resultset[] = 0 + @$trainingstats[$userid]->canswered; // answered C questions on training
        $resultset[] = 0 + @$weektrainingstats[$userid]->canswered; // answered C questions on training this week

        $resultset[] = ((0 + @$trainingstats[$userid]->ahitratio)).' %'; // ratio A
        $resultset[] = ((0 + @$weektrainingstats[$userid]->ahitratio)).' %'; // ratio A

        $resultset[] = ((0 + @$trainingstats[$userid]->chitratio)).' %'; // ratio C
        $resultset[] = ((0 + @$weektrainingstats[$userid]->chitratio)).' %'; // ratio C

        // $select = " userid = $userid AND blockid = {$exam_context->instanceid} AND attemptid = 0 ";
        // $resultset[] = 0 + get_field_select('userquiz_monitor_user_stats', 'coverageseen', $select).' %'; // knowledge covering
        // $resultset[] = 0 + get_field_select('userquiz_monitor_user_stats', 'coveragematched', $select).' %'; // knowledge covering

        $examstats = userquiz_get_user_globals($userid, $exam_context->examquiz, $from, $to);

        $matchedexams = 0;
        if ($stats = userquiz_get_attempts_stats($userid, $exam_context->examquiz, $from, $to)) {
            foreach ($stats as $attemptid => $attemptres) {
                if ($attemptres->ahitratio * 100 >= $exam_context->rateAserie && $attemptres->chitratio * 100 >= $exam_context->rateCserie) {
                    $matchedexams++;
                }
            }
        }

        $resultset[] = 0 + $matchedexams; // succeeded exam attempts
        $resultset[] = 0 + @$examstats[$userid]->attempts; // exam attempts

        $modules = examtraining_get_module_count($userid, $from, $to);

        for ($i = 1; $i < 10 ; $i++) {
            $modules[$i] = 0 + @$modules[$i];
        }

        for ($i = 1; $i <= 10 ; $i++) {
            $modules[$i * 10] = 0 + @$modules[$i * 10];
        }

        ksort($modules);
        foreach ($modules as $qcount => $modulecont) {
            $resultset[] = ($modulecont) ? $modulecont : ''; // succeeded exam attempts
        }

        // Get special fields
        // Date of birth
        if ($DOBfieldid) {
            $dob = $DB->get_field('user_info_data', 'data', array('fieldid' => $DOBfieldid, 'userid' => $user->id));
            $resultset[] = $dob;
        } else {
            $resultset[] = '[N.C.]';
        }

        // Place of birth
        if ($POBfieldid) {
            $pob = $DB->get_field('user_info_data', 'data', array('fieldid' => $POBfieldid, 'userid' => $user->id));
            $resultset[] = $pob;
        } else {
            $resultset[] = '[N.C.]';
        }

        // C3 completion state
        if ($C3fieldid) {
            $c3 = $DB->get_field('user_info_data', 'data', array('fieldid' => $C3fieldid, 'userid' => $user->id));
            $resultset[] = ($c3) ? 'Certified' : '';
        } else {
            $resultset[] = '[N.C.]';
        }

        return mb_convert_encoding(implode(';', $resultset), 'ISO-8859-1', 'UTF-8')."\n");
    }
}