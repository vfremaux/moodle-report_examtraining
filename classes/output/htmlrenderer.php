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
    public function assiduity2($userid, $from = NULL, $to = NULL, $view) {
        global $DB, $COURSE, $OUTPUT;

        $pagesize = 31;

        $modulestr = get_string('assiduity', 'report_examtraining');
        $attemptsstr = get_string('attempts', 'report_examtraining');

        $examcontext = block_userquiz_monitor_get_block($COURSE->id)->config;

        $params = [$userid];
        $params[] = $COURSE->id;
        $fromclause = '';
        if (!is_null($from)) {
            $fromclause = ' AND ua.attemptdate > ? ';
            $params[] = $from;
        }

        $toclause = '';
        if (!is_null($to)) {
            $toclause = ' AND ua.attemptdate <= ? ';
            $params[] = $to;
        }

        // Compute number of attempts "per day".

        $sql = "
            SELECT
                UNIX_TIMESTAMP(DATE(FROM_UNIXTIME(attemptdate))) as daystamp,
                COUNT(*) as acount
            FROM
                {report_examtraining} ua
            WHERE
                userid = ? AND
                course = ? AND
                questionid IS NULL AND
                categoryid IS NULL
                {$fromclause}
                {$toclause}
            GROUP BY
                DAY(FROM_UNIXTIME(attemptdate))
            HAVING
                acount != 0
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
            $template->previousicon = $this->output->pix_icon('previous', get_string('previous', 'report_examtraining'), 'report_examtraining', ['class' => 'xlarge']);
            $template->nexticon = $this->output->pix_icon('next', get_string('next', 'report_examtraining'), 'report_examtraining', ['class' => 'xlarge']);
            $template->previousiconshadow = $this->output->pix_icon('previous', get_string('previous', 'report_examtraining'), 'report_examtraining', ['class' => 'dimmed shadow xlarge']);
            $template->nexticonshadow = $this->output->pix_icon('next', get_string('next', 'report_examtraining'), 'report_examtraining', ['class' => 'dimmed shadow xlarge']);
            if ($lastdate > $stamp) {
                $params = [
                    'id' => $COURSE->id,
                    'view' => $view,
                    'assiduityfrom' => $start + $pagesize * DAYSECS,
                    'userid' => $userid,
                    'from' => $from,
                    'to' => $to
                ];
                $template->nexturl = new moodle_url('/report/examtraining/index.php', $params);
            }
            if ($start > $firstdate) {
                $params = [
                    'id' => $COURSE->id,
                    'view' => $view,
                    'assiduityoffset' => $start - $pagesize * DAYSECS,
                    'userid' => $userid,
                    'from' => $from,
                    'to' => $to
                ];
                $template->previousurl = new moodle_url('/report/examtraining/index.php', $params);
            }
        }
        $jqplotter = new jqplot_renderer();
        $title = get_string('assiduity', 'report_examtraining');
        $template->plot = $jqplotter->assiduity_bargraph($attemptstable, array_keys($attemptstable), $title, 'assiduity');

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
                if ($group->id == groups_get_course_group($course)) {
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
    public function trainings_globals($userid, $from = null, $to = null, $height = 'large', &$stats) {
        global $CFG, $COURSE;

        $compiler = new \report_examtraining\stats\compiler();

        $examcontext = block_userquiz_monitor_get_block($COURSE->id)->config;

        if (is_null($stats)) {
            $stats = $compiler->get_user_globals($userid, $COURSE->id, $from, $to, 0, false);
        }

        if (empty($examcontext->dualseries)) {
            unset($stats[$userid]->cmatched);
            unset($stats[$userid]->ccount);
        }

        $str = '';

        $jqplotter = new jqplot_renderer();
        $label = get_string('overalhitstraining', 'report_examtraining');
        $str .= $jqplotter->horiz_bar_headgraph($stats[$userid], $label, 'overalhitstraining', $height);

        $ratiostr = get_string('ratio', 'report_examtraining');
        $aratiostr = get_string('ratioa', 'report_examtraining');
        $cratiostr = get_string('ratioc', 'report_examtraining');
        $countstr = get_string('count', 'report_examtraining');
        $acountstr = get_string('counta', 'report_examtraining');
        $ccountstr = get_string('countc', 'report_examtraining');
        $table = new html_table();
        if (!empty($examcontext->dualseries)) {
            $table->head = array("<b>$aratiostr</b>", "<b>$cratiostr</b>", "<b>$acountstr</b>", "<b>$ccountstr</b>");
            $table->size = array('25%', '25%', '25%', '25%');
        } else {
            $table->head = array("<b>$ratiostr</b>", "<b>$countstr</b>");
            $table->size = array('50%', '50%');
        }
        $table->width = '90%';
        $table->align = array('center', 'center', 'center', 'center');
        if (!empty($examcontext->dualseries)) {
            $table->data[] = [
                ($stats[$userid]->aratio ?? 0).' %',
                ($stats[$userid]->cratio ?? 0).' %',
                $stats[$userid]->acount ?? 0,
                $stats[$userid]->ccount ?? 0
            ];
        } else {
            $table->data[] = [
                ($stats[$userid]->aratio ?? 0).' %',
                $stats[$userid]->acount ?? 0,
            ];
        }

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
        $compiler = new \report_examtraining\stats\compiler();

        $modulecount = $compiler->get_attempts_per_size($userid, $from, $to);

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
     * a raster for html printing a per category coverage radar plot.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $from start of time range
     * @param int $to end of time range
     * @param string $title radar title (opt).
     */
    public function coverage_radar($userid, $from = null, $to = null, $title = '', $set = 'q') {
        global $DB, $COURSE;
        static $matched;

        $theblock = block_userquiz_monitor_get_block($COURSE->id);
        $examcontext = $theblock->config;
        $compiler = new \report_examtraining\stats\compiler();

        // Get mastering indicators in subcategories.
        // TODO : fix why we do not get distinct results.
        $matched = $compiler->get_attempts_subcats($userid, $COURSE->id, $from, $to, 0 /* distinct */);

        // For each root cat, calculate the hitratio from subcats.
        $maincats = block_userquiz_monitor_get_top_cats($theblock, true /* withcatcount */);

        $kcount = $set.'count';
        $kmatch = $set.'matched';

        $radardata = [];
        $radardatamatched = [];
        $radarheaders = [];
        $branches = count($maincats);
        if ($matched) {
            foreach ($maincats as $catid => $cat) {
                // Note : min is a security value cropping until we fix the distinct count.
                $radardata[] = sprintf('%d', (!empty($cat->$kcount)) ? 0 + min(1, @$matched[$catid]->$kcount / $cat->$kcount) * 100 : 0);
                $radardatamatched[] = sprintf('%d', (!empty($cat->$kcount)) ? 0 + min(1, @$matched[$catid]->$kmatch / $cat->$kcount) * 100 : 0);
                $radarheaders[] = substr($cat->name, 0, 20);
            }
        }

        $template = new StdClass;
        if (!empty($title)) {
            $template->title = $title;
        }

        $w = 600;
        $h = 400;
        $fcolormatched = '#A0D045';
        $bcolormatched = '#779230';
        $fcolor = '#6251D0';
        $bcolor = '#453992';

        $radararg = implode(',', $radardata);
        $radarargmatched = implode(',', $radardatamatched);
        $headersarg = implode(',', $radarheaders);
        $params = [
            'branches' => $branches,
            'radar' => $radararg,
            'headers' => $headersarg,
            'fcolor' => $fcolor,
            'bcolor' => $bcolor,
            'width' => $w,
            'height' => $h
        ];
        $template->generatorurl = new moodle_url('/report/examtraining/gdgenerators/radargraph.php', $params);
        $params = [
            'branches' => $branches,
            'radar' => $radarargmatched,
            'headers' => $headersarg,
            'fcolor' => $fcolormatched,
            'bcolor' => $bcolormatched,
            'width' => $w,
            'height' => $h
        ];
        $template->generatorurlmatched = new moodle_url('/report/examtraining/gdgenerators/radargraph.php', $params);
        $template->width = $w.'px';
        $template->height = $h.'px';
        $template->legendstr = get_string('answered', 'report_examtraining');
        $template->legendmatchedstr =  get_string('matched', 'report_examtraining');

        return $this->output->render_from_template('report_examtraining/reportgraphicitem', $template);
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    public function trainings($userid, $from, $to) {
        global $DB, $OUTPUT, $COURSE;

        $examcontext = block_userquiz_monitor_get_block($COURSE->id)->config;
        $params = ['parent' => $examcontext->rootcategory];
        $subcats = $DB->get_records('question_categories', $params, 'sortorder', 'id, name');
        $compiler = new \report_examtraining\stats\compiler();

        $str = '';

        $stats = $compiler->get_weekly_globals($userid, $COURSE->id, $from, $to, 0);
        if (empty($stats)) {
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
            if (!empty($examcontext->dualserie)) {
                // Two curves
                $data[1][] = $examcontext->rateAserie;
                $data[2][] = $examcontext->rateCserie;
                $data[3][] = $stat->aratio;
                $data[4][] = $stat->cratio;
            } else {
                // One curve
                $data[1][] = $examcontext->rateAserie;
                $data[2][] = $stat->aratio;
            }
        }
        $labels = [];
        $labels[] = [
                'label' => get_string('athreshold', 'report_examtraining'),
                'lineWidth' => 2,
                'color' => '#FF6666',
                'showMarker' => 'false',
                'showLabel' => 'false',
                'renderer' => '$.jqplot.LineRenderer',
            ];
        if (!empty($examcontext->dualserie)) {
            $labels[] = [
                'label' => get_string('cthreshold', 'report_examtraining'),
                'lineWidth' => 2,
                'color' => '#6666FF',
                'showMarker' => 'false',
                'showLabel' => 'false',
                'renderer' => '$.jqplot.LineRenderer',
            ];
        }
        $labels[] = [
                'label' => get_string('ratioa', 'report_examtraining'),
                'lineWidth' => 3,
                'color' => '#E00000',
                'showMarker' => 'true',
                'renderer' => '$.jqplot.BarRenderer',
            ];
        if (!empty($examcontext->dualserie)) {
            $labels[] = [
                'label' => get_string('ratioc', 'report_examtraining'),
                'lineWidth' => 3,
                'color' => '#0000E0',
                'showMarker' => 'true',
                'renderer' => '$.jqplot.BarRenderer',
            ];
        }
        $label = get_string('globalprogress', 'report_examtraining');
        $ylabel = get_string('hitscore', 'report_examtraining');
        $str .= local_vflibs_jqplot_print_timecurve_bars($data, $label, 'globalprogress', $labels, $ylabel);
        $str .= "</center>";

        return $str;
    }

    /**
     * prints a table with all subcategory results.
     * @param int userid the user
     * @param int $from
     * @param int $to
     * @param string $printmode 'table' or 'jqplot'
     */
    public function trainings_subcats($userid, $from = null, $to = null, $printmode = 'table') {
        global $CFG, $DB, $COURSE;

        $examcontext = block_userquiz_monitor_get_block($COURSE->id)->config;
        $compiler = new \report_examtraining\stats\compiler();

        if (!$stats = $compiler->get_attempts_subcats($userid, $COURSE->id, $from, $to)) {
            $str = $this->output->heading(get_string('traininghitspercategory', 'report_examtraining'));
            $str .= $this->output->notification(get_string('notrainingactivity', 'report_examtraining'));
            return $str;
        }

        $str = $this->output->heading(get_string('traininghitspercategory', 'report_examtraining'));
        $str .= '<center>';

        if ($printmode == 'jqplot') {
            $label = get_string('questionspercategories', 'report_examtraining');
            jqplot_print_timecurve_subcats($stats, $label, 'questionspercategories', 'multiplot');
        } else {
            $categorystr = get_string('categoryname', 'report_examtraining');
            $ratiostr = get_string('ratio', 'report_examtraining');

            // Per category result.
            $table = new html_table();
            $table->head = ["<b>$categorystr</b>", "<b>$ratiostr</b>"];
            $table->size = ['80%', '20%'];
            $table->width = '90%';
            $table->align = array('left', 'left');
            foreach ($stats as $catid => $stat) {
                $catname = $DB->get_field('question_categories', 'name', ['id' => $catid]);
                $table->data[] = [$catname, $stats[$catid]->qratio. '% ('.$stats[$catid]->qmatched.'/'.$stats[$catid]->qcount.')'];
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
        global $COURSE;

        $compiler = new \report_examtraining\stats\compiler();

        $examcontext = block_userquiz_monitor_get_block($COURSE->id)->config;

        if (!$stats = $compiler->get_user_globals($userid, $COURSE->id, $from, $to)) {
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

        $examcontext = block_userquiz_monitor_get_block($COURSE->id)->config;
        $context = context_course::instance($COURSE->id);
        $compiler = new \report_examtraining\stats\compiler();

        if (!$stats = $compiler->get_attempts_stats($userid, $COURSE->id, $from, $to, 0, 'exam')) {
            return;
        }

        $template = new StdClass;

        $template->heading = $this->output->heading(get_string('examtries', 'report_examtraining'));
        $template->examtriesstr = get_string('examtries', 'report_examtraining');

        $badurl = $this->output->image_url('bad', 'report_examtraining');
        $goodurl = $this->output->image_url('good', 'report_examtraining');

        $i = 1;
        $previous = null;
        foreach ($stats as $attemptid => $stat) {
            $examtrytpl = new StdClass;
            $examtrytpl->finishdate = date('d/m/y', $stat->attemptdate);
            $params = array('id' => $COURSE->id, 'attemptid' => $attemptid, 'view' => 'userattempt');
            $examtrytpl->examurl = new moodle_url('/report/examtraining/index.php', $params);
            $examtrytpl->aratio = $stat->aratio;
            $examtrytpl->cratio = $stat->cratio;
            $examtrytpl->dualserie = !empty($examcontext->dualserie);
            if (!empty($examtrytpl->dualserie)) {
                if ($stat->aratio < $examcontext->rateAserie || $stat->cratio < $examcontext->rateCserie) {
                    $examtrytpl->resulticonurl = $badurl;
                } else {
                    $examtrytpl->resulticonurl = $goodurl;
                }
            } else {
                if ($stat->aratio < $examcontext->rateAserie) {
                    $examtrytpl->resulticonurl = $badurl;
                } else {
                    $examtrytpl->resulticonurl = $goodurl;
                }
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

        $user = $DB->get_record('user', ['id' => $userid]);
        if ($COURSE->id != $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
        } else {
            $course = &$COURSE;
        }

        $template = new StdClass;

        $template->userpicture = $this->output->user_picture($user);
        $template->userid = $user->id;

        $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

        // Print group status.
        if (!empty($usergroups)) {
            $template->hasgroups = true;
            $template->usergroupsstr = get_string('groups');

            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == groups_get_course_group($course)) {
                    $str = '<b>'.$str.'</b>';
                }
                $groupnames[] = $str;
            }

            $template->usergroups = implode(', ', $groupnames);
        }
        $template->rolesstr = get_string('roles');

        $template->userroles = get_user_roles_in_course($userid, $courseid);
        $template->firstname = $user->firstname;
        $template->lastname = $user->lastname;

        $params = ['view' => 'user', 'id' => $courseid, 'userid' => $userid];
        $template->examreporturl = new moodle_url('/report/examtraining/index.php', $params);
        $template->seedetailsstr = get_string('seedetails', 'report_examtraining');

        $template->equlearningtimestr = get_string('equlearningtime', 'report_examtraining');
        $template->elapsed = examtraining_reports_format_time($data->elapsed ?? 0, 'html');

        return $this->output->render_from_template('report_examtraining/globalheading', $template);
    }

    /**
     * prints a jqplot bidimensional graph with :
     * as x : coverage ratio : amount of attempted unique questions / total questions
     * as y : matched ratio : amount of matched unique questions / total questions
     *
     */
    public function coverage_vs_ratio(&$users, $courseid, $from, $to, $stats) {
        global $DB;

        $userids = implode("','", array_keys($users));

        $theblock = block_userquiz_monitor_get_block($courseid);
        $examcontext = $theblock->config;

        // For each root cat, calculate the hitratio from subcats.
        $maincats = block_userquiz_monitor_get_top_cats($theblock, true /* withcatcount */);
        $totalquestions = 0;
        foreach($maincats as $cat) {
            $totalquestions += $cat->qcount;
        }

        $data = array();

        foreach ($users as $userid => $user) {
            if (array_key_exists($user->id, $stats)) {
                $data[0][] = $stats[$user->id]->qcount / $totalquestions * 100;
                $data[1][] = ($stats[$user->id]->qcount) ? $stats[$user->id]->qmatched / $stats[$user->id]->qcount * 100 : 0;
            } else {
                $data[0][] = 0;
                $data[1][] = 0;
            }
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
     * Renders a "per category overal stats" table.
     */
    public function categories_attempts($stats, $group = null) {
        global $COURSE, $DB;

        $template = new StdClass();

        $theblock = block_userquiz_monitor_get_block($COURSE->id);
        if (isset($group)) {
            $template->group = $group;
            $targetusers = $DB->get_records('groups_members', ['groupid' => $group->id]);
            $template->groupsize = count($targetusers);
        }
        $examcontext = $theblock->config;
        $maincats = block_userquiz_monitor_get_top_cats($theblock, true /* withcatcount */);

        foreach ($maincats as &$cat) {
            if (array_key_exists($cat->id, $stats)) {
                $cat->stats = $stats[$cat->id];
            } else {
                $cat->stats = new StdClass;
                $cat->stats->attempts = 0;
                $cat->stats->qcount = 0;
                $cat->stats->qmatched = 0;
                $cat->stats->acount = 0;
                $cat->stats->amatched = 0;
                $cat->stats->ccount = 0;
                $cat->stats->cmatched = 0;
            }
        }

        $template->serieAname = (!empty($examcontext->serieAname)) ? $examcontext->serieAname : get_string('firstserie', 'report_examtraining');
        $template->serieCname = (!empty($examcontext->serieCname)) ? $examcontext->serieCname : get_string('secondserie', 'report_examtraining');
        $template->dualserie = $examcontext->dualserie;
        $template->cats = array_values($maincats);

        return $this->output->render_from_template('report_examtraining/categories', $template);
    }

    /**
     * Prints an overal coverage for all course or a group.
     * @params object $group if null get for all courses.
     * @params array $stats overl stats for groupe or course.
     * @param int $from
     * @param int $to
     */
    public function course_globals($group, $stats, $from = null, $to = null) {
        global $COURSE;

        $theblock = block_userquiz_monitor_get_block($COURSE->id);
        $examcontext = $theblock->config;

        $template = new StdClass;
        $template->serieAname = (!empty($examcontext->serieAname)) ? $examcontext->serieAname : get_string('firstserie', 'report_examtraining');
        $template->serieCname = (!empty($examcontext->serieCname)) ? $examcontext->serieCname : get_string('secondserie', 'report_examtraining');

        if (is_object($group)) {
            $targetusers = groups_get_members($group->id);
            $template->group = $group;
            $template->groupsize = count($targetusers);
        }

        // Render course stats : question count

        $template->course = $stats->course;

        // Render groups ratio

        if (is_null($group)) {
            $template->withgroupratios = true;
            $jqplotter = new jqplot_renderer();
            $title = get_string('groupratios', 'report_examtraining');

            $gdata = [];
            foreach ($stats->groups as $g) {
                $g->name = str_replace('-', '_', $g->name); // protect JS syntax.
                $gdata[$g->name] = $g->wqratio;
            }

            $template->groupratiograph = $jqplotter->groupworkratio_bargraph($gdata, $title, 'groupworkratio');
        }

        // Render coverage ratios
        $template->qgraph = $this->coverage_radar(0, $from, $to, '', 'q');

        if (!empty($examcontext->dualserie)) {
            $template->dualserie = true;
            $template->agraph = $this->coverage_radar(0, $from, $to, '', 'a');
            $template->cgraph = $this->coverage_radar(0, $from, $to, '', 'c');
        }

        return $this->output->render_from_template('report_examtraining/courseglobals', $template);
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

        $examcontext = block_userquiz_monitor_get_block($COURSE->id)->config;

        $sql = "
            SELECT
                q.id,
                q.name,
                SUM(ua.qcount) as qcount,
                SUM(ua.qmatched) as qmatched
            FROM
                {question} q,
                {report_examtraining} ua
            WHERE
                ua.questionid IS NOT NULL AND
                ua.userid IS NULL AND
                ua.course = ? AND
                ua.uniqueid IS NULL AND
                ua.questionid = q.id AND
                qdistinct = 0
            GROUP BY
                questionid
            ORDER BY
                q.name $orderby
        ";

        $questionlines = $DB->get_records_sql($sql, [$COURSE->id]);

        if (!empty($questionlines)) {
            $i = 1;
            foreach ($questionlines as $elm) {
                $data[0][0][] = $i;
                $data[0][1][] = $elm->qcount;
                $data[0][2][] = preg_replace('/\s.*$/', '', $elm->name);
                $data[1][$i] = $elm->qmatched;
                $data[2][$i] = round(($elm->qcount - $elm->qmatched) / $elm->qcount * 100);
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
            $errorratio = round(($elm->qcount - $elm->qmatched) / $elm->qcount * 100);
            $data[''.(round($errorratio / 20 * 4) * 5)] = @$data[''.(round($errorratio / 20 * 4)) * 5] + 1;
            $i++;
        }

        $arr = array();
        $str .= local_vflibs_jqplot_print_simple_bargraph($data, $arr, get_string('errorratio', 'report_examtraining'), 'qerrors');

        $sql = "
            SELECT
                ua.questionid,
                q.name,
                q.createdby,
                SUM(ua.qcount - ua.qmatched) / SUM(ua.qcount) * 100 AS errorrate,
                SUM(ua.qcount) as totaluse
            FROM
                {question} q,
                {report_examtraining} ua
            WHERE
                ua.questionid IS NOT NULL AND
                ua.userid IS NULL AND
                ua.course = ? AND
                ua.uniqueid IS NULL AND
                ua.questionid = q.id AND
                ua.qcount > 0 AND
                ua.qdistinct = 0
            GROUP BY
                ua.questionid
            HAVING
                SUM(qcount) > 0
            ORDER BY
                errorrate $orderby
            LIMIT 0, 50
        ";

        if ($errorquestions = $DB->get_records_sql($sql, array($COURSE->id))) {

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

    /**
     * @param int $userid User ID
     * @param arrayref &$data one user global results.
     */
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

        $template = new StdClass;

        // First row.
        $template->firstaccess = userdate($firstaccess);
        $template->lastaccess = userdate($lastaccess);
        // Second row.
        $template->connectionscountstr = get_string('connectionscount', 'report_examtraining', $cnx);
        // Third row.
        $template->elapsed = examtraining_reports_format_time(0 + @$data->elapsed, 'html');

        return $this->output->render_from_template('report_examtraining/learningtime', $template);
    }

    public static function sortbysubcatname($a, $b) {
        global $DB;

        $subcatordera = $DB->get_field('question_categories', 'sortorder', ['id' => $a]);
        $subcatorderb = $DB->get_field('question_categories', 'sortorder', ['id' => $b]);
        if ($subcatordera == $subcatorderb) {
            return 0;
        }
        if ($subcatordera > $subcatorderb) {
            return 1;
        }
        return -1;
    }

    /**
     * prints top lists.
     */
    public function tops($topexams, $topmatched, $topquestions) {
        global $DB, $COURSE;
        static $USERS;

        $template = new StdClass;

        // Feed exams.

        $template->hasusers = count($topexams) > 0 || count($topmatched) > 0 || count($topquestions) > 0;

        $attemptsstr = get_string('attempts', 'report_examtraining');
        $userstr = get_string('user');
        $questionsstr = get_string('questions', 'report_examtraining');
        $coveragestr = get_string('coverageshort', 'report_examtraining');

        $template->topexamshdr = get_string('topexams', 'report_examtraining');
        $template->notrainingactivity = $this->output->notification(get_string('notrainingactivity', 'report_examtraining'));
        $template->nousersstr = $this->output->notification(get_string('nousers', 'report_examtraining'));

        if ($topexams) {
            $template->hasexams = true;

            $table = new html_table();
            $table->head = ["<b>$attemptsstr</b>", "<b>$userstr</b>"];
            $table->align = ['left', 'left'];
            $table->size = ['10%', '90%'];
            $table->width = '90%';
            foreach ($topexams as $top) {
                $groups = examtraining_get_grouplist($COURSE->id, $top->userid);
                $groupclause = ($groups) ? " ($groups) " : '';
                $userurl = new moodle_url('/user/view.php', ['id' => $top->userid]);
                if (!array_key_exists($top->userid, $USERS)) {
                    $USERS[$top->userid] = $DB->get_record('user', ['id' => $top->userid]);
                }
                $userline = '<a href="'.$userurl.'">'.fullname($USERS[$top->userid]).' '.$groupclause.'</a>';
                $table->data[] = [$top->attempts, $userline];
            }
            $template->topexamstable = html_writer::table($table);
        }

        // Feed questions.

        $template->topquestionshdr = get_string('topquestions', 'report_examtraining');

        if ($topquestions) {
            $template->hasquestions = true;
            $table = new html_table();
            $table->head = ["<b>$questionsstr</b>", "<b>$userstr</b>"];
            $table->align = ['left', 'left'];
            $table->size = ['10%', '90%'];
            $table->width = '90%';
            foreach ($topquestions as $top) {
                $groups = examtraining_get_grouplist($COURSE->id, $top->userid);
                $groupclause = ($groups) ? " ($groups) " : '';
                $userurl = new moodle_url('/user/view.php', ['id' => $top->userid]);
                if (!array_key_exists($top->userid, $USERS)) {
                    $USERS[$top->userid] = $DB->get_record('user', ['id' => $top->userid]);
                }
                $userline = '<a href="'.$userurl.'">'.fullname($USERS[$top->userid]).' '.$groupclause.'</a>';
                $table->data[] = [$top->qcount, $userline];
            }
            $template->topquestionstable = html_writer::table($table);
        }

        // Feed matched coverage.

        $template->topcoveragehdr = get_string('topcoveragematched', 'report_examtraining');

        if ($topmatched) {
            $template->hascoverage = true;
            $table = new html_table();
            $table->head = ["<b>$coveragestr</b>", "<b>$userstr</b>"];
            $table->align = ['left', 'left'];
            $table->size = ['10%', '90%'];
            $table->width = '90%';
            foreach ($topmatched as $top) {
                $groups = examtraining_get_grouplist($COURSE->id, $top->userid);
                $groupclause = ($groups) ? " ($groups) " : '';
                $userurl = new moodle_url('/user/view.php', ['id' => $top->userid]);
                if (!array_key_exists($top->userid, $USERS)) {
                    $USERS[$top->userid] = $DB->get_record('user', ['id' => $top->userid]);
                }
                $userline = '<a href="'.$userurl.'">'.fullname($USERS[$top->userid]).' '.$groupclause.'</a>';
                $table->data[] = [$top->coveragematched.' %', $userline];
            }
            $template->topcoveragetable = html_writer::table($table);
        }

        return $this->output->render_from_template('report_examtraining/topreport', $template);

    }
}
