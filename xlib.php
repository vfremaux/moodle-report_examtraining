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
 * Provides entry point functions for other plugins
 */

/**
 * Registers a quiz attempt in examtraining register.
 * @param quiz_attempt $attempt the attempt object from a quiz.
 */
function report_examtraining_register_attempt($attempt) {
    global $DB;

    $uniqueid = $attempt->uniqueid;

    $params = ['uniqueid' => $uniqueid];
    if (empty($DB->get_record('report_examtraining', $params))) {

        $rec = new Stdclass;
        $rec->uniqueid = $uniqueid;
        $rec->qcount = 0;
        $rec->serieaanswered = 0;
        $rec->seriecanswered = 0;
        $rec->serieamatched = 0;
        $rec->seriecmatched = 0;
        $rec->datecompiled = 0;
        $DB->insert_record('report_examtraining', $rec);
    }
}
