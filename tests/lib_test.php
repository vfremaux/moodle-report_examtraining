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
 * Tests for report library functions.
 *
 * @package    report_examtraining
 * @copyright  2022 onwards Valery Fremaux <valery.fremaux@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot. '/report/examtraining/statscompiler/statscompilelib.php');

/**
 * Class report_examtraining_statslib_testcase
 *
 * @package    report_examtraining
 * @copyright  2022 onwards Valery Fremaux <valery.fremaux@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class report_examtraining_statslib_testcase extends advanced_testcase {

    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Tests the flatten_rec function.
     */
    public function test_report_examtraining_flattener() {
        $this->setAdminUser();

        $dimensions = ['course' => 0, 'questionid' => 0];
        $rec = new Stdclass;
        $rec->qcount = 0;
        $rec->qmatched = 0;
        $rec->acount = 0;
        $rec->amatched = 0;
        $rec->ccount = 0;
        $rec->cmatched = 0;
        $stub[10][21] = $rec;

        $rec = new Stdclass;
        $rec->qcount = 0;
        $rec->qmatched = 0;
        $rec->acount = 0;
        $rec->amatched = 0;
        $rec->ccount = 0;
        $rec->cmatched = 0;
        $stub[10][22] = $rec;

        $rec = new Stdclass;
        $rec->qcount = 0;
        $rec->qmatched = 0;
        $rec->acount = 0;
        $rec->amatched = 0;
        $rec->ccount = 0;
        $rec->cmatched = 0;
        $stub[11][21] = $rec;
        $rec = new Stdclass;

        $rec->qcount = 0;
        $rec->qmatched = 0;
        $rec->acount = 0;
        $rec->amatched = 0;
        $rec->ccount = 0;
        $rec->cmatched = 0;
        $stub[11][31] = $rec;

        $compiler = new \report_examtraining\stats\compiler();
        $flat = [];
        $compiler->flatten_rec($stub, $dimensions, $flat);

        $this->assertTrue(count($flat) == 4);
        $this->assertTrue(is_object($flat[0]));
        $this->assertTrue(isset($flat[0]->course));
        $this->assertTrue(isset($flat[0]->questionid));
    }
}
