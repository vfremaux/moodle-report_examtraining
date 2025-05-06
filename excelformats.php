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

defined('MOODLE_INTERNAL') || die();

/**
 * sets up a set fo formats
 * @param object $workbook
 * @return array of usable formats keyed by a label
 */
function examtraining_reports_xls_formats(&$workbook) {

    // Nominal text format.
    $xlsformats['t'] = $workbook->add_format();
    $xlsformats['t']->set_size(12);
    $xlsformats['t']->set_color(0);
    $xlsformats['t']->set_fg_color(1);
    $xlsformats['t']->set_bold(1);

    // Nominal text wrapped.
    $xlsformats['tw'] = $workbook->add_format();
    $xlsformats['tw']->set_size(12);
    $xlsformats['tw']->set_color(0);
    $xlsformats['tw']->set_fg_color(1);
    $xlsformats['tw']->set_bold(1);
    $xlsformats['tw']->set_text_wrap();

    // Nominal text format backgrounded.
    $xlsformats['t2'] = $workbook->add_format();
    $xlsformats['t2']->set_size(12);
    $xlsformats['t2']->set_color(1);
    $xlsformats['t2']->set_fg_color(10);
    $xlsformats['t2']->set_bold(1);

    // Positive text format.
    $xlsformats['t+'] = $workbook->add_format();
    $xlsformats['t+']->set_size(11);
    $xlsformats['t+']->set_color(0);
    $xlsformats['t+']->set_fg_color(42);
    $xlsformats['t+']->set_bold(0);

    // Error text format.
    $xlsformats['t-'] = $workbook->add_format();
    $xlsformats['t-']->set_size(11);
    $xlsformats['t-']->set_color(0);
    $xlsformats['t-']->set_fg_color(45);
    $xlsformats['t-']->set_bold(0);

    // Smalltext format.
    $xlsformats['tt'] = $workbook->add_format();
    $xlsformats['tt']->set_size(9);
    $xlsformats['tt']->set_color(1);
    $xlsformats['tt']->set_fg_color(21);
    $xlsformats['tt']->set_bold(0);

    $xlsformats['ctr'] = $workbook->add_format();
    $xlsformats['ctr']->set_bold(1);
    $xlsformats['ctr']->set_align('right');

    $xlsformats['ctl'] = $workbook->add_format();
    $xlsformats['ctl']->set_bold(1);
    $xlsformats['ctl']->set_align('left');

    $xlsformats['p'] = $workbook->add_format();
    $xlsformats['p']->set_bold(0);
    $xlsformats['p']->set_align('center');

    $xlsformats['pl'] = $workbook->add_format();
    $xlsformats['pl']->set_bold(0);
    $xlsformats['pl']->set_align('left');

    $xlsformats['z'] = $workbook->add_format();
    $xlsformats['z']->set_size(9);

    $xlsformats['zt'] = $workbook->add_format();
    $xlsformats['zt']->set_size(9);
    $xlsformats['zt']->set_num_format('[h]:mm:ss');

    // Duration format.
    $xlsformats['ztl'] = $workbook->add_format();
    $xlsformats['ztl']->set_size(9);
    $xlsformats['ztl']->set_num_format('[h]:mm:ss');
    $xlsformats['ztl']->set_align('left');

    // Date format.
    $xlsformats['zd'] = $workbook->add_format();
    $xlsformats['zd']->set_size(9);
    $xlsformats['zd']->set_num_format('aaaa/mm/jj hh:mm');

    return $xlsformats;
}
