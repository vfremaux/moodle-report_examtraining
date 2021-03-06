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

use \moodle_url;
use \StdClass;
use \html_writer;
use \jqplot_renderer;
use \html_table;
use \context_course;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/report/examtraining/classes/jqplotter.php');
require_once($CFG->dirroot.'/local/vflibs/jqplotlib.php');

class html_renderer extends \plugin_renderer_base {

    /**
     * a raster for html printing an assiduity timeline.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    public function assiduity2($userid, $from, $to, $view) {
        global $DB, $COURSE, $OUTPUT;

        $pagesize = 31;

        $modulestr = get_string('assiduity', 'report_examtraining');
        $attemptsstr = get_string('attempts', 'report_examtraining');

        $examcontext = examtraining_get_context();

        $params = [$userid];
        $fromclause = '';
        if (!empty($form)) {
            $fromclause = ' AND qa.timefinish > ? ';
            $params[] = $from;
        }

        $toclause = '';
        if (!empty($to)) {
            $toclause = ' AND qa.timefinish < ? ';
            $params[] = $to;
        }

        // Compute attempts "per day".

        $sql = "
            SELECT
                UNIX_TIMESTAMP(DATE(FROM_UNIXTIME(timefinish))) as daystamp,
                COUNT(qa.id) as acount
            FROM
                {quiz_attempts} qa,
                {report_examtraining} ua
            WHERE
                qa.uniqueid = ua.uniqueid AND
                userid = ?
                {$fromclause}
                {$toclause}
            GROUP BY
                DAY(FROM_UNIXTIME(timefinish))
            HAVING
                daystamp != 0
            ORDER BY
                daystamp
        ";

        $assiduity = $DB->get_records_sql($sql, $params);

        $template = new Stdclass;

        if (empty($assiduity)) {
            $template->hasattempts = false;
            $template->noattemptsnotification = $OUTPUT->notification(get_string('noattempts', 'report_examtraining'));
            return $this->output->render_from_template('report_examtraining/assiduitygraph', $template);
        }
        $template->hasattempts = true;

        $stamps = array_keys($assiduity);
        $firstdate = $stamps[0];
        $lastdate = array_pop($stamps);

        $dateticks = array_keys($assiduity);

        $firstdate = $dateticks[0];
        $lastdate = $dateticks[count($dateticks) - 1];

        $viewstart = optional_param('assiduityfrom', 0, PARAM_INT);

        // Rebuild an unholed array for bargraphs.
        $start = $stamp = max($firstdate, $viewstart);
        $i = 0;
        $attemptstable = [];
        while ($i < $pagesize) {
            $date = strftime('%Y/%m/%d', (int)$stamp);
            if (array_key_exists($stamp, $assiduity)) {
                $attemptstable[$date] = $assiduity[$stamp]->acount;
            } else {
                $attemptstable[$date] = 0;
            }
            $stamp += DAYSECS;
            $i++;
        }

        if ($lastdate > $stamp || $start > $firstdate) {
            $template->paged = true;
            $template->previousiconurl = $this->output->pix_icon('previous', get_string('previous', 'report_examtraining'), 'report_examtraining');
            $template->nexticonurl = $this->output->pix_icon('next', get_string('next', 'report_examtraining'), 'report_examtraining');
            if ($lastdate > $stamp) {
                $params = array('id' => $COURSE->id, 'view' => $view, 'assiduityfrom' => $start + $pagesize * DAYSECS);
                $template->nexturl = new moodle_url('/report/examtraining/index.php', $params);
            }
            if ($start > $firstdate) {
                $params = array('id' => $COURSE->id, 'view' => $view, 'assiduityoffset' => $start - $pagesize * DAYSECS);
                $template->previousurl = new moodle_url('/report/examtraining/index.php', $params);
            }
        }
        $jqplotter = new jqplot_renderer();
        $label = get_string('assiduity', 'report_examtraining');
        $template->plot = $jqplotter->assiduity_bargraph($attemptstable, array_keys($attemptstable), $label, 'assiduity');

        return $this->output->render_from_template('report_examtraining/assiduitygraph', $template);
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     * TODO : Mark for obolescence.
     */
    public function assiduity($userid, $from, $to) {
        global $DB;

        $modulestr = get_string('assiduity', 'report_examtraining');
        $attemptsstr = get_string('attempts', 'userquiz');

        $examcontext = examtraining_get_context();

        $fromclause = ($from) ? " AND qa.timefinish > $from " : '';
        $toclause = ($to) ? " AND qa.timefinish < $to " : '';

        // Compute attempts "per day".

        $sql = "
            SELECT
                timefinish * 1000,
                COUNT(qa.id) as acount
            FROM
                {quiz_attempts} qa,
                {report_examtraining} ua
            WHERE
                qa.uniqueid = ua.uniqueid AND
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

            $lbl1 = get_string('assiduity', 'report_examtraining');
            $lbl2 = get_string('attemptquantity', 'report_examtraining');
            $str = vflibs_jqplot_print_timecurve_bars($assiduityarr, $lbl1, 'assiduity', $labels, $lbl2);
        }
        return $str;
    }

    /**
     * a raster for html printing of a report structure header
     * with all the relevant data about a user.
     * @param int $userid
     * @param int $courseid
     */
    public function header($userid, $courseid, $data, $isshort = false) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $userid));
        $course = $DB->get_record('course', array('id' => $courseid));

        $template = new StdClass;

        $template->fullname = fullname($user);

        // Get group.
        $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');
        // Print group status.

        $template->usergroups = '';
        if (!empty($usergroups)) {
            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == groups_get_course_group($courseid)) {
                    $str = "<b>$str</b>";
                }
                $groupnames[] = $str;
            }
            $template->usergroups = implode(', ', $groupnames);
        }

        $template->email = $user->email;
        $template->city = $user->city;
        $template->isshort = $isshort;
        if ($isshort) {
            $params = array('view' => 'user', 'id' => $courseid, 'userid' => $userid);
            $template->detailsurl = new moodle_url('/report/examtraining/index.php', $params);
            $template->detailsstr = get_string('seedetails', 'report_examtraining');
        }

        return $this->output->render_from_template('report_examtraining/userheading', $template);
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param int $userid the user for wich the report needs to be computed
     * @param int $from the from start date
     * @param int $to the to start date
     * @param object $height graph height
     * @param arrayref $stats the overal stats calcualted internally and provided for further use.
     */
    public function trainings_globals($userid, $from, $to, $height = 'large', &$stats) {
        global $CFG;

        $examcontext = examtraining_get_context();

        if (is_null($stats)) {
            $stats = userquiz_get_user_globals($userid, $examcontext->trainingquizzes, $from, $to);
        }

        $str = '';

        $jqplotter = new jqplot_renderer();
        $label = get_string('overalhitstraining', 'report_examtraining');
        $str .= $jqplotter->horiz_bar_headgraph($stats[$userid], $label, 'overalhitstraining', $height);

        $aratiostr = get_string('ratioa', 'report_examtraining');
        $cratiostr = get_string('ratioc', 'report_examtraining');
        $acountstr = get_string('counta', 'report_examtraining');
        $ccountstr = get_string('countc', 'report_examtraining');
        $table = new html_table();
        $table->head = array("<b>$aratiostr</b>", "<b>$cratiostr</b>", "<b>$acountstr</b>", "<b>$ccountstr</b>");
        $table->size = array('25%', '25%', '25%', '25%');
        $table->width = '90%';
        $table->align = array('center', 'center', 'center', 'center');
        $table->data[] = array((@$stats[$userid]->ahitratio + 0).' %',
                               (@$stats[$userid]->chitratio + 0).' %',
                               @$stats[$userid]->aanswered + 0,
                               @$stats[$userid]->canswered + 0);

        $str .= html_writer::table($table);

        return $str;
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    public function knowledge_covering($userid, $courseid, $from, $to) {

        $template = new StdClass;

        $template->heading = $this->output->heading(get_string('knowledgecovering', 'report_examtraining'));
        $template->generatorurl = new moodle_url('/report/examtraining/gdgenerators/knowledgetag.php', $params);
        $template->width = '500px';
        $template->height = '500px';
        $template->legendstr = $this->output->box(get_string('knowledgecoveringlegend', 'report_examtraining'));

        return $this->output->render_from_template('report_examtraining/reportgraphicitem', $template);
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    public function modules($userid, $from, $to) {
        global $OUTPUT;

        $modulestr = get_string('seriesize', 'report_examtraining');
        $attemptsstr = get_string('series', 'report_examtraining');

        $modulecount = examtraining_get_module_count($userid, $from, $to);

        $template = new StdClass;

        $template->hasattempts = true;
        if ($modulecount == 0) {
            $template->hasattempts = false;
            $template->noattemptsnotification = $OUTPUT->notification(get_string('noattempts', 'report_examtraining'));
        } else {
            $jqplotter = new jqplot_renderer();
            $template->modules = $jqplotter->modules_bargraph($modulecount, get_string('permodule', 'report_examtraining'), 'permodule');
        }

        return $OUTPUT->render_from_template('report_examtraining/permodules', $template);
    }

    /**
     * a raster for html printing of a radar.
     *
     * @param array $data 12 categories mastering array
     */
    public function radar($userid, $from, $to, $title = '') {
        global $DB;

        $examcontext = examtraining_get_context();

        // Get mastering indicators in subcategories.
        $subcats = new Stdclass;
        $subcatdata = count_questions_in_categories_rec($examcontext->rootcategory, $subcats);
        $quizzes = implode(',', $examcontext->trainingquizzes);
        $matched = userquiz_get_user_subcats($userid, $quizzes, $from, $to);

        debug_print_for_user('admin', $examcontext->rootcategory);
        debug_print_for_user('admin', $subcatdata);

        // For each root cat, calculate the hitratio from subcats.
        $maincats = $DB->get_records('question_categories', array('parent' => $examcontext->rootcategory), 'sortorder', 'id, name');

        foreach ($matched as $catid => $catcounters) {
            if (in_array($catid, array_keys($maincats))) {
                
            }
        }

        $radardata = array();
        $radarheaders = array();
        if ($matched) {
            foreach ($maincats as $id => $cat) {
                if (!empty($matched[$cat->id]->qcount)) {
                    $allmatched = (@$matched[$cat->id]->amatched + @$matched[$cat->id]->cmatched);
                    $overalratio = $allmatched / $matched[$cat->id]->qcount * 100;
                } else {
                    $overalratio = 0;
                }
                $radardata[] = $overalratio;
                $radarheaders[] = substr($cat->name, 0, 14);
            }
        }

        $template = new StdClass;
        if (!empty($title)) {
            $template->title = $title;
        }

        debug_print_for_user('admin', $radardata);

        $radararg = implode(',', $radardata);
        $headersarg = implode(',', $radarheaders);
        $params = array('radar' => $radararg, 'headers' => $headersarg);
        $template->generatorurl = new moodle_url('/report/examtraining/gdgenerators/radargraph.php', $params);
        $template->width = '500px';
        $template->height = '500px';
        $template->legendstr = '';

        return $this->output->render_from_template('report_examtraining/reportgraphicitem', $template);
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
        $subcats = $DB->get_records('question_categories', $params, 'sortorder', 'id, name');

        $str = '';

        if (!$stats = userquiz_get_weekly_globals($userid, $examcontext->trainingquizzes, $from, $to)) {
            $str .= $this->output->heading(get_string('traininghits', 'report_examtraining'));
            $str .= $this->output->notification(get_string('notrainingactivity', 'report_examtraining'));
            return $str;
        }

        // This prints an histogram on training result categories.
        $str = '<center>';

        // Preformat data.
        $statsrawarr = array_values($stats);
        foreach ($statsrawarr as $stat) {
            $firstdayinyear = mktime(0, 0, 0, 1, 1, $statsrawarr[0]->year);
            $statdate = strftime('%Y/%m/%d %H:%M', ($firstdayinyear + $stat->week * 7 * DAYSECS));
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
        $str .= local_vflibs_jqplot_print_timecurve_bars($data, $label, 'globalprogress', $labels, true);
        $str .= "</center>";

        return $str;
    }

    public function trainings_subcats($userid, $from, $to, $printmode = 'table') {
        global $CFG, $DB;

        $examcontext = examtraining_get_context();

        if (!$stats = userquiz_get_attempts_subcats($userid, $examcontext->trainingquizzes, $from, $to)) {
            $str = $this->output->heading(get_string('traininghitspercategory', 'report_examtraining'));
            $str .= $this->output->notification(get_string('notrainingactivity', 'report_examtraining'));
            return $str;
        }

        $str = $this->output->heading(get_string('traininghitspercategory', 'report_examtraining'));
        $str .= '<center>';
        $statsrawarr = array_values($stats);
        $cats = [];
        $datacats = [];
        $datacatstot = [];
        foreach ($statsrawarr as $stat) {
            $firstdayinyear = mktime(0, 0, 0, 1, 1, $statsrawarr[0]->year);
            $statdate = ($firstdayinyear + $stat->week * 7 * DAYSECS);
            $subcatid = $stat->categoryid;
            if (!array_key_exists($subcatid, $datacats)) {
                $datacats[$subcatid] = [];
            }
            if (!array_key_exists($statdate, $datacats[$subcatid])) {
                $datacats[$subcatid][$statdate] = new STdClass;
            }

            $datacats[$subcatid][$statdate]->ccount = @$datacats[$subcatid][$statdate]->ccount + $stat->ccount;
            $datacats[$subcatid][$statdate]->acount = @$datacats[$subcatid][$statdate]->acount + $stat->acount;
            $datacats[$subcatid][$statdate]->cmatched = @$datacats[$subcatid][$statdate]->cmatched + $stat->cmatched;
            $datacats[$subcatid][$statdate]->amatched = @$datacats[$subcatid][$statdate]->amatched + $stat->amatched;

            if (!array_key_exists($subcatid, $datacatstot)) {
                $datacatstot[$subcatid] = new StdClass;
            }

            $datacatstot[$subcatid]->ccount = @$datacatstot[$subcatid]->ccount + $stat->ccount;
            $datacatstot[$subcatid]->acount = @$datacatstot[$subcatid]->acount + $stat->acount;
            $datacatstot[$subcatid]->cmatched = @$datacatstot[$subcatid]->cmatched + $stat->cmatched;
            $datacatstot[$subcatid]->amatched = @$datacatstot[$subcatid]->amatched + $stat->amatched;
        }

        uksort($datacats, array('\\report_examtraining\\output\\html_renderer', 'sortbysubcatname'));

        $subcatshitratios = array();
        // Post compile hitratios.
        foreach ($datacats as $subcatid => $datacatarr) {
            if (!array_key_exists($subcatid, $cats)) {
                $cats[$subcatid] = new StdClass;
            }
            $cats[$subcatid]->count = 0 + @$datacatstot[$subcatid]->ccount + @$datacatstot[$subcatid]->acount;
            $cats[$subcatid]->matched = 0 + @$datacatstot[$subcatid]->cmatched + @$datacatstot[$subcatid]->amatched;
            $ratio = ($cats[$subcatid]->count) ? $cats[$subcatid]->matched / ($cats[$subcatid]->count) * 100 : 0;
            $cats[$subcatid]->ratio = ($cats[$subcatid]->count) ? round($ratio) : 0;
        }

        if ($printmode == 'jqplot') {
            $label = get_string('questionspercategories', 'report_examtraining');
            jqplot_print_timecurve_subcats($subcatshitratios, $label, 'questionspercategories', 'multiplot');
        } else {
            $categorystr = get_string('categoryname', 'report_examtraining');
            $ratiostr = get_string('ratio', 'report_examtraining');

            // Per category result.
            $table = new html_table();
            $table->head = array("<b>$categorystr</b>", "<b>$ratiostr</b>");
            $table->size = array('80%', '20%');
            $table->width = '90%';
            $table->align = array('left', 'left');
            foreach ($cats as $catid => $cat) {
                $catname = $DB->get_field('question_categories', 'name', ['id' => $catid]);
                $table->data[] = array($catname, $cats[$catid]->ratio. '% ('.$cats[$catid]->matched.'/'.$cats[$catid]->count.')');
            }

            $this->output->heading(get_string('traininghits', 'report_examtraining'));
            $str .= html_writer::table($table);
        }
        $str .= '</center>';

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

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    public function exams($userid, $from, $to) {
        global $COURSE;

        $examcontext = examtraining_get_context();
        $context = context_course::instance($COURSE->id);

        if (!$stats = userquiz_get_attempts_stats($userid, $examcontext->examquiz, $from, $to)) {
            return;
        }

        $template = new StdClass;

        $template->heading = $this->output->heading(get_string('examtries', 'report_examtraining'));
        $template->examtriesstr = get_string('examtries', 'report_examtraining');

        $badurl = $this->output->image_url('bad', 'report_examtraining');
        $goodurl = $this->output->image_url('good', 'report_examtraining');

        ksort($stats);
        $i = 1;
        $previous = null;
        foreach ($stats as $attemptid => $attemptres) {
            $examtrytpl = new StdClass;
            $examtrytpl->finishdate = date('d/m/y', $attemptres->timefinish);
            $params = array('id' => $COURSE->id, 'attemptid' => $attemptid, 'view' => 'userattempt');
            $examtrytpl->examurl = new moodle_url('/report/examtraining/index.php', $params);
            $examtry->ahitratio = round(0 + @$attemptres->ahitratio * 100);
            $examtry->chitratio = round(0 + @$attemptres->chitratio * 100);
            if ($attemptres->ahitratio < 0.85 || $attemptres->chitratio < 0.75) {
                $examtry->resulticonurl = $badurl;
            } else {
                $examtry->resulticonurl = $goodurl;
            }
            $examtrytpl->islink = false;
            if (has_capability('report/examtraining:viewall', $context)) {
                $examtrytpl->islink = true;
            }

            $template->examtries[] = $examtrytpl;
        }

        return $this->output->render_from_template('report_examtraining/examtries', $template);
    }

    /**
     * a raster for html printing of a report structure global header
     * with all the relevant data about a user.
     */
    public function globalheader($userid, $courseid, &$data) {
        global $COURSE, $DB;

        $user = $DB->get_record('user', array('id' => $userid));
        if ($COURSE->id != $courseid) {
            $course = $DB->get_record('course', array('id' => $courseid));
        } else {
            $course = &$COURSE;
        }

        $template = new StdClass;

        $template->userpicture = $this->output->user_picture($user);

        $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

        // Print group status.
        if (!empty($usergroups)) {
            $template->hasgroups = true;
            $template->usergroupsstr = get_string('groups');

            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == groups_get_course_group($courseid)) {
                    $str = '<b>'.$str.'</b>';
                }
                $groupnames[] = $str;
            }

            $template->usergroups = implode(', ', $groupnames);
        }
        $template->rolesstr = get_string('roles');

        $template->userroles = get_user_roles_in_course($userid, $courseid);

        $params = array('view' => 'user', 'id' => $courseid, 'userid' => $userid);
        $template->examreporturl = new moodle_url('/report/examtraining/index.php', $params);
        $template->seedetailsstr = get_string('seedetails', 'report_examtraining');

        $template->equlearningtimestr = get_string('equlearningtime', 'report_examtraining');
        $template->elapsed = examtraining_reports_format_time(0 + @$data->elapsed, 'html');

        return $this->output->render_from_template('report_examtraining/globalheading', $template);
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

        $options = array('width' => 600,
                         'height' => 600,
                         'xlabel' => get_string('coverage', 'report_examtraining'),
                         'ylabel' => get_string('ratio', 'report_examtraining'));
        $label = get_string('grouplocation', 'report_examtraining');
        return local_vflibs_jqplot_print_labelled_graph($data, $label, 'examtries', $options);
    }

    /**
     *
     */
    public function time_selector_form($from) {
        global $COURSE;

        $template = new StdClass;

        $template->courseid = $COURSE->id;
        $template->fromstr = get_string('from');

        $template->fromdateselector = print_date_selector('startday', 'startmonth', 'startyear', $from);
        $template->updatestr = get_string('update');
        $template->jshandler = 'document.forms[\'selector\'].fromstart.value = 1;document.forms[\'selector\'].submit();';
        $template->buttonlabel = get_string('updatefromcoursestart', 'report_trainingsessions');

        return $this->output->render_from_template('report_examtraining/timeselectorform', $template);
    }

    /**
     *
     *
     */
    public function questionstats($orderby) {
        global $COURSE, $USER, $DB;

        $examcontext = examtraining_get_context();

        $sql = "
            SELECT
                q.id,
                q.name,
                SUM(usecount > 0) as usecount,
                SUM(matchcount > 0) as matchcount
            FROM
                {question} q,
                {userquiz_monitor_coverage} qc
            WHERE
                qc.questionid = q.id AND
                blockid = ?
            GROUP BY
                questionid
            ORDER BY
                q.name
        ";

        $questionlines = $DB->get_records_sql($sql, array($examcontext->instanceid));

        if (!empty($questionlines)) {
            $i = 1;
            foreach ($questionlines as $elm) {
                $data[0][0][] = $i;
                $data[0][1][] = $elm->usecount;
                $data[0][2][] = preg_replace('/\s.*$/', '', $elm->name);
                $data[1][$i] = $elm->matchcount;
                $data[2][$i] = round(($elm->usecount - $elm->matchcount) / $elm->usecount * 100);
                $i++;
            }

            $jqplotter = new jqplot_renderer();
            $str = $jqplotter->questionuse_graph($data, get_string('questionusage', 'report_examtraining'), 'quse');
        } else {
            return $this->output->notification(get_string('noquestionsusage', 'report_examtraining'));
        }

        $data = array();
        for ($i = 0; $i <= 20; $i++) {
            $data[''.($i * 5)] = 0;
        }

        foreach ($questionlines as $elm) {
            $errorratio = round(($elm->usecount - $elm->matchcount) / $elm->usecount * 100);
            $data[''.(round($errorratio / 20 * 4) * 5)] = @$data[''.(round($errorratio / 20 * 4)) * 5] + 1;
            $i++;
        }

        $arr = array();
        $str .= local_vflibs_jqplot_print_simple_bargraph($data, $arr, get_string('errorratio', 'report_examtraining'), 'qerrors');

        $sql = "
            SELECT
                qc.questionid,
                q.name,
                q.createdby,
                (SUM(usecount > 0) - SUM(matchcount > 0)) / SUM(usecount > 0) * 100 AS errorrate,
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

        if ($errorquestions = $DB->get_records_sql($sql, array($examcontext->instanceid))) {

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
                $caneditq = has_capability('moodle/question:editall', context_course::instance($COURSE->id)) ||
                        ($errq->createdby = $USER->id &&
                                has_capability('moodle/question:editmine', context_course::instance($COURSE->id)));
                $qname = ($caneditq) ? '<a href="'.$qlink.'">'.$errq->name.'</a>' : $errq->name;
                $table->data[] = array($errq->errorrate, $errq->totaluse, $qname);
            }
            $str .= html_writer::table($table);
        }

        return $str;
    }

    public function times($userid, &$data) {
        global $DB, $OUTPUT;

        $str = '';

        $loginfo = examtraining_get_log_reader_info();

        $str .= $OUTPUT->heading(get_string('times', 'report_examtraining'));

        $select = " action = ? AND userid = ? ";
        $params = array($loginfo->loggedin, $userid);
        $firstaccess = 0 + $DB->get_field_select($loginfo->table, 'MIN('.$loginfo->timeparam.')', $select, $params);
        $lastaccess = 0 + $DB->get_field_select($loginfo->table, 'MAX('.$loginfo->timeparam.')', $select, $params);
        $cnx = new StdClass;
        $cnx->count = $DB->count_records_select($loginfo->table, $select, $params);
        $tendaysbefore = time() - DAYSECS * 10;
        $select = "  action = ? AND userid = ? AND ".$loginfo->timeparam." > ? ";
        $cnx->lastcount = $DB->count_records_select($loginfo->table, $select, array($loginfo->loggedin, $userid, $tendaysbefore));

        // First row.
        $str .= '<table width="100%" style="border:1px solid #A0A0A0;padding:2px" cellspacing="2">';
        $str .= '<tr>';
        $str .= '<td align="left"><b>';
        $str .= get_string('firstaccess', 'report_examtraining');
        $str .= ' : </b></td>';
        $str .= '<td align="left">';
        $str .= userdate($firstaccess);
        $str .= '</td>';
        $str .= '<td align="left"><b>';
        $str .= get_string('lastaccess', 'report_examtraining');
        $str .= ' : </b></td>';
        $str .= '<td align="left">';
        $str .= userdate($lastaccess);
        $str .= '</td>';
        $str .= '</tr>';

        // Second row.
        $str .= '<tr>';
        $str .= '<td align="left"><b>';
        $str .= get_string('connections', 'report_examtraining');
        $str .= ' : </b></td><td colspan="3" align="left">';
        $str .= get_string('connectionscount', 'report_examtraining', $cnx);
        $str .= '</td>';
        $str .= '</tr>';

        // Third row.
        // Start printing the overall times.
        $str .= '<tr>';
        $str .= '<td align="left"><b>';
        $str .= get_string('equlearningtime', 'report_examtraining');
        $str .= '</b></td><td colspan="3" align="left">';
        $str .= examtraining_reports_format_time(0 + @$data->elapsed, 'html');
        $str .= '</td>';
        $str .= '</tr>';
        $str .= '</table>';

        return $str;
    }

    public static function sortbysubcatname($a, $b) {
        global $DB;

        $subcatordera = $DB->get_field('question_categories', 'sortorder', array('id' => $a));
        $subcatorderb = $DB->get_field('question_categories', 'sortorder', array('id' => $b));
        if ($subcatordera == $subcatorderb) {
            return 0;
        }
        if ($subcatordera > $subcatorderb) {
            return 1;
        }
        return -1;
    }
}
