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

require_once($CFG->dirroot.'/blocks/userquiz_monitor/xlib.php');

class report_examtraining_renderer extends plugin_renderer_base {

    public function selectorform($course, $view, $input) {
        global $USER;

        $uqmanager = get_block_userquiz_monitor_manager();

        $context = context_course::instance($course->id);

        $template = new Stdclass;
        $template->view = $view;
        $template->courseid = $course->id;
        $template->canseeother = false;
        $template->nousers = $input->nousers;

        $template->startdateselector = $this->date_selector('startday', 'startmonth', 'startyear', $input->from, false, 2014, 2020);
        $template->enddateselector = $this->date_selector('endday', 'endmonth', 'endyear', $input->to, false, 2014, 2020);

        $userid = optional_param('userid', $USER->id, PARAM_INT);

        if (has_capability('report/examtraining:viewall', $context)) {

            $template->canseeother = true;

            if (empty($input->nousers)) {
                // User selector.
                $mygroupings = groups_get_user_groups($course->id, $userid);
                if (!empty($mygroupings) &&
                        !has_capability('moodle/site:accessallgroups', $context)) {

                    $mygroups = array();
                    foreach ($mygroupings as $grouping) {
                        $mygroups = $mygroups + $grouping;
                    }

                    $users = array();

                    // Get all users in my groups.
                    foreach ($mygroups as $mygroupid) {
                        $members = groups_get_members($mygroupid, 'u.id, firstname, lastname');
                        if ($members) {
                            $users = $users + $members;
                        }
                    }
                } else {
                    $users = get_enrolled_users($context);
                }

                $useroptions = array();
                foreach ($users as $user) {
                    $activity = $uqmanager->count_user_attempts($user->id); // Count finished attempts.
                    $useroptions[$user->id] = fullname($user);
                    if ($activity) {
                        $useroptions[$user->id] .= " ($activity)";
                    }
                }
                $template->userselect = html_writer::select($useroptions, 'userid', $userid, ['' => 'choosedots']);
            } else {
                $urlroot = new moodle_url('/report/examtraining/index.php', ['view' => $view, 'id' => $course->id]);
                $template->groupmenu = groups_print_course_menu($course, $urlroot, true);
            }
        }

        return $this->output->render_from_template('report_examtraining/selectorform', $template);
    }

    /**
     * Prints form items with the names $day, $month and $year
     *
     * @param string $day   fieldname
     * @param string $month  fieldname
     * @param string $year  fieldname
     * @param int $currenttime A default timestamp in GMT
     * @param boolean $return
     */
    public function date_selector($day, $month, $year, $currenttime = 0, $return = false, $from = 1970, $to = 2020) {

        if (!$currenttime) {
            $currenttime = time();
        }
        $currentdate = usergetdate($currenttime);

        for ($i = 1; $i <= 31; $i++) {
            $days[$i] = $i;
        }
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = userdate(gmmktime(12, 0, 0, $i, 15, 2000), "%B");
        }
        for ($i = $from; $i <= $to; $i++) {
            $years[$i] = $i;
        }

        // Build or print result.

        $str = '';

        /*
         * Note: There should probably be a fieldset around these fields as they are
         * clearly grouped. However this causes problems with display. See Mozilla
         * bug 474415
         */
        $str .= '<label class="accesshide" for="menu'.$day.'">'.get_string('day', 'form').'</label>';
        $str .= html_writer::select($days,   $day,   $currentdate['mday']);
        $str .= '<label class="accesshide" for="menu'.$month.'">'.get_string('month', 'form').'</label>';
        $str .= html_writer::select($months, $month, $currentdate['mon']);
        $str .= '<label class="accesshide" for="menu'.$year.'">'.get_string('year', 'form').'</label>';
        $str .= html_writer::select($years,  $year,  $currentdate['year']);

        return $str;
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

    public function tabs($view, $subview, $groupid) {
        global $COURSE;

        $context = context_course::instance($COURSE->id);

        // Print tabs with options for user.
        if (has_capability('report/examtraining:viewall', $context)) {
            $taburl = new moodle_url('/report/examtraining/index.php', array('id' => $COURSE->id, 'view' => 'user'));
            $rows[0][] = new tabobject('user', $taburl, get_string('user', 'report_examtraining'));

            $params = array('id' => $COURSE->id, 'view' => 'course', 'groupid' => $groupid);
            $taburl = new moodle_url('/report/examtraining/index.php', $params);
            $rows[0][] = new tabobject('course', $taburl, get_string('course', 'report_examtraining'));

            $params = array('id' => $COURSE->id, 'view' => 'courseraw', 'groupid' => $groupid);
            $taburl = new moodle_url('/report/examtraining/index.php', $params);
            $rows[0][] = new tabobject('courseraw', $taburl, get_string('courseraw', 'report_examtraining'));

            $taburl = new moodle_url('/report/examtraining/index.php', array('id' => $COURSE->id, 'view' => 'questionbank'));
            $rows[0][] = new tabobject('questionbank', $taburl, get_string('questionbank', 'report_examtraining'));

            if (has_capability('moodle/site:config', context_system::instance())) {
                $taburl = new moodle_url('/report/examtraining/index.php', array('id' => $COURSE->id, 'view' => 'compilationtools'));
                $rows[0][] = new tabobject('compilationtools', $taburl, get_string('compilationtools', 'report_examtraining'));
            }

            if ($view == 'course') {
                if (has_capability('report/examtraining:viewsensibleresults', $context)) {
                    $params = array('id' => $COURSE->id, 'view' => 'course_map', 'groupid' => $groupid);
                    $taburl = new moodle_url('/report/examtraining/index.php', $params);
                    $rows[1][] = new tabobject('course_map', $taburl, get_string('coursemap', 'report_examtraining'));
                }

                $params = array('id' => $COURSE->id, 'view' => 'course_group', 'groupid' => $groupid);
                $taburl = new moodle_url('/report/examtraining/index.php', $params);
                $rows[1][] = new tabobject('course_group', $taburl, get_string('coursegroup', 'report_examtraining'));

                if (has_capability('report/examtraining:viewsensibleresults', $context)) {
                    $params = array('id' => $COURSE->id, 'view' => 'course_tops', 'groupid' => $groupid);
                    $taburl = new moodle_url('/report/examtraining/index.php', $params);
                    $rows[1][] = new tabobject('course_tops', $taburl, get_string('coursetops', 'report_examtraining'));
                }

                return print_tabs($rows, 'course_'.$subview, 'course', array($view), true);
            } else {
                return print_tabs($rows, $view, '', null, true);
            }
        }
    }
}