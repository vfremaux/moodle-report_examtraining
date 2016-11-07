<?php

defined('MOODLE_INTERNAL') || die();

class report_examtraining_xls_renderer extends plugin_renderer_base {

    /**
     * a raster for printing training results in an XLS sheet.
     *
     */
    function trainings(&$outputdoc, $startrow, $xls_formats, $userid, $courseid, &$results) {
        global $CFG;

        $exam_context = examtraining_get_context();

        $ratiostr = get_string('ratio', 'report_examtraining');
        $Aratiostr = get_string('ratioA', 'report_examtraining');
        $Cratiostr = get_string('ratioC', 'report_examtraining');
        $Acountstr = get_string('countA', 'report_examtraining');
        $Ccountstr = get_string('countC', 'report_examtraining');

        // global result
        $outputdoc->write_string($startrow,0, get_string('overalhitstraining', 'report_examtraining'),$xls_formats['t']);
        $outputdoc->merge_cells($startrow,0,$startrow,4);
        $startrow++;

        $outputdoc->write_string($startrow,0,$ratiostr,$xls_formats['tt']);
        $outputdoc->write_string($startrow,1,$Aratiostr,$xls_formats['tt']);
        $outputdoc->write_string($startrow,2,$Cratiostr,$xls_formats['tt']);
        $outputdoc->write_string($startrow,3,$Acountstr,$xls_formats['tt']);
        $outputdoc->write_string($startrow,4,$Ccountstr,$xls_formats['tt']);
        $startrow++;

        $outputdoc->write_string($startrow,0,(@$results->hitratio + 0).'%',$xls_formats['p']);
        $outputdoc->write_string($startrow,1,(@$results->ahitratio + 0).'%',$xls_formats['p']);
        $outputdoc->write_string($startrow,1,(@$results->chitratio + 0).'%',$xls_formats['p']);
        $outputdoc->write_string($startrow,1,(@$results->aanswered + 0),$xls_formats['p']);
        $outputdoc->write_string($startrow,1,(@$results->canswered + 0),$xls_formats['p']);
        $startrow++;

        // jump line
        $startrow++;

        /*
        $outputdoc->write_string($startrow,0, get_string('overalhitstraining', 'report_examtraining'),$xls_formats['t']);
        $outputdoc->merge_cells($startrow,0, $startrow,4);
        $startrow++;
        */

        // get main categories
        if (!empty($results->categories)) {
            $cats = get_records('question_categories', 'parent', $exam_context->rootcategory, 'sortorder,id');

            $categorystr = get_string('categoryname', 'report_examtraining');
            $proposedstr = get_string('proposed', 'report_examtraining');
            $answeredstr = get_string('answered', 'report_examtraining');
            $matchedstr = get_string('matched', 'report_examtraining');
            $ratiostr = get_string('ratio', 'report_examtraining');

            $outputdoc->write_string($startrow,0, get_string('traininghits', 'report_examtraining'),$xls_formats['t']);
            $outputdoc->merge_cells($startrow, 0, $startrow, 4);
            $startrow++;

            $outputdoc->write_string($startrow, 0, $categorystr, $xls_formats['tt']);
            $outputdoc->write_string($startrow, 1, $proposedstr, $xls_formats['tt']);
            $outputdoc->write_string($startrow, 2, $answeredstr, $xls_formats['tt']);
            $outputdoc->write_string($startrow, 3, $matchedstr, $xls_formats['tt']);
            $outputdoc->write_string($startrow, 4, $ratiostr, $xls_formats['tt']);
            $startrow++;

            // per category result
            foreach ($cats as $cat) {
                $outputdoc->write_string($startrow, 0, format_string($cat->name), $xls_formats['ctl']);
                $outputdoc->write_string($startrow, 1, @$results->categories[$cat->id]->count_proposed + 0, $xls_formats['p']);
                $outputdoc->write_string($startrow, 2, @$results->categories[$cat->id]->count_answered + 0, $xls_formats['p']);
                $outputdoc->write_string($startrow, 3, @$results->categories[$cat->id]->count_matched + 0, $xls_formats['p']);
                $outputdoc->write_string($startrow, 4, (@$results->categories[$cat->id]->ratio + 0).' %', $xls_formats['p']);
                $startrow++;
            }

        } else {
            $outputdoc->write_string($startrow, 0, get_string('traininghits', 'report_examtraining'), $xls_formats['t']);
            $startrow++;
            $outputdoc->write_string($startrow, 0, get_string('notrainingactivity', 'report_examtraining'), $xls_formats['tt']);
            $startrow++;
        }

        // jump a line
        $startrow++;

        return $startrow;
    }

    /**
     * a raster for printing in an xls file 
     * with all the relevant data about a user.
     *
     */
    function globalrow(&$xlsdoc, $userid, $courseid, &$data, $from, $to, &$row) {
        global $CFG, $COURSE, $DB;

        $exam_context = examtraining_get_context();

        $user = $DB->get_record('user', array('id' => $userid));
        if ($courseid != $COURSE->id) {
            $course = $DB->get_record('course', array('id' => $courseid));
        } else {
            $course = &$COURSE;
        }

        $resultset = array();
        $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

         $col = 0;

        $data = '';
        if (!empty($usergroups)) {
            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == groups_get_course_group($courseid)) {
                    $str = "$str";
                }
                $groupnames[] = $str;
            }
            $data = implode(', ', $groupnames); // entity

        }
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        // $resultset[] = $user->id; // userid
        $xlsdoc->write_string($row, $col, $user->id, $xls_formats['pl']);
        $col++;

        $loginfo = examtraining_get_log_reader_info();

        $sql = "
            SELECT
                MIN({$loginfo->timeparam})
            FROM
                {{$loginfo->table}}
            WHERE
                userid = ? AND
                {$loginfo->courseparam} = ?
        ";

        $data = date('d/m/Y', $DB->get_field_sql($sql, array($userid, $courseid))); // userid
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $data = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->lastname))), 'ISO-8859-1', 'UTF-8');
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $data = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->firstname))), 'ISO-8859-1', 'UTF-8');
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $data = raw_format_duration(@$data->elapsed); // elapsed time
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $data = raw_format_duration(@$data->weekelapsed); // elapsed time this week
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        // $context = get_context_instance(CONTEXT_COURSE, $courseid);
        // $roles = get_user_roles_in_context($userid, $context);
        // $resultset[] = $roles;

        $trainingstats = userquiz_get_user_globals($userid, $exam_context->trainingquizzes, $from, $to);
        $weektrainingstats = userquiz_get_user_globals($userid, $exam_context->trainingquizzes, time() - DAYSECS * 7, time());

        $data = 0 + @$trainingstats[$userid]->answered; // answered questions on training
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $data = 0 + @$weektrainingstats[$userid]->answered; // answered questions on training this week
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $data = ((0 + @$trainingstats[$userid]->chitratio)).' %'; // ratio C
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $data = ((0 + @$weektrainingstats[$userid]->chitratio)).' %'; // ratio C
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $data = ((0 + @$trainingstats[$userid]->ahitratio)).' %'; // ratio A
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $data = ((0 + @$weektrainingstats[$userid]->ahitratio)).' %'; // ratio A
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        // $select = " userid = $userid AND blockid = {$exam_context->instanceid} AND attemptid = 0 ";
        // $resultset[] = 0 + get_field_select('userquiz_monitor_user_stats', 'coverageseen', $select).' %'; // knowledge covering
        // $resultset[] = 0 + get_field_select('userquiz_monitor_user_stats', 'coveragematched', $select).' %'; // knowledge covering

        $examstats = userquiz_get_user_globals($userid, $exam_context->examquiz, $from, $to);

        $matchedexams = 0;
        if ($stats = userquiz_get_attempts_stats($userid, $exam_context->examquiz)) {
            foreach ($stats as $attemptid => $attemptres) {
                if ($attemptres->ahitratio * 100 >= $exam_context->rateAserie && $attemptres->chitratio * 100 >= $exam_context->rateCserie) {
                    $matchedexams++;
                }
            }
        }

        $data = 0 + $matchedexams; // succeeded exam attempts
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $data = 0 + @$examstats[$userid]->attempts; // exam attempts
        $xlsdoc->write_string($row, $col, $data, $xls_formats['pl']);
        $col++;

        $row++; // passed by reference
    }

}