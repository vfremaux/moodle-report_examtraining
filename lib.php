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
 * @package    report
 * @subpackage examtraining
 * @copyright  2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_examtraining_extend_navigation_course($navigation, $course, $context) {
    global $DB;

    // Do NOT give access to this report unless you are using a course as examtraining system.
    if (!$DB->get_records('block_instances', array('blockname' => 'userquiz_monitor', 'parentcontextid' => $context->id))) {
        return;
    }

    if (has_capability('report/examtraining:view', $context)) {
        $url = new moodle_url('/report/examtraining/index.php', array('id' => $course->id));
        $label = get_string('pluginname', 'report_examtraining');
        $pixicon = new pix_icon('i/report', '');
        $navigation->add($label, $url, navigation_node::TYPE_SETTING, null, null, $pixicon);
    }
}

function report_examtraining_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = array(
        '*'                          => get_string('page-x', 'pagetype'),
        'report-*'                   => get_string('page-report-x', 'pagetype'),
        'report-examtraining-*'     => get_string('page-report-examtraining-x',  'report_examtraining'),
        'report-examtraining-index' => get_string('page-report-examtraining-index',  'report_examtraining'),
    );
    return $array;
}

/**
 * Is current user allowed to access this report
 *
 * @private defined in lib.php for performance reasons
 *
 * @param stdClass $user
 * @param stdClass $course
 * @return bool
 */
function report_examtraining_can_access_user_report($user, $course) {
    global $USER;

    $coursecontext = context_course::instance($course->id);
    $personalcontext = context_user::instance($user->id);

    if (has_capability('report/examtraining:view', $coursecontext)) {
        return true;
    } else if ($user->id == $USER->id) {
        if ($course->showreports && (is_viewing($coursecontext, $USER) or is_enrolled($coursecontext, $USER))) {
            return true;
        }
    }

    return false;
}

/**
 * Called by the storage subsystem to give back a raw report
 */
function report_examtraining_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    require_course_login($course);

    if (!in_array($filearea, array('rawreports', 'instantreport'))) {
        send_file_not_found();
    }

    $fs = get_file_storage();

    $itemid = array_shift($args);
    $filename = array_shift($args);
    $filepath = dirname($filename);
    $filename = basename($filename);

    if (empty($filepath) || $filepath == '.') {
        $filepath = '/';
    }

    if ((!$file = $fs->get_file($context->id, 'report_examtraining', $filearea, $itemid, $filepath, $filename)) ||
            $file->is_directory()) {
        send_file_not_found();
    }

    $forcedownload = true;

    send_stored_file($file, 60 * 60, 0, $forcedownload);
}

/**
 * Callback to verify if the given instance of store is supported by this report or not.
 *
 * @param string $instance store instance.
 *
 * @return bool returns true if the store is supported by the report, false otherwise.
 */
function report_examtraining_supports_logstore($instance) {
    if ($instance instanceof \core\log\sql_internal_reader ||
            $instance instanceof \logstore_legacy\log\store ||
                    $instance instanceof \logstore_standard\log\store) {
        return true;
    }
    return false;
}
