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
 *
 * @package     report_examtraining
 * @subpackage  report
 * @author      Valery Fremaux <valery.fremaux@club-internet.fr>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright   (C) 2016 onwards Valery Fremaux
 */

namespace report_examtraining;

use \StdClass;

class datacache {

    static $instance;

    protected $questions;

    protected $questionfields;

    protected $categryfields;

    protected $categories;

    protected $coverages;

    protected function __construct() {
        $this->questions = [];
        $this->categories = [];
        $this->questionfields = '*';
        $this->categoryfields = '*';
        $this->coverages = [];
    }

    // Singleton implementation.
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new datacache();
        }
        return self::$instance;
    }

    public function set_questionfields($qf) {
        $this->questionfields = $qf;
    }

    public function set_categoryfields($cf) {
        $this->categoryfields = $cf;
    }

    public function get_question($qid) {
        global $DB;

        if (!array_key_exists($qid, $this->questions)) {
            $this->questions[$qid] = $DB->get_record('question', ['id' => $qid], $this->questionfields);
        }
        return $this->questions[$qid];
    }

    public function get_category($cid) {
        global $DB;

        if (!array_key_exists($cid, $this->categories)) {
            $this->categories[$cid] = $DB->get_record('question_categories', ['id' => $cid], $this->categryfields);
        }

        return $this->categories[$cid];
    }

    public function get_coverage($params) {
        global $DB;

        $key = $params['userid'].'-'.$params['blockid'].'-'.$params['questionid'];

        if (!array_key_exists($key, $this->coverages)) {
            if ($rec = $DB->get_record('userquiz_monitor_coverage', $params)) {
                $this->coverages[$key] = $rec;
            } else {
                $rec = new StdClass;
                $rec->id = 0;
                $rec->questionid = $params['questionid'];
                $rec->userid = $params['userid'];
                $rec->blockid = $params['blockid'];
                $rec->usecount = 0;
                $rec->matchcount = 0;
                $this->coverages[$key] = $rec;
            }
        }

        return $this->coverages[$key];
    }

    // Saves the cache, inserting or updating records.
    public function save_coverages() {
        global $DB;

        if (!empty($this->coverages)) {
            foreach ($this->coverages as $cv) {
                if ($cv->id == 0) {
                    // New record.
                    unset($cv->id);
                    $cv->id = $DB->insert_record('userquiz_monitor_coverage', $cv);
                } else {
                    $DB->update_record('userquiz_monitor_coverage', $cv);
                }
            }
        }
    }
}