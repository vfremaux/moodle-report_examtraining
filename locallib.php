<?php

require_once $CFG->dirroot.'/blocks/userquiz_monitor/block_userquiz_monitor_lib.php';
require_once $CFG->libdir.'/questionlib.php';
require_once $CFG->dirroot.'/report/examtraining/statscompilelib.php';
require_once 'excelformats.php';

/**
 * Returns proper info to query log 
 */
function examtraining_get_log_reader_info() {

    $logmanager = get_log_manager();
    $readers = $logmanager->get_readers('\core\log\sql_select_reader');
    $reader = reset($readers);

    if (empty($reader)) {
        echo "No reader";
        return null; // No log reader found.
    }

    if ($reader instanceof \logstore_standard\log\store) {
        $readerinfo = new StdClass;
        $readerinfo->courseparam = 'courseid';
        $readerinfo->table = 'logstore_standard_log';
        $readerinfo->timeparam = 'timecreated';
        $readerinfo->loggedin = 'loggedin';
    } elseif($reader instanceof \logstore_legacy\log\store) {
        $readerinfo = new StdClass;
        $readerinfo->courseparam = 'course';
        $readerinfo->table = 'log';
        $readerinfo->timeparam = 'time';
        $readerinfo->loggedin = 'loggin';
    } else {
        return null;
    }

    return $readerinfo;
}

function count_questions_in_categories_rec($rootcatid, &$cats) {
    global $DB;

    static $level = 0;
    static $countcache = array();

    if (!isset($countcache[$level])) {

        // count real questions
        $cats->count = $DB->count_records_select('question', " category = ? AND parent = 0 AND hidden = 0 ", array($rootcatid));
        $cats->count_a = $DB->count_records_select('question', "category = ? AND parent = 0 AND defaultmark = 1 AND hidden = 0 ", array($rootcatid));
        $cats->count_c = $DB->count_records_select('question', "category = ? AND parent = 0 AND defaultmark = 1000 AND hidden = 0 ", array($rootcatid));
        
        $childs = $DB->get_records('question_categories', array('parent' => $rootcatid), 'id,id');
        
        if ($childs){
            $cats->subs = array();
            foreach($childs as $subcat){
                if ($subcat->id == $rootcatid) continue;
                $subs = new StdClass;
                $level++;
                count_questions_in_categories_rec($subcat->id, $subs);
                $level--;
                $cats->count += $subs->count;
                $cats->count_a += $subs->count_a;
                $cats->count_c += $subs->count_c;
                $cats->subs[$subcat->id] = $subs;
            }
            $countcache[$level]->count = $cats->count;
            $countcache[$level]->count_a = $cats->count_a;
            $countcache[$level]->count_c = $cats->count_c;
            $countcache[$level]->subs = $cats->subs;
        }
    } else {
        $cats->count = $countcache[$level]->count;
        $cats->count_a = $countcache[$level]->count_a;
        $cats->count_c = $countcache[$level]->count_c;
        $cats->subs = &$countcache[$level]->subs;
    }
}

/*********************** RASTERS FOR OUTPUT ************************************/

/**
 * a raster for printing training results in an XLS sheet.
 *
 */
function examtraining_reports_print_trainings_xls(&$xlsdoc, $startrow, $xls_formats, $userid, $courseid, &$results) {
    global $CFG;

    include_once($CFG->dirroot.'/report/examtraining/xlsrenderer.php');
    $renderer = new report_examtraining_xls_renderer($xlsdoc, $startrow, $xls_formats, $userid, $courseid, $results);
    return $renderer->trainings();
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function examtraining_reports_print_exams_summary_html($userid, $from, $to) {
    global $CFG, $PAGE;

    $renderer = $PAGE->get_renderer('report_examtraining');
    echo $renderer->exams_summary($userid, $from, $to);
}

/**
 * a raster for printing exam results in XSL.
 *
 */
function examtraining_reports_print_exams_xls(&$amf_, $startrow, $xls_formats, $userid) {
    global $CFG;

    $exam_context = examtraining_get_context();

    $datestr = get_string('date', 'report_examtraining');
    $tryindexstr = get_string('tryindex', 'report_examtraining');
    $ratiostr = get_string('ratio', 'report_examtraining');
    $Aratiostr = get_string('ratioA', 'report_examtraining');
    $Cratiostr = get_string('ratioC', 'report_examtraining');
    $Acountstr = get_string('countA', 'report_examtraining');
    $Ccountstr = get_string('countC', 'report_examtraining');

    $amf_->write_string($startrow,0, get_string('examtries', 'report_examtraining'), $xls_formats['t']);
    $amf_->merge_cells($startrow,0,$startrow,6);
    $startrow++;

    $amf_->write_string($startrow,0,$tryindexstr,$xls_formats['tt']);
    $amf_->write_string($startrow,1,$datestr,$xls_formats['tt']);
    $amf_->write_string($startrow,2,$ratiostr,$xls_formats['tt']);
    $amf_->write_string($startrow,3,$Aratiostr,$xls_formats['tt']);
    $amf_->write_string($startrow,4,$Cratiostr,$xls_formats['tt']);
    $amf_->write_string($startrow,5,$Acountstr,$xls_formats['tt']);
    $amf_->write_string($startrow,6,$Ccountstr,$xls_formats['tt']);
    $startrow++;

    ksort($results->attempts);
    $i = 1;
    $previous = null;
    if (!empty($results->attempts)) {
        foreach ($results->attempts as $attemptid => $attemptres) {

            // fix ratios for exam because exam must always propose 100 questions
            $attemptres->ratio = $attemptres->ratio * $attemptres->count_proposed / 100;

            $amf_->write_string($startrow,0,$i,$xls_formats['p']);
            $amf_->write_string($startrow,1,examtraining_reports_format_time($attemptres->timefinish, 'xls'),$xls_formats['zt']);
            $amf_->write_string($startrow,2,($attemptres->ratio + 0).' %',$xls_formats['p']);
            $amf_->write_string($startrow,3,(@$attemptres->ratio_A + 0).' %',$xls_formats['p']);
            $amf_->write_string($startrow,4,(@$attemptres->ratio_C + 0).' %',$xls_formats['p']);
            $amf_->write_string($startrow,5,@$attemptres->count_answered_A + 0,$xls_formats['p']);
            $amf_->write_string($startrow,6,@$attemptres->count_answered_A + 0,$xls_formats['p']);
            $startrow++;

            $i++;
        }
    } else {
        $amf_->write_string($startrow,0, get_string('examtries', 'report_examtraining'),$xls_formats['t']);    
        $amf_->merge_cells($startrow,0,$startrow,6);
        $startrow++;
        $amf_->write_string($startrow,0, get_string('noexamtries', 'report_examtraining'),$xls_formats['tt']);    
        $startrow++;
    }

    // jump a line
    $startrow++;

    return $startrow;
}

/**
 * a raster for html printing of a radar.
 *
 * @param array $data 12 categories mastering array
 */
function examtraining_reports_print_radar_html($userid, $from, $to) {
    global $CFG, $DB;

    $exam_context = examtraining_get_context();

    // get mastering indicators in subcategories
    $subcats = new Stdclass;
    $subcatdata = count_questions_in_categories_rec($exam_context->rootcategory, $subcats);
    $quizzes = implode(',', $exam_context->testquizzes);
    $matched = userquiz_get_user_subcats($userid, $quizzes, $from, $to);
    
    // for each root cat, calculate the hitratio
    $maincats = $DB->get_records('question_categories', 'parent', $exam_context->rootcategory, 'sortorder', 'id, name');
    $radardata = array();
    $radarheaders = array();
    if ($matched) {
        foreach($maincats as $id => $cat) {
            $radardata[] = (!empty($matched[$cat->id]->qcount)) ? 0 + (@$matched[$cat->id]->amatched + @$matched[$cat->id]->cmatched) / $matched[$cat->id]->qcount * 100 : 0 ;
            $radarheaders[] = substr($cat->name, 0, 14);
        }
    }

    print_heading(get_string('mastering', 'report_examtraining'));
    echo '<center>';

    $radararg = implode(',', $radardata);
    $headersarg = implode(',', $radarheaders);
    $generatorurl = new moodle_url('/course/report/examtraining/gdgenerators/radargraph.php', array('radar' => $radararg, 'headers' => $headersarg));
    echo '<img src="'.$generatorurl.'" width="500" height="500" />';
    echo '</center>';
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function examtraining_reports_print_knowledge_covering_html($userid, $courseid, $from, $to) {
    global $CFG, $DB, $OUTPUT;

    echo $OUTPUT->heading(get_string('knowledgecovering', 'report_examtraining'));
    echo '<center>';
    $generatorurl = new moodle_url('/course/report/examtraining/gdgenerators/knowledgetag.php', array('userid' => $userid, 'course' => $courseid, 'from' => $from, 'to' => $to));
    echo '<img src="'.$generatorurl.'" width="300" height="300" />';
    echo $OUTPUT->box(get_string('knowledgecoveringlegend', 'report_examtraining'));
    echo '</center>';
}

/**
 * a raster for html printing of a report structure header
 * with all the relevant data about a user.
 *
 */
function examtraining_reports_print_header_html($userid, $courseid, $data, $isshort = false) {
    global $CFG, $DB;

    $user = $DB->get_record('user', 'id', $userid);
    $course = $DB->get_record('course', 'id', $courseid);

    echo '<table width="100%" style="border:1px solid #A0A0A0">';
    echo '<tr><td align="left" width="20%">';
    echo fullname($user);
    echo '</td><td align="center" width="20%">';

    // get group
    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');
    // print group status

    if (!empty($usergroups)) {
        foreach($usergroups as $group) {
            $str = $group->name;
            if ($group->id == get_current_group($courseid)) {
                $str = "<b>$str</b>";
            }
            $groupnames[] = $str;
        }
        echo implode(', ', $groupnames);
    }

    echo '</td><td align="center" width="20%">';
    echo "<a href=\"mailto:{$user->email}\">$user->email</a>";
    echo '</td><td align="center" width="20%">';
    echo $user->city;
    echo '</td><td align="right" width="20%">';
    if ($isshort) {
        $params = array('view' => 'user', 'id' => $courseid, 'userid' => $userid);
        $url = new moodle_url('/course/report/examtraining/index.php', $params);
        echo '<a href="'.$url.'">'.get_string('seedetails', 'report_examtraining').'</a>';
    }
    echo '</td></tr>';
    echo '</table>';
}

function examtraining_reports_print_times_html($userid, &$data) {
    global $DB, $OUTPUT;

    $loginfo = examtraining_get_log_reader_info();

    echo $OUTPUT->heading(get_string('times', 'report_examtraining'));

    $firstaccess = 0 + $DB->get_field_select($loginfo->table, 'MIN('.$loginfo->timeparam.')', " action = ".$loginfo->loggedin." AND userid = ? ", array($userid));
    $lastaccess = 0 + $DB->get_field_select($loginfo->table, 'MAX('.$loginfo->timeparam.')', "  action = '".$loginfo->loggedin."' AND userid = ? ", array($userid));
    $cnx->count = $DB->count_records_select($loginfo->table, "  action = ".$loginfo->loggedin." AND userid = ? ", array($userid));
    $tendaysbefore = time() - DAYSECS * 10;
    $cnx->lastcount = $DB->count_records_select($loginfo->table, "  action = ".$loginfo->loggedin." AND userid = ? AND ".$loginfo->timeparam." > ? ", array($userid, $tendaysbefore));

    // First row
    echo '<table width="100%" style="border:1px solid #A0A0A0;padding:2px" cellspacing="2">';
    echo '<tr><td align="left"><b>';
    print_string('firstaccess', 'report_examtraining');
    echo ' : </b></td><td align="left">';
    echo userdate($firstaccess);
    echo '</td><td align="left"><b>';
    print_string('lastaccess', 'report_examtraining');
    echo ' : </b></td><td align="left">';
    echo userdate($lastaccess);
    echo '</td></tr>';

    // Second row
    echo '<tr><td align="left"><b>';
    print_string('connections', 'report_examtraining');
    echo ' : </b></td><td colspan="3" align="left">';
    echo get_string('connectionscount', 'report_examtraining', $cnx);
    echo '</td></tr>';

    // Third row
    // Start printing the overall times
    echo '<tr><td align="left"><b>';
    print_string('equlearningtime', 'report_examtraining');
    echo '</b></td><td colspan="3" align="left">';
    echo examtraining_reports_format_time(0 + @$data->elapsed, 'html');
    // echo ' ( '.(0 + @$data->events).' hit(s) )';
    echo '</td></tr>';
    echo '</table>';
}

/**
*
*
*/
function examtraining_reports_print_globalheader_xls(&$amf_, &$xls_formats, &$row){
    
     $col = 0;

    $resultset[] = get_string('entity', 'report_examtraining'); // groupname
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('id', 'report_examtraining'); // userid
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('startdate', 'report_examtraining'); // username 
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('lastname', 'report_examtraining'); // user name 
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('firstname', 'report_examtraining'); // user name 
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('timeelapsed', 'report_examtraining');
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('timeelapsedcurweek', 'report_examtraining');
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('answeredquestions', 'report_examtraining');
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('answeredquestionscurweek', 'report_examtraining');
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('ratioa', 'report_examtraining');
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('ratioacurweek', 'report_examtraining');
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('ratioc', 'report_examtraining');
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('ratioccurweek', 'report_examtraining');
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $resultset[] = get_string('examsuccess', 'report_examtraining');
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

    $data = get_string('examattempts', 'report_examtraining');
    $amf_->write_string($row, $col, $data, $xls_formats['pl']);    
    $col++;

}

/**
 * special time formating
 *
 */
function examtraining_reports_format_time($timevalue, $mode = 'html') {
    if ($timevalue) {
        if ($mode == 'html') {
            return ceil($timevalue / HOURSECS) . ' ' . get_string('hours', 'report_examtraining');
        } else {
            // for excel time format we need have a fractional day value
            return  $timevalue / DAYSECS;
        }
    } else {
        return get_string('visited', 'report_examtraining');
    }
}

/**
* a raster for xls printing of a report structure header
* with all the relevant data about a user.
*
*/
function examtraining_reports_print_header_xls(&$amf_, $userid, $courseid, $data, $xls_formats) {
    global $CFG, $DB;

    $loginfo = examtraining_get_log_reader_info();

    $user = $DB->get_record('user', array('id' => $userid));
    $course = $DB->get_record('course', array('id' => $courseid));

    $row = 0;

    $amf_->write_string($row, 0, get_string('progressionreport', 'report_examtraining'), $xls_formats['t']);
    $amf_->merge_cells($row, 0, $row, 12);
    $row++;

    $amf_->write_string($row, 0, get_string('user').' :', $xls_formats['ctr']);
    $amf_->write_string($row, 1, fullname($user), $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $amf_->write_string($row, 0, get_string('email').' :', $xls_formats['ctr']);
    $amf_->write_string($row, 1, $user->email, $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $amf_->write_string($row, 0, get_string('city').' :', $xls_formats['ctr']);
    $amf_->write_string($row, 1, $user->city, $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $amf_->write_string($row, 0, get_string('institution').' :', $xls_formats['ctr']);
    $amf_->write_string($row, 1, $user->institution, $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $amf_->write_string($row, 0, get_string('course', 'report_examtraining').' :', $xls_formats['ctr']);
    $amf_->write_string($row, 1, $course->fullname, $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $amf_->write_string($row, 0, get_string('from').' :', $xls_formats['ctr']);
    $amf_->write_string($row, 1, userdate($data->from), $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $amf_->write_string($row, 0, get_string('to').' :', $xls_formats['ctr']);
    $amf_->write_string($row, 1, userdate($data->to), $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

    // print group status
    $amf_->write_string($row, 0, get_string('groups').' :', $xls_formats['ctr']);
    $str = '';
    if (!empty($usergroups)) {
        foreach($usergroups as $group) {
            $str = $group->name;
            if ($group->id == groups_get_course_group($courseid)) {
                $str = "[$str]";
            }
            $groupnames[] = $str;
        }
        $str = implode(', ', $groupnames);
    }
    $amf_->write_string($row, 1, $str, $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $context = context_course::instance($courseid);
    $amf_->write_string($row, 0, get_string('roles').' :', $xls_formats['ctr']);
    $amf_->write_string($row, 1, strip_tags(get_user_roles_in_context($userid, $context)), $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $amf_->write_string($row, 0, get_string('ratioA', 'report_examtraining'), $xls_formats['ctr']);
    $amf_->write_string($row, 1, 0 + @$data->ahitratio.' %', $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $amf_->write_string($row, 0, get_string('ratioC', 'report_examtraining'), $xls_formats['ctr']);
    $amf_->write_string($row, 1, 0 + @$data->chitratio.' %', $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $amf_->write_string($row, 0, get_string('elapsed', 'report_examtraining').' :', $xls_formats['ctr']);
    $amf_->write_number($row, 1, examtraining_reports_format_time(0 + @$data->elapsed, 'xls'), $xls_formats['ztl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $sql = "
        SELECT
            MIN(".$loginfo->timeparam.") as mintime
        FROM
            {".$loginfo->table."}
        WHERE
            userid = ?
    ";
    $firstcon = $DB->get_record_sql($sql, array($userid));
    $amf_->write_string($row, 0, get_string('firstconnection', 'report_examtraining').' :', $xls_formats['ctr']);
    $mintime = ($firstcon->mintime) ? userdate($firstcon->mintime) : get_string('never');
    $amf_->write_string($row, 1, $mintime, $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);
    $row++;

    $sql = "
        SELECT
            MAX(".$loginfo->timeparam.") as maxtime
        FROM
            {".$loginfo->table."}
        WHERE
            userid = ?
    ";
    $lastcon = $DB->get_record_sql($sql, array($userid));
    $amf_->write_string($row, 0, get_string('lastconnection', 'report_examtraining').' :', $xls_formats['ctr']);    
    $maxtime = ($lastcon->maxtime) ? userdate($lastcon->maxtime) : get_string('never') ;
    $amf_->write_string($row, 1, $maxtime, $xls_formats['pl']);
    $amf_->merge_cells($row, 1, $row, 12);    
    $row++;

    // jump a line
    $row++;

    return $row;
}


/**
* initializes a new amf_ with static formats
* @param int $userid
* @param int $startrow
* @param array $xls_formats
* @param object $workbook
* @return the initialized amf_.
*/
function examtraining_reports_init_worksheet($userid, &$xls_formats, &$workbook, $columndef = null){
    global $DB;

    $user = $DB->get_record('user', array('id' => $userid));
    $sheettitle = mb_convert_encoding(fullname($user), 'ISO-8859-1', 'UTF-8');
    $amf_ =& $workbook->add_worksheet($sheettitle);
    $amf_->hide_gridlines();

    if (is_null($columndef)) {
        $amf_->set_column(0,0,48); 
        $amf_->set_column(1,6,11); 
    } else {
        foreach($columndef as $def){
            list($start,$end,$width) = $def;
            $amf_->set_column($start,$end,$width); 
        }
    }
    
    return $amf_;
}

/**
 * get participating objects to this training context, knowing the course.
 * We have to fetch the userquiz_monitor block configuration matching this course.
 */
function examtraining_get_context($courseid = 0, $passthru = false) {
    global $COURSE, $DB;

    if (!$courseid) $courseid = $COURSE->id;

    $coursecontext = context_course::instance($COURSE->id);
    if (!$instance = $DB->get_record('block_instances', array('blockname' => 'userquiz_monitor', 'parentcontextid' => $coursecontext->id))) {
        if (!$passthru) print_error('no userquiz monitor here', 'block_userquiz_monitor');
        return false;
    }

    $theBlock = block_instance('userquiz_monitor', $instance);
    $theBlock->config->instanceid = $instance->id;
    return $theBlock->config;
}


/** 
 * recursively get all question ids
 */
function examtraining_reports_get_questions_rec($catid, &$questionids) {
    global $DB;
    static $level = 0;

    if ($questions = $DB->get_records_select('question', " category = ? AND parent = 0 ", array($catid), 'id', 'id,name,category')) {
        foreach ($questions as $q) {
            if (!in_array($q->id, $questionids)) {
                $questionids[] = $q->id;
            }
        }
    }

    if ($subcats = $DB->get_records('question_categories', array('parent' => $catid), 'sortorder,id', 'id, name')) {
        foreach ($subcats as $c) {
            examtraining_reports_get_questions_rec($c->id, $questionids);
        }
    }
}

/**
 * Overloads weblib.php function to get it more usable
 *
 */
/**
 * Prints form items with the names $day, $month and $year
 *
 * @param string $day   fieldname
 * @param string $month  fieldname
 * @param string $year  fieldname
 * @param int $currenttime A default timestamp in GMT
 * @param boolean $return
 */
function examtraining_print_date_selector($day, $month, $year, $currenttime=0, $return=false, $from=1970, $to=2020) {

    if (!$currenttime) {
        $currenttime = time();
    }
    $currentdate = usergetdate($currenttime);

    for ($i = 1; $i <= 31; $i++) {
        $days[$i] = $i;
    }
    for ($i = 1; $i <= 12; $i++) {
        $months[$i] = userdate(gmmktime(12,0,0,$i,15,2000), "%B");
    }
    for ($i = $from; $i <= $to; $i++) {
        $years[$i] = $i;
    }

    // Build or print result
    $result='';
    // Note: There should probably be a fieldset around these fields as they are
    // clearly grouped. However this causes problems with display. See Mozilla
    // bug 474415
    $result.='<label class="accesshide" for="menu'.$day.'">'.get_string('day','form').'</label>';
    $result.= html_writer::select($days,   $day,   $currentdate['mday']);
    $result.='<label class="accesshide" for="menu'.$month.'">'.get_string('month','form').'</label>';
    $result.= html_writer::select($months, $month, $currentdate['mon']);
    $result.='<label class="accesshide" for="menu'.$year.'">'.get_string('year','form').'</label>';
    $result.= html_writer::select($years,  $year,  $currentdate['year']);

    if ($return) {
        return $result;
    } else {
        echo $result;
    }
}
 
/**
*
*
*/
function examtraining_print_questionstats($orderby){
    global $CFG, $COURSE, $USER, $DB;

    $exam_context = examtraining_get_context();
    
    $sql = "
        SELECT
            qc.questionid,
            q.name,
            SUM(usecount > 0) as usecount,
            SUM(matchcount > 0) as matchcount
        FROM
            {userquiz_monitor_coverage} qc,
            {question} q
        WHERE
            qc.questionid = q.id AND
            blockid = ?
        GROUP BY
            questionid
        ORDER BY 
            q.name
    ";

    $questionlines = $DB->get_records_sql($sql, array($exam_context->instanceid));

    $i = 1;
    foreach ($questionlines as $elm) {
        $data[0][0][] = $i;
        $data[0][1][] = $elm->usecount;
        $data[0][2][] = preg_replace('/\s.*$/', '', $elm->name);
        $data[1][$i] = $elm->matchcount;
        $data[2][$i] = round(($elm->usecount - $elm->matchcount) / $elm->usecount * 100);
        $i++;
    }

    jqplot_print_questionuse_graph($data, get_string('questionusage', 'report_examtraining'), 'quse');

    $data = array();
    for ($i = 0 ; $i <= 20 ; $i++) {
        $data[''.($i*5)] = 0;
    }
    foreach ($questionlines as $elm) {
        $errorratio = round(($elm->usecount - $elm->matchcount) / $elm->usecount * 100);
        $data[''.(round($errorratio / 20 * 4) * 5)] = @$data[''.(round($errorratio / 20 * 4)) * 5] + 1;
        $i++;
    }

    jqplot_print_simple_bargraph($data, get_string('errorratio', 'report_examtraining'), 'qerrors');

    $sql = "
        SELECT
            qc.questionid,
            q.name,
            q.createdby,
            (SUM(usecount > 0) - SUM(matchcount > 0)) / SUM(usecount > 0) * 100". sql_as()." errorrate,
            SUM(usecount) as totaluse
        FROM
            {userquiz_monitor_coverage} qc,
            {question} q
        WHERE
            qc.questionid = q.id AND
            blockid = ?
        GROUP BY
            questionid
        HAVING
            SUM(usecount) > 0
        ORDER BY
            errorrate $orderby
        LIMIT 0, 50
    ";

    if ($errorquestions = $DB->get_records_sql($sql, array($exam_context->instanceid))) {

        $errorratestr = get_string('errorrate', 'report_examtraining');
        $qnamestr = get_string('qname', 'report_examtraining');
        $totalusestr = get_string('totaluse', 'report_examtraining');

        $table = new html_table();
        $table->head = array("<b>$errorratestr</b<", "<b>$totalusestr</b>", "<b>$qnamestr</b>");
        $table->align = array('left', 'center', 'left');
        $table->width = '100%';
        $table->size = array('10%', '10%', '80%');

        foreach ($errorquestions as $errq) {
            $qlink = new moodle_url('/question/question.php', array('id' => $errq->questionid, 'courseid' => $COURSE->id));
            $caneditq = has_capability('moodle/question:editall', context_course::instance($COURSE->id)) || ($errq->createdby = $USER->id && has_capability('moodle/question:editmine', context_course::instance($COURSE->id)));
            $qname = ($caneditq) ? '<a href="'.$qlink.'">'.$errq->name.'</a>' : $errq->name;
            $table->data[] = array($errq->errorrate, $errq->totaluse, $qname);
        }
        print_table($table);
    }

}

/**
* a raster for html printing of a report structure.
*
* @param string ref $str a buffer for accumulating output
* @param object $structure a course structure object.
*/
function examtraining_reports_print_modules_html($userid, $from, $to) {
    global $CFG;

    $modulestr = get_string('seriesize', 'report_examtraining');
    $attemptsstr = get_string('series', 'report_examtraining');

    $modulecount = examtraining_get_module_count($userid, $from, $to);

    jqplot_print_modules_bargraph($modulecount, get_string('permodule', 'report_examtraining'), 'permodule');
}

function examtraining_get_module_count($userid, $from, $to) {
    global $CFG, $DB;

    $exam_context = examtraining_get_context();
    $testquizzes = implode("','", $exam_context->trainingquizzes);

    $fromclause = ($from) ? " AND qa.timefinish > $from " : '';
    $toclause = ($to) ? " AND qa.timefinish < $to " : '';

    // compute attempts "per module size"

    $sql = "
        SELECT
            qcount,
            COUNT(qa.id) as acount
        FROM
            {quiz_attempts} qa
        LEFT JOIN
            {userquiz_attempts} ua
        ON
            qa.uniqueid = ua.uniqueid
        WHERE
            userid = ? AND
            quiz IN ('$testquizzes ')
            $fromclause
            $toclause
        GROUP BY
            qcount
        ORDER BY
            qcount
    ";

    return $DB->get_records_sql_menu($sql, array($userid));
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 * TODO : Mark for obolescence.
 */
function examtraining_reports_print_assiduity_html($userid, $from, $to) {
    global $CFG, $DB;

    $modulestr = get_string('assiduity', 'report_examtraining');
    $attemptsstr = get_string('attempts', 'userquiz');

    $exam_context = examtraining_get_context();

    $fromclause = ($from) ? " AND qa.timefinish > $from " : '' ;
    $toclause = ($to) ? " AND qa.timefinish < $to " : '' ;

    // compute attempts "per day"

    $sql = "
        SELECT
            timefinish * 1000,
            COUNT(id) as acount
        FROM
            {quiz_attempts} qa
        WHERE
            userid = ?
            $fromclause
            $toclause
        GROUP BY
            DAY(FROM_UNIXTIME(qa.timefinish))
        ORDER BY
            qa.timefinish
    ";

    if ($assiduity = $DB->get_records_sql_menu($sql, array($userid))) {

        $labels = array(
            array(
                'label' => get_string('assiduity', 'report_examtraining'),
                'lineWidth' => 4,
                'color' => '#40E040',
                'showMarker' => 'false'
            ),
        );

        $assiduityarr[] = array_keys($assiduity);
        $assiduityarr[] = array_values($assiduity);

        jqplot_print_timecurve_bars($assiduityarr, get_string('assiduity', 'report_examtraining'), 'assiduity', $labels, get_string('attemptquantity', 'report_examtraining'));
    }
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function examtraining_reports_print_assiduity2_html($userid, $from, $to) {
    global $CFG, $DB;

    $modulestr = get_string('assiduity', 'report_examtraining');
    $attemptsstr = get_string('attempts', 'userquiz');

    $exam_context = examtraining_get_context();

    $fromclause = ($from) ? " AND qa.timefinish > $from " : '';
    $toclause = ($to) ? " AND qa.timefinish < $to " : '';

    // compute attempts "per day"
    
    $sql = "
        SELECT
            UNIX_TIMESTAMP(DATE(FROM_UNIXTIME(timefinish))) as daystamp,
            COUNT(id) as acount
        FROM
            {quiz_attempts} qa
        WHERE
            userid = ?
            $fromclause
            $toclause
        GROUP BY
            DAY(FROM_UNIXTIME(timefinish))
        ORDER BY
            daystamp
    ";

    if ($assiduity = $DB->get_records_sql_menu($sql, array($userid))) {

        $dateticks = array_keys($assiduity);

        $firstdate = $dateticks[0];
        $lastdate = $dateticks[count($dateticks) - 1];

        // rebuild an unholed array for bargraphs
        $stamp = $firstdate;
        $i = 0;
        $attemptstable = array();
        while ($stamp < $lastdate && ($i < 500)) {
            $attemptstable[date('Y-m-d', $stamp)] = 0 + @$assiduity[$stamp];
            $stamp += DAYSECS;
            $i++;
        }

        jqplot_print_assiduity_bargraph($attemptstable, array_keys($attemptstable), get_string('assiduity', 'report_examtraining'), 'assiduity');
    }
}

/**
 * returns the lost of the groups of the user.
 *
 */
function examtraining_get_grouplist($courseid, $userid) {
    global $DB;

    $groupnames = array();

    $usergroupings = groups_get_user_groups($courseid, $userid);
    foreach ($usergroupings as $grouping) {
        foreach ($grouping as $groupid){
            $groupnames[] = $DB->get_field('groups', 'name', array('id' => $groupid));
        }
    }
    
    return implode(', ', $groupnames);
}

function examtraining_compute_results($userid, $from, $to, $part, $attemptid = 0){
    global $USER, $CFG, $DB;
    global $CATEGORIES;
    global $QUESTIONS;
    
    // init structure
    
    $exam_context = examtraining_get_context();

    // we get all states
    if ($part == 'training') {
        $quizzeslist = implode("','", $exam_context->trainingquizzes);
    } else {
        $quizzeslist = str_replace(',', "','", $exam_context->examquiz);
    }

    // Category cache
    if (empty($QUESTIONS)) {
        $QUESTIONS = $DB->get_records('question', array(), 'id', 'id,defaultgrade,category');
    }
    if (empty($CATEGORIES)){
        $CATEGORIES = get_records('question_categories', array(), 'id', 'id,parent');
    }

    // prefetch categories structure

    $cats = new StdClass;
    $totalquestions = count_questions_in_categories_rec($exam_context->rootcategory, $cats);

    // compute results

    $results = new StdClass;
    $results->categories = array();
    $results->attempts = array();
    $results->items = 0;
    $results->done = 0;

    if (empty($attemptid)) {
        $select = " userid = ? AND timefinish > ? AND timefinish < ? AND quiz IN ('$quizzeslist') ";
        $attempts = $DB->get_records_select('quiz_attempts', $select, array($userid, $from, $to));
    } else {
        $select = " id = ? ";
        $attempts = $DB->get_records_select('quiz_attempts', $select, array($attemptid));
    }

    if ($attempts) {
        foreach($attempts as $attempt) {
            if ($statesrs = get_all_user_records($attempt->uniqueid, $userid, null, true)){
                if ($statesrs->valid()) {
                    foreach ($statesrs as $state) {

                        // compute answers in states against question answers determining question type
                        if (!$question = &$QUESTIONS[$state->question]) {
                            continue;
                        }
                        $cattype = ($question->defaultmark == 1) ? 'A' : 'C';
                        $ht = "hastype_$cattype";
                        $ca = "count_answered_$cattype";
                        $cp = "count_proposed_$cattype";
                        $cm = "count_matched_$cattype";
                        $cat = "count_answered";
                        $cpt = "count_proposed";
                        $cmt = "count_matched";

                        // aggregate upper category till rootcategory
                        $currentcat = &$CATEGORIES[$question->category];

                        if ($state->grade > 0) {
                            @$results->attempts[$attempt->id]->{$cm}++;
                            @$results->attempts[$attempt->id]->{$cmt}++;
                        }
                        if (strstr($state->answer, ':') !== false){
                            @$results->attempts[$attempt->id]->{$ca}++;
                            @$results->attempts[$attempt->id]->{$cat}++;
                        }
                        @$results->attempts[$attempt->id]->{$cp}++;
                        @$results->attempts[$attempt->id]->{$cpt}++;
                        $results->attempts[$attempt->id]->timefinish = $attempt->timefinish;
                        $results->modules[$attempt->userquiz][$attempt->id] = 1; // to count frequency of use of questionset
    
                        do {
                            $previouscatid = $currentcat->id;
                            $results->categories[$currentcat->id]->{$ht} = 1;
                            if ($state->grade > 0) {
                                $results->categories[$currentcat->id]->{$cm}++;
                                $results->categories[$currentcat->id]->{$cmt}++;
                            }
                            if (strstr($state->answer, ':') !== false){
                                $results->categories[$currentcat->id]->{$ca}++;
                                $results->categories[$currentcat->id]->{$cat}++;
                            }
                            $results->categories[$currentcat->id]->{$cp}++;
                            $results->categories[$currentcat->id]->{$cpt}++;
                            $currentcat = &$CATEGORIES[$currentcat->parent];
                        } while($currentcat && ($previouscatid != $exam_context->rootcategory));
                    }
                }
                $statesrs->close();
            }
        }
    }

    // post compute ratios
    if (!empty($results->categories)) {
        foreach(array_keys($results->categories) as $catid) {
            if (@$results->categories[$catid]->count_answered_A) {
                $results->categories[$catid]->hitratio_A = round(@$results->categories[$catid]->count_matched_A / $results->categories[$catid]->count_answered_A * 100);
            } else {
                $results->categories[$catid]->hitratio_A = 0;
            }
            if (@$results->categories[$catid]->count_proposed_A) {
                $results->categories[$catid]->ratio_A = round(@$results->categories[$catid]->count_matched_A / $results->categories[$catid]->count_proposed_A * 100);
            } else {
                $results->categories[$catid]->ratio_A = 0;
            }
            if (@$results->categories[$catid]->count_answered_C) {
                $results->categories[$catid]->hitratio_C = round(@$results->categories[$catid]->count_matched_C / $results->categories[$catid]->count_answered_C * 100);
            } else {
                $results->categories[$catid]->hitratio_C = 0;
            }
            if (@$results->categories[$catid]->count_proposed_C) {
                $results->categories[$catid]->ratio_C = round(@$results->categories[$catid]->count_matched_C / $results->categories[$catid]->count_proposed_C * 100);
            } else {
                $results->categories[$catid]->ratio_C = 0;
            }
            if (@$results->categories[$catid]->count_answered) {
                $results->categories[$catid]->hitratio = round(@$results->categories[$catid]->count_matched / $results->categories[$catid]->count_answered * 100);
            } else {
                $results->categories[$catid]->hitratio = 0;
            }
            if (@$results->categories[$catid]->count_proposed) {
                $results->categories[$catid]->ratio = round(@$results->categories[$catid]->count_matched / $results->categories[$catid]->count_proposed * 100);
            } else {
                $results->categories[$catid]->ratio = 0;
            }
            if ($catid != $exam_context->rootcategory) {
                $cat = get_record('question_categories', 'id', $catid);
                if ($cat->parent == $exam_context->rootcategory) {
                    if (@$cats->subs[$catid]->count > 0) {
                        $results->categories[$catid]->mastering = (0 + @$results->categories[$catid]->count_matched) / $cats->subs[$catid]->count * 40;
                        $results->masteringdata[$catid] = min(100, $results->categories[$catid]->mastering);
                        $results->masteringheaders[$catid] = shorten_text($cat->name, 15);
                    } else {
                        $results->categories[$catid]->mastering = 0;
                        $results->masteringdata[$catid] = 0;
                        $results->masteringheaders[$catid] = shorten_text($cat->name, 15);
                    }
                }
            }
        }
    }
    if (!empty($results->attempts)) {
        foreach (array_keys($results->attempts) as $attemptid) {
            if (@$results->attempts[$attemptid]->count_answered_A) {
                $results->attempts[$attemptid]->hitratio_A = round(@$results->attempts[$attemptid]->count_matched_A / $results->attempts[$attemptid]->count_answered_A * 100);
            } else {
                $results->attempts[$attemptid]->hitratio_A = 0;
            }
            if (@$results->attempts[$attemptid]->count_proposed_A) {
                $results->attempts[$attemptid]->ratio_A = round(@$results->attempts[$attemptid]->count_matched_A / $results->attempts[$attemptid]->count_proposed_A * 100);
            } else {
                $results->attempts[$attemptid]->ratio_A = 0;
            }
            if (@$results->attempts[$attemptid]->count_answered_C) {
                $results->attempts[$attemptid]->hitratio_C = round(@$results->attempts[$attemptid]->count_matched_C / $results->attempts[$attemptid]->count_answered_C * 100);
            } else {
                $results->attempts[$attemptid]->hitratio_C = 0;
            }
            if (@$results->attempts[$attemptid]->count_proposed_C) {
                $results->attempts[$attemptid]->ratio_C = round(@$results->attempts[$attemptid]->count_matched_C / $results->attempts[$attemptid]->count_proposed_C * 100);
            } else {
                $results->attempts[$attemptid]->ratio_C = 0;
            }
            if (@$results->attempts[$attemptid]->count_answered) {
                $results->attempts[$attemptid]->hitratio = round(@$results->attempts[$attemptid]->count_matched / $results->attempts[$attemptid]->count_answered * 100);
            } else {
                $results->attempts[$attemptid]->hitratio = 0;
            }
            if (@$results->attempts[$attemptid]->count_proposed) {
                $results->attempts[$attemptid]->ratio = round(@$results->attempts[$attemptid]->count_matched / $results->attempts[$attemptid]->count_proposed * 100);
            } else {
                $results->attempts[$attemptid]->ratio = 0;
            }
        }
    }

    if (isset($results->categories[$exam_context->rootcategory])) {
        $results->items = @$results->categories[$exam_context->rootcategory]->count_proposed_C + @$results->categories[$exam_context->rootcategory]->count_proposed_A;
        $results->done = @$results->categories[$exam_context->rootcategory]->count_matched_C + @$results->categories[$exam_context->rootcategory]->count_matched_A;

        if ($cats->count > 0) {
            $results->categories[$exam_context->rootcategory]->mastering = (0 + @$results->categories[$exam_context->rootcategory]->count_matched) / $cats->count * 40;
        } else {
            $results->categories[$exam_context->rootcategory]->mastering = 0;
        }
        if ($cats->count_a > 0) {
            $results->categories[$exam_context->rootcategory]->mastering_A = (0 + @$results->categories[$exam_context->rootcategory]->count_matched_A) / $cats->count_a * 40;
        } else {
            $results->categories[$exam_context->rootcategory]->mastering_A = 0;
        }
        if ($cats->count_c > 0) {
            $results->categories[$exam_context->rootcategory]->mastering_C = (0 + @$results->categories[$exam_context->rootcategory]->count_matched_C) / $cats->count_c * 40;
        } else {
            $results->categories[$exam_context->rootcategory]->mastering_C = 0;
        }
    }
    return $results;
}

/**
 *
 *
 */
function examtraining_compute_global_results($userid, $from, $to) {
    global $USER;
    global $CFG;
    global $QUESTIONS;

    // init structure

    $exam_context = examtraining_get_context();

    // we get all states
    $quizzeslist = implode("','", $exam_context->testquizzes);
    $examquizzeslist = str_replace(',', "','", $exam_context->examquizzes);
    
    $results = new StdClass;
    if (!isset($QUESTIONS)) {
        $QUESTIONS = $DB->get_records('question', array(), 'id,defaultgrade,category');
    }

    $examselect = " userid = ? AND timefinish > ? AND timefinish < ? AND quiz IN ('$examquizzeslist') ";
    if ($exams = $DB->get_records_select('quiz_attempts', $examselect, array($userid, $from, $to))) {
        $results->exams = count($exams);
    } else {
        $results->exams = 0;
    }

    $select = " userid = ? AND timefinish > ? AND timefinish < ? AND userquiz IN ('$quizzeslist') ";
    $distinctquestions = array();
    if ($attempts = $DB->get_records_select('quiz_attempts', $select, array($userid, $from, $to))){
        $results->attempts = count($attempts);
        foreach ($attempts as $attempt) {
            if ($statesrs = get_all_user_records($attempt->id, $userid, null, true)) {
                if ($statesrs->valid()) {
                    foreach ($staters as $state) {
                        // compute answers in states against question answers determining question type
                        $question = &$QUESTIONS[$state->question];

                        if (!$question) continue;
                        $cattype = ($question->defaultmark == 1) ? 'A' : 'C' ;
                        $ht = "hastype_$cattype";
                        $ca = "count_answered_$cattype";
                        $cp = "count_proposed_$cattype";
                        $cm = "count_matched_$cattype";
                        $cat = "count_answered";
                        $cpt = "count_proposed";
                        $cmt = "count_matched";

                        // aggregate on globalizers
                        if ($state->grade > 0) {
                            @$results->{$cm}++;
                            @$results->{$cmt}++;
                        }
                        if (strstr($state->answer, ':') !== false) {
                            @$results->{$ca}++;
                            @$results->{$cat}++;
                        }
                        @$results->{$cp}++;
                        @$results->{$cpt}++;
                    }
                }
                $$statesrs->close();
            }
        }
    }

    // post compute ratios
    if (!empty($results->count_proposed)) {
        $results->ratio = round( (0 + @$results->count_matched) / $results->count_proposed * 100);
    } else {
        $results->ratio = 0;
    }
    if (!empty($results->count_proposed_A)) {
        $results->ratio_A = round((0 + @$results->count_matched_A) / $results->count_proposed_A * 100);
    } else {
        $results->ratio_A = 0;
    }
    if (!empty($results->count_proposed_C)) {
        $results->ratio_C = round((0 + @$results->count_matched_C) / $results->count_proposed_C * 100);
    } else {
        $results->ratio_C = 0;
    }
    $results->items = @$results->count_proposed;
    $results->done = @$results->count_matched;

    $questionids = array();
    examtraining_reports_get_questions_rec($exam_context->rootcategory, $questionids);
    $questioncount = count($questionids);
    if ($questioncount) {
        $results->knowledge_covering_ratio = round(count($distinctquestions) / $questioncount * 100);
    }

    return $results;
}

function raw_format_duration($secs){
    $min = floor($secs / 60);
    $hours = floor($min / 60);
    $days = floor($hours / 24);

    $hours = $hours - $days * 24;
    $min = $min - ($days * 24 * 60 + $hours * 60);
    $secs = $secs - ($days * 24 * 60 * 60 + $hours * 60 * 60 + $min * 60);

    if ($days) {
        return $days.' '.get_string('days')." $hours ".get_string('hours')." $min ".get_string('min')." $secs ".get_string('secs');
    }
    if ($hours) {
        return $hours.' '.get_string('hours')." $min ".get_string('min')." $secs ".get_string('secs');
    }
    if ($min) {
        return $min.' '.get_string('min')." $secs ".get_string('secs');
    }
    return $secs.' '.get_string('secs');
}

function examtraining_reports_print_questiondetail_xls(&$amf_, $startrow, $effg, $xls_formats) {
    global $QCAT;

    $amf_->write_string($startrow,0, get_string('questionsort', 'report_examtraining', $effg->sortorder),$xls_formats['t']);
    $amf_->merge_cells($startrow , 0, $startrow, 6);
    $amf_->merge_cells($startrow + 1, 0, $startrow + 1, 6);
    $amf_->write_string($startrow,7, $effg->name,$xls_formats['tw']);
    $amf_->merge_cells($startrow, 7, $startrow, 12);

    $startrow++;
    $amf_->write_string($startrow,7, $effg->questiontext,$xls_formats['tw']);
    $amf_->merge_cells($startrow, 7, $startrow, 12);

    $startrow++;
    $amf_->write_string($startrow,0, get_string('answers', 'report_examtraining'),$xls_formats['t']);
    $ansnum = 0;
    foreach ($effg->answers as $a) {
        $format = ($a->fraction) ? $xls_formats['t+'] : $xls_formats['t-'];
        $amf_->write_string($startrow,7, $a->answer, $format);
        $amf_->merge_cells($startrow, 7, $startrow, 12);
        $startrow++;
        $ansnum++;
    }
    $amf_->merge_cells($startrow - $ansnum, 0, $startrow  - $ansnum, 6);   // answer title line
    $amf_->merge_cells($startrow - $ansnum + 1, 0, $startrow - 1, 6);  // other answers

    $givenanswerclass = ($effg->score) ? 'qcorrect' : 'qfailed';
    $givenanswerformat = ($effg->defaultgrade == 1000) ? $xls_formats['t+'] : $xls_formats['t-'];
    $amf_->write_string($startrow,0, get_string('givenanswer', 'report_examtraining'),$xls_formats['t']);
    $amf_->merge_cells($startrow, 0, $startrow, 6);
    $amf_->write_string($startrow,7, $effg->answeredtext, $givenanswerformat);
    $amf_->merge_cells($startrow, 7, $startrow, 12);

    $startrow++;
    $amf_->write_string($startrow,0, get_string('category', 'report_examtraining'),$xls_formats['t']);
    $amf_->merge_cells($startrow, 0, $startrow, 6);
    $amf_->write_string($startrow,7, $QCAT[$effg->category]->name, $xls_formats['t']);
    $amf_->merge_cells($startrow, 7, $startrow, 12);

    $startrow++;
    $amf_->write_string($startrow,0, get_string('type', 'report_examtraining'), $xls_formats['t']);
    $amf_->merge_cells($startrow, 0, $startrow, 6);
    $amf_->write_string($startrow,7, $effg->type, $xls_formats['t']);
    $amf_->merge_cells($startrow, 7, $startrow, 12);

    $startrow++;
    $amf_->write_string($startrow,0, get_string('score', 'report_examtraining'), $xls_formats['t']);
    $amf_->merge_cells($startrow, 0, $startrow, 6);
    $amf_->write_string($startrow,7, $effg->score, $xls_formats['t']);
    $amf_->merge_cells($startrow, 7, $startrow, 12);

    return $startrow;
}

function examtraining_reports_print_catscores_xls(&$amf_, $startrow, $scores, $xls_formats) {

    $amf_->write_string($startrow,0, get_string('category', 'report_examtraining'), $xls_formats['t']);
    $amf_->write_string($startrow,1, $scores->name, $xls_formats['t']);

    if (!empty($scores->atype)) {
        $amf_->write_string($startrow,2, 'Type A', $xls_formats['t']);
        $amf_->write_string($startrow,3, sprintf('%0.2f', $scores->aratio), $xls_formats['t']);
        if ($scores->atype) {
            $amf_->write_string($startrow,3, @$scores->ascore.'/'.@$scores->atype, $xls_formats['t']);
        } else {
            $amf_->write_string($startrow,3, 0, $xls_formats['t']);
        }
    }
    if (!empty($scores->ctype)) {
        $amf_->write_string($startrow,2, 'Type C', $xls_formats['t']);
        $amf_->write_string($startrow,3, sprintf('%0.2f', $scores->cratio), $xls_formats['t']);
        if ($scores->ctype) {
            $amf_->write_string($startrow,3, @$scores->ascore.'/'.@$scores->atype, $xls_formats['t']);
        } else {
            $amf_->write_string($startrow,3, 0, $xls_formats['t']);
        }
    }
}

function examtraining_reports_print_overralcatscores_xls($worksheet, $startrow, $scores, $xls_formats) {
}

