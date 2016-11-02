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
 * direct log construction implementation
 *
 */

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$id = required_param('id', PARAM_INT) ; // The course id.

// Quick controller for enabing/disabling background tasks.
$bgenabled = optional_param('backgroundenabled', 0, PARAM_BOOL);
if ($bgenabled) {
    set_config('backgroundrunsenabled', $bgenabled, 'report_examtraining');
    echo $OUTPUT->notification('Background compilations enabled');
}

$bgchecked = (empty($CFG->backgroundrunsenabled)) ? '' : 'checked="checked"';

// Print tools.

?>
<table width="90%">
<tr valign="top">
    <td><b>Backgrounder control</b><br/>
        <p>By enabling/disabling this, you can stop a running background compilation</p>
    </td>
    <td>
        <form name="backgroundenable" action="#">
            <input type="hidden" name="view" value="compilationtools" />
            <input type="hidden" name="id" value="<?php p($id) ?>" />
            <table width="100%">
                <tr>
                    <td colspan="2">
                        <input name="backgroundenabled" type="checkbox" value="1" <?php echo $bgchecked ?> />
                        <input name="go_btn" type="submit" value="Update"  />
                    </td>
            </table>
        </form>
    </td>
</tr>
<tr valign="top">
    <td><b>States to attempts and categories compilation</b></td>
    <td>
        <form name="simplecompile" action="<?php echo $CFG->wwwroot.'/mod/userquiz/statscompiler/precompile.php' ?>">
            <input type="hidden" name="id" value="<?php p($id) ?>" />
            <table width="100%">
                    <tr valign="top">
                    <td>Records range</td>
                    <td>
                        <select name="range">
                            <option value="1" selected="selected">Only new</option>
                            <option value="0">All records</option>
                        </select>
                        From (if all records)
                        <input type="text" name="fromid" value="" />
                    </td>
                </tr>
                    <tr valign="top">
                    <td>With cats</td>
                    <td>
                        <select name="withcats">
                            <option value="0">Without cats</option>
                            <option value="1" selected="selected">With cats</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <td>Limit</td>
                    <td>
                        <select name="limit">
                            <option value="0">No limit</option>
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                            <option value="1000">1000</option>
                            <option value="2000">2000</option>
                            <option value="5000">5000</option>
                        </select>
                    </td>
                </tr><tr valign="top">
                    <td>Auto (release time)</td>
                    <td>
                        <select name="auto">
                            <option value="0">Manual</option>
                            <option value="1">1 seconde</option>
                            <option value="2">2 seconds</option>
                            <option value="5">5 seconds</option>
                            <option value="8">8 seconds</option>
                            <option value="10">10 seconds</option>
                            <option value="15">15 seconds</option>
                            <option value="30">30 seconds</option>
                        </select>
                    </td>
                </tr><tr valign="top">
                    <td>Max bulks</td>
                    <td>
                        <select name="maxruns">
                            <option value="0">No max</option>
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="80">80</option>
                            <option value="100">100</option>
                            <option value="150">150</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                            <option value="1000">1000</option>
                            <option value="2000">2000</option>
                            <option value="5000">5000</option>
                            <option value="10000">10000</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <input type="submit" value="Compile states to categories" />
                    </td>
            </table>
        </form>
        <form name="simplecompileunit" action="<?php echo $CFG->wwwroot.'/mod/userquiz/statscompiler/precompile_unity.php' ?>">
            <input type="hidden" name="id" value="<?php p($id) ?>" />
            <input type="text" name="ids" value="" />
            <input type="submit" value="Compile some states" />
        </form>
        <form name="simplecompileclear" action="<?php echo $CFG->wwwroot.'/mod/userquiz/statscompiler/clear_stats.php' ?>">
            <input type="hidden" name="id" value="<?php p($id) ?>" />
            <p><input type="submit" value="Clear stats data" /></p>
        </form>
    </td>
</tr>
<tr valign="top">
    <td><b>User stats globalisators and coverage information</b></td>
    <td>
        <form name="simplecompilecover" action="<?php echo $CFG->wwwroot.'/mod/userquiz/statscompiler/precompile_userstats.php' ?>">
            <input type="hidden" name="id" value="<?php p($id) ?>" />
            <table width="100%">
                    <tr valign="top">
                        <td>Records range</td>
                        <td>
                            <select name="range">
                                <option value="1" selected="selected">Only new</option>
                                <option value="0">All records</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                    <td>Limit</td>
                    <td>
                        <select name="limit">
                            <option value="0">No limit</option>
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                            <option value="1000">1000</option>
                            <option value="2000">2000</option>
                            <option value="5000">5000</option>
                        </select>
                    </td>
                </tr><tr valign="top">
                    <td>Auto (release time)</td>
                    <td>
                        <select name="auto">
                            <option value="0">Manual</option>
                            <option value="2">2 seconds</option>
                            <option value="5">5 seconds</option>
                            <option value="8">8 seconds</option>
                            <option value="10">10 seconds</option>
                            <option value="15">15 seconds</option>
                            <option value="30">30 seconds</option>
                        </select>
                    </td>
                </tr><tr valign="top">
                    <td>Max bulks</td>
                    <td>
                        <select name="maxruns">
                            <option value="0">No max</option>
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="80">80</option>
                            <option value="100">100</option>
                            <option value="150">150</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                            <option value="1000">1000</option>
                            <option value="2000">2000</option>
                            <option value="5000">5000</option>
                            <option value="10000">10000</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <input type="submit" value="Compile user globalisators" />
                    </td>
            </table>
        </form>
        <form name="simplecompilecoverclear" action="<?php echo $CFG->wwwroot.'/mod/userquiz/statscompiler/clear_userstats.php' ?>">
            <input type="hidden" name="id" value="<?php p($id) ?>" />
            <p><input type="submit" value="Clear user stats data" /></p>
        </form>
    </td>
</tr>
<tr valign="top">
    <td><b>User coverage globalisators</b></td>
    <td>
        <form name="simplecompileusercover" action="<?php echo $CFG->wwwroot.'/mod/userquiz/statscompiler/precompile_coverages.php' ?>">
            <input type="hidden" name="id" value="<?php p($id) ?>" />
            <table width="100%">
                <tr>
                    <td colspan="2">
                        <input type="submit" value="Compile coverage indexes" />
                    </td>
            </table>
        </form>
    </td>
</tr>
</table>

