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
 * A scheduled task for trainingsessions cron.
 *
 * @todo MDL-44734 This job will be split up properly.
 *
 * @package    report_examtraining
 * @category   report
 * @copyright  2018 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_examtraining\task;

require_once($CFG->dirroot.'/report/examtraining/statscompilelib.php');

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/report/examtraining/locallib.php');

class statscompile_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('statscompile_task', 'report_examtraining');
    }

    /**
     * Run trainingsessions cron.
     */
    public function execute() {

        @raise_memory_limit('512M');
        @set_time_limit(1800);

        $attempts = userquiz_cron_results();
        $admin = get_admin();
        email_to_user($admin, $admin, $SITE->fullname." : Userquiz Statcompilation : $attempts attempts compiled", 'Done.', 'Done.');

    }
}
