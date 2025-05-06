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
 * @package report_examtraining
 */

namespace report_examtraining;

defined('MOODLE_INTERNAL') || die();

use core\event\course_reset_ended;

/**
 * Handles events.
 */
class observers {

    /**
     * Handle user_deleted event - clean up calendar subscriptions.
     *
     * @param moogwai_user\event\user_deleted $event The triggered event.
     * @return bool Success/Failure.
     */
    public static function handle_course_reset_ended(course_reset_ended $event) {
        global $DB;

        $userid = $event->objectid;
        $DB->delete_records('report_examtaining', ['course' => $event->objectid]);

        return true;
    }
}
