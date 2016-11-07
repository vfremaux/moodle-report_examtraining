<?php

defined('MOODLE_INTERNAL') || die();

/**
 * direct log construction implementation
 *
 */

require_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
require_once $CFG->dirroot.'/course/report/barchenamf3/locallib.php';

$attemptid = required_param('attemptid', PARAM_INT);

ini_set('memory_limit', '256M');

// TODO : Secure userid access depending on proper capabilities

// don't give access to any unless coaches
require_capability('report/examtraining:viewall', $context);

// calculate start time

// get data

// Barchen case
$attempt = get_record('userquiz_attempts', 'uniqueid', $attemptid);
$quiz = get_record('userquiz', 'id', $attempt->userquiz);

$user = get_record('user', 'id', $attempt->userid);

$questionset = explode(',', $attempt->layout);
$realquestions = array();
foreach($questionset as $qid) {
    if ($qid == 0 || $qid == '') {
        continue;
    }
    $realquestions[] = $qid;

    $q = $DB->get_record('question', 'id', $qid);

        // if randomized, need fetch the effective question
        if (preg_match('/^random/', $q->qtype)){
            if ($state = get_record('question_states', 'attempt', $attemptid, 'question', $q->id, 'event', 0)){
                if (preg_match("/^{$q->qtype}(\\d+)-/", $state->answer, $matches)){
                    $effectiveqid = $matches[1];
                }
            }
        } else {
            $effectiveqid = $qid;
        }

        $questions[$qid] = $q;
        $questions[$effectiveqid] = get_record('question', 'id', $effectiveqid);
        $questionforwards[$qid] = $effectiveqid;

    }

// print result

$i = 1;

$atype = 0;
$ctype = 0;
$ascore = 0;
$cscore = 0;

global $QCAT;

$QCAT = array();
$EFFQS = array();

// compute question and prepare some data for output (html or xls)
foreach($realquestions as $qid) {

    // get effective question for answers, check it is a straight forward question        
    if (array_key_exists($qid, $questionforwards)){
        $effectiveqid = $questionforwards[$qid];
    } else {
        $effectiveqid = $qid;
    }

    $effq = $DB->get_record('question', array('id' => $effectiveqid));

    if (!array_key_exists($effq->category, $QCAT)) {
        $QCAT[$effq->category] = $DB->get_record('question_categories', array('id' => $effq->category));
    }

    $effg = clone($effq);
    $effg->answers = $DB->get_records('question_answers', array('question' => $effectiveqid));

    if ($state = get_record('question_states', 'attempt', $attemptid, 'question', $qid, 'event', 3)) {
        $effg->score = $state->raw_grade;
        list($questioninfo, $answerid) = explode(':', $state->answer);
    } else {
        $effg->score = 0;
        $answerid = 0;
    }

    $effg->answeredtext = get_string('unanswered', 'report_examtraining');
    foreach ($effg->answers as $aid => $a){
        if ($a->id == $answerid){
            $effg->answeredtext = "<div>$a->answer</div>";
        }
        $answerclass = ($a->fraction) ? 'qcorrect' : 'qfailed';
        $answers[$aid]->htmloutput = '<li style="wordwrap:wrap" class="'.$answerclass.'">'.$a->answer.'</li>';
    }

    $effg->sortorder = $i;

    $effg->htmloutput = "<table width=\"$tablewidth\" class=\"generaltable\">";    
    $effg->htmloutput .= '<tr valign="top" class="header r0">';
    $effg->htmloutput .= '<td width="10%" class=="header c0">Question :</td>';
    $effg->htmloutput .= '<td class="questioninfo">';
    $effg->htmloutput .= "Tirage : ".$i."<br/>";
    $effg->htmloutput .= "<b>Question : {$effq->name}</b><br/>";
    $effg->htmloutput .= '<span class="qfulltext">'.$effq->questiontext.'</span><br/><ul>';
    foreach ($effg->answers as $a) {
        $effg->htmloutput .= $answers[$a->id]->htmloutput;
    }
    $effg->htmloutput .= '</ul><br/>';
    $givenanswerclass = ($effg->score) ? 'qcorrect' : 'qfailed';
    $effg->type = ($effq->defaultgrade == 1000) ? 'C' : 'A' ;
    $effg->typeoutput = ($effq->defaultgrade == 1000) ? '<img width="14" height="15" src="'.$CFG->wwwroot.'/blocks/userquiz_monitor/pix/c.png" />' : '<img width="14" height="15" src="'.$CFG->wwwroot.'/blocks/userquiz_monitor/pix/a.png" />' ;
    $effg->htmloutput .= get_string('givenanswer', 'report_examtraining', '<div class="'.$givenanswerclass.'">'.$effg->answeredtext.'</div>');
    $effg->htmloutput .= "Categorie : <span class=\"qcategory\">".$QCAT[$effq->category]->name.'</span><br/>';
    $effg->htmloutput .= "Type : <span class=\"qtype\">".$effg->typeoutput.'</span><br/>';
    $effg->htmloutput .= "Score : <span class=\"qscore\">".$effg->score.'</span><br/>';
    $effg->htmloutput .= '</td></tr>';
    $effg->htmloutput .= "</table>";    

    $QCAT[$effq->category]->qs = @$QCAT[$effq->category]->qs + 1;
    if ($effq->defaultgrade == 1000) {
        $QCAT[$effq->category]->ctype = @$QCAT[$effq->category]->ctype + 1;
        $ctype++;
        if ($effg->score) {
            $cscore++;
            $QCAT[$effq->category]->cscore = @$QCAT[$effq->category]->cscore + 1;
        }
    } else {
        $QCAT[$effq->category]->atype = @$QCAT[$effq->category]->atype + 1;
        $atype++;
        if ($effg->score) {
            $ascore++;
            $QCAT[$effq->category]->ascore = @$QCAT[$effq->category]->ascore + 1;
        }
    }

    $EFFQS[$qid] = $effg;

    $i++;
}

function sortcatsbyname($a, $b) {
    if (preg_match('/^([0-9]+)\.([0-9]+)\s/', $a->name, $matches)) {
        $amajor = $matches[1];
        $aminor = $matches[2];
    } else {
        $amajor = 1000;
        $aminor = 0;
    }
    if (preg_match('/^([0-9]+)\.([0-9]+)\s/', $b->name, $matches)){
        $bmajor = $matches[1];
        $bminor = $matches[2];
    } else {
        $bmajor = 1000;
        $bminor = 0;
    }
    if ($amajor * 100 + $aminor > $bmajor * 100 + $bminor) return 1;
    if ($amajor * 100 + $aminor < $bmajor * 100 + $bminor) return -1;
    return 0;
}

uasort($QCAT, 'sortcatsbyname');

$total = $atype + $ctype;
$aratio = ($atype) ? $ascore / $atype * 100 : 0 ;
$cratio = ($ctype) ? $cscore / $ctype * 100 : 0 ;

//
// Rastering output document
//

if ($output == 'html' || $output == 'pdf') {
    // time period form

    $html .= '<h2>'.get_string('userinfo', 'report_examtraining').'</h2>';

    if ($output == 'html') {
        $html .= "<link rel=\"stylesheet\" href=\"reports.css\" type=\"text/css\" />";
        $html .= print_user($user, $COURSE, false, true);
    } else {
        $usergroups = groups_get_all_groups($COURSE->id, $user->id, 0, 'g.id, g.name');
        $groupsinfo = '';
        // print group status
        if (!empty($usergroups)) {
            foreach($usergroups as $group) {
                $str = $group->name;
                if ($group->id == get_current_group($COURSE->id)){
                    $str = "<b>$str</b>";
                }
                $groupnames[] = $str;
            }
            $groupsinfo = implode(', ', $groupnames);                        
        }

        $html .= "<table width=\"$tablewidth\" class=\"generaltable\"><tr>";
        $html .= '<td width="20%">'.print_user_picture($user, $COURSE->id, null, 0, true, false).'</td>';
        // $html .= '<td width="35%"><img src="'.$CFG->wwwroot.'/pix/u/f1.png" width="98" height="98" /></td>';
        $html .= '<td width="65%"><span  class="username">'.fullname($user).'</span><br/>';
        $html .= '['.$user->email.']</td>';
        $html .= '</tr><tr>';
        $html .= '<td class="param" width="35%">Premier accès : </td>';
        $html .= '<td width="65%">'.userdate($user->firstaccess).'</td>';
        $html .= '</tr><tr>';
        $html .= '<td class="param" width="35%">Dernière connexion : </td>';
        $html .= '<td width="65%">'.userdate($user->lastlogin).'</td>';
        $html .= '</tr><tr>';
        $html .= '<td class="param" width="35%">Institution : </td>';
        $html .= '<td width="65%">'.$user->institution.'</td>';
        $html .= '</tr><tr>';
        $html .= '<td class="param" width="35%">Groupes : </td>';
        $html .= '<td width="65%">'.$groupsinfo.'</td>';
        $html .= '</tr></table>';
    }

    // echo htmlentities($html);

    $html .= '<h2>'.get_string('attemptinfo', 'report_examtraining').'</h2>';

    $html .= '<table width="'.$tablewidth.'" class="generaltable"><tr>';
    $html .= '<td class="param">Cours : </td>';
    $html .= '<td>'.format_string($COURSE->fullname).'</td>';
    $html .= '</tr><tr>';
    $html .= '<td class="param">ID Cours : </td>';
    $html .= '<td>'.format_string($COURSE->shortname).' ('.$COURSE->idnumber.')</td>';
    $html .= '</tr><tr>';
    $html .= '<td class="param">Test : </td>';
    $html .= '<td>'.format_string($quiz->name).'</td>';
    $html .= '</tr><tr>';
    $html .= '<td class="param">Date début : </td>';
    $html .= '<td>'.userdate($attempt->timestart).'</td>';
    $html .= '</tr><tr>';
    $html .= '<td class="param">Date fin : </td>';
    $html .= '<td>'.userdate($attempt->timefinish).'</td>';
    $html .= '</tr><tr>';
    $html .= '<td class="param">ID : </td>';
    $html .= '<td>'.$attempt->uniqueid.'</td>';
    $html .= '</tr></table>';

    if ($output == 'pdf') {
        $html .= html2pdf_print_page_break(true);
    }

    $html .= '<h2>'.get_string('questionanswersdetail', 'report_examtraining').'</h2>';

    foreach ($EFFQS as $effq) {
        $html .= $effq->htmloutput;
    }

    $html .= '<h2>'.get_string('questionsetscores', 'report_examtraining').'</h2>';

    // now we can output category and type sums
    $html .= "<p><table width=\"$tablewidth\" class=\"generaltable\">";
    $html .= '<tr>
                <th></th>
                <th align="center" class="header c1">%</th>
                <th align="center" class="header c2">#</th>
              </tr>';
    $html .= '<tr><td>Type A</td><td align="center">'.sprintf('%0.2f', $aratio).'</td><td align="center">'.$ascore.'/'.$atype.'</td></tr>';
    $html .= '<tr><td>Type C</td><td align="center">'.sprintf('%0.2f', $cratio).'</td><td align="center">'.$cscore.'/'.$ctype.'</td></tr>';
    $html .= '</table></p>';

    $html .= '<h2>'.get_string('categoryscores', 'report_examtraining').'</h2>';

    // print category scores
    foreach ($QCAT as $catid => $scores) {
        $total = 0 + @$scores->atype + @$scores->ctype;
        $aratio = (@$scores->atype) ? @$scores->ascore / @$scores->atype * 100 : 0;
        $cratio = (@$scores->ctype) ? @$scores->cscore / @$scores->ctype * 100 : 0;
        $catstyle = (@$scores->atype) ? 'atype' : 'ctype' ;
        $html .= '<p><table width="'.$tablewidth.'" class="generaltable">';
        $html .= '<tr>';
        $html .= "<th width=\"50%\" class=\"qcategory $catstyle\">".$scores->name.'</th>';
        $html .= "<th align=\"center\" class=\"header c1 $catstyle\" width=\"25%\">%</th>";
        $html .= "<th align=\"center\" class=\"header c2 $catstyle\" width=\"25%\">#</th>";
        $html .= '</tr>';
        if (!empty($scores->atype)) {
            $html .= '<tr><td>Type A</td><td align="center">'.sprintf('%0.2f', $aratio).'</td><td align="center">'.@$scores->ascore.'/'.@$scores->atype.'</td></tr>';
        }
        if (!empty($scores->ctype)) {
            $html .= '<tr><td>Type C</td><td align="center">'.sprintf('%0.2f', $cratio).'</td><td align="center">'.@$scores->cscore.'/'.@$scores->ctype.'</td></tr>';
        }
        $html .= '</table></p>';
    }

    if ($output == 'html') {
        echo $html;
        echo '<center>';
        $options['id'] = $course->id;
        $options['userid'] = $user->id;
        $options['output'] = 'xls'; // ask for XLS
        $options['view'] = 'userattempt'; //
        $options['attemptid'] = $attemptid; //
        echo $OUTPUT->single_button(new moodle_url('/course/report/examtraining/index.php', $options), get_string('generateXLS', 'report_examtraining'), 'get');
        echo '</center>';

        if ($pdfinstalled) {
            echo '<center>';
            $options['id'] = $course->id;
            $options['userid'] = $user->id;
            $options['output'] = 'pdf'; // ask for PDF
            $options['view'] = 'userattempt'; //
            $options['attemptid'] = $attemptid; //
            $OUTPUT->single_button(new moodle_url('/report/examtraining/index.php', $options), get_string('generatePDF', 'report_examtraining'), 'get');
            echo '</center>';
        }
    } elseif ($output == 'pdf') {
        $pdffooter = str_replace('[[leftfooterinfo]]', fullname($user).' / '.$COURSE->fullname, $pdffooter);
    }

} else {
    $filename = 'examtraining_sessions_report_'.date('d-M-Y', time()).'.xls';
    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers
    $workbook->send($filename);

    $globalresults->from = 0;
    $globalresults->to = 0;
    $globalresults->elapsed = 0;
    $globalresults->events = 0;
    
    // preparing some formats
    $xls_formats = examtraining_reports_xls_formats($workbook);
    $worksheet = examtraining_reports_init_worksheet($user->id, $xls_formats, $workbook, array(0,3,70));
    $startrow = examtraining_reports_print_header_xls($worksheet, $user->id, $course->id, $globalresults, $xls_formats);
    
    foreach($EFFQS as $effg) {
        $startrow = examtraining_reports_print_questiondetail_xls($worksheet, $startrow, $effg, $xls_formats);
    }

    foreach ($QCAT as $catid => $scores) {
        $scores->total = 0 + @$scores->atype + @$scores->ctype;
        $scores->aratio = (@$scores->atype) ? @$scores->ascore / @$scores->atype * 100 : 0 ;
        $scores->cratio = (@$scores->ctype) ? @$scores->cscore / @$scores->ctype * 100 : 0 ;
        $startrow = examtraining_reports_print_catscores_xls($worksheet, $startrow, $scores, $xls_formats);
    }

    ob_end_clean();
    $workbook->close();
}
