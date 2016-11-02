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
* sets up a set fo formats
* @param object $workbook
* @return array of usable formats keyed by a label
*/
function examtraining_reports_xls_formats(&$workbook) {

    // Nominal text format.
    $xls_formats['t'] =& $workbook->add_format();
    $xls_formats['t']->set_size(12);
    $xls_formats['t']->set_color(0);
    $xls_formats['t']->set_fg_color(1);
    $xls_formats['t']->set_bold(1);

    // Nominal text wrapped.
    $xls_formats['tw'] =& $workbook->add_format();
    $xls_formats['tw']->set_size(12);
    $xls_formats['tw']->set_color(0);
    $xls_formats['tw']->set_fg_color(1);
    $xls_formats['tw']->set_bold(1);
    $xls_formats['tw']->set_text_wrap();

    // Nominal text format backgrounded.
    $xls_formats['t2'] =& $workbook->add_format();
    $xls_formats['t2']->set_size(12);
    $xls_formats['t2']->set_color(1);
    $xls_formats['t2']->set_fg_color(10);
    $xls_formats['t2']->set_bold(1);

    // Positive text format.
    $xls_formats['t+'] =& $workbook->add_format();
    $xls_formats['t+']->set_size(11);
    $xls_formats['t+']->set_color(0);
    $xls_formats['t+']->set_fg_color(42);
    $xls_formats['t+']->set_bold(0);

    // Error text format.
    $xls_formats['t-'] =& $workbook->add_format();
    $xls_formats['t-']->set_size(11);
    $xls_formats['t-']->set_color(0);
    $xls_formats['t-']->set_fg_color(45);
    $xls_formats['t-']->set_bold(0);

    // Smalltext format.
    $xls_formats['tt'] =& $workbook->add_format();
    $xls_formats['tt']->set_size(9);
    $xls_formats['tt']->set_color(1);
    $xls_formats['tt']->set_fg_color(21);
    $xls_formats['tt']->set_bold(0);

    $xls_formats['ctr'] =& $workbook->add_format();
    $xls_formats['ctr']->set_bold(1);
    $xls_formats['ctr']->set_align('right');

    $xls_formats['ctl'] =& $workbook->add_format();
    $xls_formats['ctl']->set_bold(1);
    $xls_formats['ctl']->set_align('left');

    $xls_formats['p'] =& $workbook->add_format();
    $xls_formats['p']->set_bold(0);
    $xls_formats['p']->set_align('center');

    $xls_formats['pl'] =& $workbook->add_format();
    $xls_formats['pl']->set_bold(0);
    $xls_formats['pl']->set_align('left');

    $xls_formats['z'] =& $workbook->add_format();
    $xls_formats['z']->set_size(9);

    $xls_formats['zt'] =& $workbook->add_format();
    $xls_formats['zt']->set_size(9);
    $xls_formats['zt']->set_num_format('[h]:mm:ss');

    // Duration format.
    $xls_formats['ztl'] =& $workbook->add_format();
    $xls_formats['ztl']->set_size(9);
    $xls_formats['ztl']->set_num_format('[h]:mm:ss');
    $xls_formats['ztl']->set_align('left');

    // Date format.
    $xls_formats['zd'] =& $workbook->add_format();
    $xls_formats['zd']->set_size(9);
    $xls_formats['zd']->set_num_format('aaaa/mm/jj hh:mm');

    return $xls_formats;
}
