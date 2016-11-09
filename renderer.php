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
     * Prints form items with the names $day, $month and $year
     *
     * @param string $day   fieldname
     * @param string $month  fieldname
     * @param string $year  fieldname
     * @param int $currenttime A default timestamp in GMT
     * @param boolean $return
     */
    function date_selector($day, $month, $year, $currenttime = 0, $return = false, $from = 1970, $to = 2020) {

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

}