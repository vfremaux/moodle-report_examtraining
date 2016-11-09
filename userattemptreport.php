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

defined('MOODLE_INTERNAL') || die();

/*
 * direct log construction implementation
 */

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$attemptid = required_param('attemptid', PARAM_INT);

ini_set('memory_limit', '256M');

// TODO : Secure userid access depending on proper capabilities.

// Don't give access to any unless coaches.
require_capability('report/examtraining:viewall', $context);

// Calculate start time.

// Get data.

$attempt = get_record('quiz_attempts', 'uniqueid', $attemptid);
$quiz = get_record('userquiz', 'id', $attempt->quiz);

$user = get_record('user', 'id', $attempt->userid);

$questionset = explode(',', $attempt->layout);
$realquestions = array();

foreach ($questionset as $qid) {
    if ($qid == 0 || $qid == '') {
        continue;
    }
    $realquestions[] = $qid;

    $q = $DB->get_record('question', 'id', $qid);

    // If randomized, need fetch the effective question.
    // TODO / Redraw, question_states not exists anymore.
    if (preg_match('/^random/', $q->qtype)) {
        if ($state = get_record('question_states', 'attempt', $attemptid, 'question', $q->id, 'event', 0)) {
            if (preg_match("/^{$q->qtype}(\\d+)-/", $state->answer, $matches)) {
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

// Print result.

$i = 1;

$atype = 0;
$ctype = 0;
$ascore = 0;
$cscore = 0;

global $qcategories;

$qcategories = array();
$effqs = array();

// Compute question and prepare some data for output (html or xls).
foreach ($realquestions as $qid) {

    // Get effective question for answers, check it is a straight forward question.
    if (array_key_exists($qid, $questionforwards)) {
        $effectiveqid = $questionforwards[$qid];
    } else {
        $effectiveqid = $qid;
    }

    $effq = $DB->get_record('question', array('id' => $effectiveqid));

    if (!array_key_exists($effq->category, $qcategories)) {
        $qcategories[$effq->category] = $DB->get_record('question_categories', array('id' => $effq->category));
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
    foreach ($effg->answers as $aid => $a) {
        if ($a->id == $answerid) {
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
    $effg->type = ($effq->defaultgrade == 1000) ? 'C' : 'A';
    $pix1 = '<img width="14" height="15" src="'.$CFG->wwwroot.'/blocks/userquiz_monitor/pix/c.png" />';
    $pix2 = '<img width="14" height="15" src="'.$CFG->wwwroot.'/blocks/userquiz_monitor/pix/a.png" />';
    $effg->typeoutput = ($effq->defaultgrade == 1000) ? $pix1 : $pix2;
    $e = '<div class="'.$givenanswerclass.'">'.$effg->answeredtext.'</div>';
    $effg->htmloutput .= get_string('givenanswer', 'report_examtraining', $e);
    $effg->htmloutput .= "Categorie : <span class=\"qcategory\">".$qcategories[$effq->category]->name.'</span><br/>';
    $effg->htmloutput .= "Type : <span class=\"qtype\">".$effg->typeoutput.'</span><br/>';
    $effg->htmloutput .= "Score : <span class=\"qscore\">".$effg->score.'</span><br/>';
    $effg->htmloutput .= '</td></tr>';
    $effg->htmloutput .= "</table>";

    $qcategories[$effq->category]->qs = @$qcategories[$effq->category]->qs + 1;
    if ($effq->defaultgrade == 1000) {
        $qcategories[$effq->category]->ctype = @$qcategories[$effq->category]->ctype + 1;
        $ctype++;
        if ($effg->score) {
            $cscore++;
            $qcategories[$effq->category]->cscore = @$qcategories[$effq->category]->cscore + 1;
        }
    } else {
        $qcategories[$effq->category]->atype = @$qcategories[$effq->category]->atype + 1;
        $atype++;
        if ($effg->score) {
            $ascore++;
            $qcategories[$effq->category]->ascore = @$qcategories[$effq->category]->ascore + 1;
        }
    }

    $effqs[$qid] = $effg;

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
    if (preg_match('/^([0-9]+)\.([0-9]+)\s/', $b->name, $matches)) {
        $bmajor = $matches[1];
        $bminor = $matches[2];
    } else {
        $bmajor = 1000;
        $bminor = 0;
    }
    if ($amajor * 100 + $aminor > $bmajor * 100 + $bminor) {
        return 1;
    }
    if ($amajor * 100 + $aminor < $bmajor * 100 + $bminor) {
        return -1;
    }
    return 0;
}

uasort($qcategories, 'sortcatsbyname');

$total = $atype + $ctype;
$aratio = ($atype) ? $ascore / $atype * 100 : 0;
$cratio = ($ctype) ? $cscore / $ctype * 100 : 0;

/*
 * Rastering output document
 */

if ($output == 'html' || $output == 'pdf') {
    // Time period form.

    $html .= '<h2>'.get_string('userinfo', 'report_examtraining').'</h2>';

    if ($output == 'html') {
        $html .= "<link rel=\"stylesheet\" href=\"reports.css\" type=\"text/css\" />";
        $html .= print_user($user, $COURSE, false, true);
    } else {
        $usergroups = groups_get_all_groups($COURSE->id, $user->id, 0, 'g.id, g.name');
        $groupsinfo = '';
        // Print group status.
        if (!empty($usergroups)) {
            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == get_current_group($COURSE->id)) {
                    $str = "<b>$str</b>";
                }
                $groupnames[] = $str;
            }
            $groupsinfo = implode(', ', $groupnames);
        }

        $html .= "<table width=\"$tablewidth\" class=\"generaltable\"><tr>";
        $html .= '<td width="20%">'.print_user_picture($user, $COURSE->id, null, 0, true, false).'</td>';
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

    foreach ($effqs as $effq) {
        $html .= $effq->htmloutput;
    }

    $html .= '<h2>'.get_string('questionsetscores', 'report_examtraining').'</h2>';

    // Now we can output category and type sums.
    $html .= "<p><table width=\"$tablewidth\" class=\"generaltable\">";
    $html .= '<tr>
                <th></th>
                <th align="center" class="header c1">%</th>
                <th align="center" class="header c2">#</th>
              </tr>';

    $html .= '<tr>';
    $html .= '<td>Type A</td>';
    $html .= '<td align="center">'.sprintf('%0.2f', $aratio).'</td>';
    $html .= '<td align="center">'.$ascore.'/'.$atype.'</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td>Type C</td>';
    $html .= '<td align="center">'.sprintf('%0.2f', $cratio).'</td>';
    $html .= '<td align="center">'.$cscore.'/'.$ctype.'</td>';
    $html .= '</tr>';

    $html .= '</table></p>';

    $html .= '<h2>'.get_string('categoryscores', 'report_examtraining').'</h2>';

    // Print category scores.
    foreach ($qcategories as $catid => $scores) {
        $total = 0 + @$scores->atype + @$scores->ctype;
        $aratio = (@$scores->atype) ? @$scores->ascore / @$scores->atype * 100 : 0;
        $cratio = (@$scores->ctype) ? @$scores->cscore / @$scores->ctype * 100 : 0;
        $catstyle = (@$scores->atype) ? 'atype' : 'ctype';
        $html .= '<p><table width="'.$tablewidth.'" class="generaltable">';

        $html .= '<tr>';
        $html .= '<th width="50%" class="qcategory '.$catstyle.'">'.$scores->name.'</th>';
        $html .= '<th align="center" class="header c1 '.$catstyle.'" width="25%">%</th>';
        $html .= '<th align="center" class="header c2 '.$catstyle.'" width="25%">#</th>';
        $html .= '</tr>';

        if (!empty($scores->atype)) {
            $html .= '<tr>';
            $html .= '<td>Type A</td>';
            $html .= '<td align="center">'.sprintf('%0.2f', $aratio).'</td>';
            $html .= '<td align="center">'.@$scores->ascore.'/'.@$scores->atype.'</td>';
            $html .= '</tr>';
        }
        if (!empty($scores->ctype)) {
            $html .= '<tr>';
            $html .= '<td>Type C</td>';
            $html .= '<td align="center">'.sprintf('%0.2f', $cratio).'</td>';
            $html .= '<td align="center">'.@$scores->cscore.'/'.@$scores->ctype.'</td>';
            $html .= '</tr>';
        }
        $html .= '</table></p>';
    }

    if ($output == 'html') {
        echo $html;
        echo '<center>';
        $options['id'] = $course->id;
        $options['userid'] = $user->id;
        $options['output'] = 'xls'; // Ask for XLS.
        $options['view'] = 'userattempt';
        $options['attemptid'] = $attemptid;
        $buttonurl = new moodle_url('/course/report/examtraining/index.php', $options);
        echo $OUTPUT->single_button($buttonurl, get_string('generateXLS', 'report_examtraining'), 'get');
        echo '</center>';

        if ($pdfinstalled) {
            echo '<center>';
            $options['id'] = $course->id;
            $options['userid'] = $user->id;
            $options['output'] = 'pdf'; // Ask for PDF.
            $options['view'] = 'userattempt';
            $options['attemptid'] = $attemptid;
            $buttonurl = new moodle_url('/report/examtraining/index.php', $options);
            $OUTPUT->single_button($buttonurl, get_string('generatePDF', 'report_examtraining'), 'get');
            echo '</center>';
        }
    } else if ($output == 'pdf') {
        $pdffooter = str_replace('[[leftfooterinfo]]', fullname($user).' / '.$COURSE->fullname, $pdffooter);
    }

} else {
    $filename = 'examtraining_sessions_report_'.date('d-M-Y', time()).'.xls';
    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers.
    $workbook->send($filename);

    $globalresults->from = 0;
    $globalresults->to = 0;
    $globalresults->elapsed = 0;
    $globalresults->events = 0;

    // Preparing some formats.
    $xlsformats = examtraining_reports_xls_formats($workbook);
    $worksheet = examtraining_reports_init_worksheet($user->id, $xlsformats, $workbook, array(0, 3, 70));
    $startrow = examtraining_reports_print_header_xls($worksheet, $user->id, $course->id, $globalresults, $xlsformats);

    foreach ($effqs as $effg) {
        $startrow = examtraining_reports_print_questiondetail_xls($worksheet, $startrow, $effg, $xlsformats);
    }

    foreach ($qcategories as $catid => $scores) {
        $scores->total = 0 + @$scores->atype + @$scores->ctype;
        $scores->aratio = (@$scores->atype) ? @$scores->ascore / @$scores->atype * 100 : 0;
        $scores->cratio = (@$scores->ctype) ? @$scores->cscore / @$scores->ctype * 100 : 0;
        $startrow = examtraining_reports_print_catscores_xls($worksheet, $startrow, $scores, $xlsformats);
    }

    ob_end_clean();
    $workbook->close();
}
