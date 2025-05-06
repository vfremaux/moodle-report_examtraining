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
 * @package    report_learningtimecheck
 * @category   report
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

/**
 * Standard upgrade handler.
 * @param int $oldversion
 */
function xmldb_report_examtraining_upgrade($oldversion = 0) {
    global $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    $result = true;
    // Removed old upgrade stuff, as it now uses install.xml by default to install.

    if ($oldversion < 2022093000) {

        // Define table report_examtraining to be created.
        $table = new xmldb_table('report_examtraining');

        $field = new xmldb_field('qdistinct', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'cmatched');
 
        if (!$dbman->field_exists($table, $field)) {
            // We already have the new model.
            // examtraining savepoint reached.
            upgrade_plugin_savepoint(true, 2022093000, 'report', 'examtraining');
            return true;
        }

        // Conditionally launch drop table for report_examtraining before recreating it.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('report_examtraining');

        // Adding fields to table report_examtraining.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('blockid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('uniqueid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('isexam', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('qsize', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
        $table->add_field('qcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('qmatched', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('acount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ccount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('amatched', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmatched', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('qdistinct', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attemptdate', XMLDB_TYPE_INTEGER, '11', null, null, null, null);
        $table->add_field('datecompiled', XMLDB_TYPE_INTEGER, '11', null, null, null, null);

        // Adding keys to table report_examtraining.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table report_examtraining.
        $table->add_index('ix_uniqueid', XMLDB_INDEX_UNIQUE, ['uniqueid']);
        $table->add_index('ix_course', XMLDB_INDEX_NOTUNIQUE, ['course']);
        $table->add_index('ix_question', XMLDB_INDEX_NOTUNIQUE, ['questionid']);
        $table->add_index('ix_questioncat', XMLDB_INDEX_NOTUNIQUE, ['categoryid']);
        $table->add_index('ix_user', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionnally launch create table for report_examtraining.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        echo $OUTPUT->notification(get_string('needrecompileall', 'report_examtraining'), 'warning');

        // examtraining savepoint reached.
        upgrade_plugin_savepoint(true, 2022093000, 'report', 'examtraining');
    }

    if ($oldversion < 2022100200) {

        $table = new xmldb_table('report_examtraining');

        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NULL, null, null, 'blockid');
 
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // count attempts in the dimension.
        $field = new xmldb_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NULL, null, null, 'isexam');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // examtraining savepoint reached.
        upgrade_plugin_savepoint(true, 2022100200, 'report', 'examtraining');
    }

     return $result;
}
