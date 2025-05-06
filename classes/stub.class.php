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
 * @copyright  1999 onwards valery fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    report_examtraining
 */
namespace report_examtraining\stats;

defined('MOODLE_INTERNAL') or die();

use StdClass;
use coding_exception;

/**
 * A stub is a memory cache that compiles counters on some dimensions.
 */
class stub {

    static $STUBS = [];

    static $dimensionarr = [
        'course' => null,
        'blockid' => null,
        'categoryid' => null,
        'groupid' => null,
        'userid' => null,
        'isexam' => null,
        'questionid' => null,
        'uniqueid' => null,
        'qdistinct' => 0
    ];


    // Counters.
    public $attempts;
    public $qcount;
    public $qmatched;
    public $acount;
    public $amatched;
    public $ccount;
    public $cmatched;
    public $attemptdate;
    public $qsize;

    /**
     * stub name for identification.
     */
    public $name;

    /**
     * Description
     */
    public $desc;

    /**
     * dimensions in DB storage. dimensions is an array of dimension variables
     * that are locked to an explicit values. All non explicited dimensional values will be turned
     * to NULL.
     */
    protected $dims;

    /**
     * Keyed context (dimensions and values) for indexing.
     */
    protected $key;

    /**
     * defines and initialises a stat stub, loading previous state from DB if exists.
     */
    public function __construct($name, $desc, $dims) {
        $this->name = $name;
        $this->desc = $desc ?? '';
        $this->dims = $dims;
        $this->dims['stubtype'] = $name;
    }

    protected function load_from_db() {
        global $DB;

        list($select, $params) = self::get_dimension_select_sql($this->dims);

        if (!$indb = $DB->get_record_select('report_examtraining', $select, $this->dims)) {
            $this->attempts = 0;
            $this->qcount = 0;
            $this->qmatched = 0;
            $this->acount = 0;
            $this->ccount = 0;
            $this->amatched = 0;
            $this->cmatched = 0;
            $this->attemptdate = 0;
            $this->qsize = 0;
        } else {
            $this->attempts = $indb->attempts;
            $this->qcount = $indb->qcount;
            $this->qmatched = $indb->qmatched;
            $this->acount = $indb->acount;
            $this->amatched = $indb->amatched;
            $this->ccount = $indb->ccount;
            $this->cmatched = $indb->cmatched;
            $this->attemptdate = 0;
            $this->qsize = 0;
        }
    }

    public function get_key() {
        return $this->key;
    }

    public function set_key($key) {
        $this->key = $key;
    }

    public static function flush() {
        self::$STUBS = [];
    }

    /**
     * Get an existig stub in memory or builds a new one.
     */
    public static function instance($name, $desc, $dims) {

        // Forges a compact key from dims to store a unique memory instance.
        $stubkey = self::forge_key($dims);

        if (!array_key_exists($stubkey, self::$STUBS)) {
            $stub = new stub($name, $desc, $dims);
            $stub->load_from_db();
            $stub->set_key($stubkey);
            self::$STUBS[$stubkey] = $stub;
        }

        return self::$STUBS[$stubkey];
    }

    /**
     * forges a compact key to index stubs in static memory storage.
     */
    public static function forge_key($dims) {

        foreach ($dims as $key => $value) {
            $parts[] = "$key:$value";
        }

        $key = implode('&', $parts);
        return hash('md5', $key);
    }

    public function get_dims() {
        return $this->dims;
    }

    /**
     * Test if a dim exists in stub (not NULL).
     * @param string $dim
     * @param mixed $value;
     */
    public function has_dim($dim) {
        return array_key_exists($dim, $this->dims);
    }

    /**
     * Returns the dim value.
     * @param string $dim
     */
    public function get_dim($dim) {

        if (!array_key_exists($dim, $this->dims)) {
            throw new coding_exception("Unkown dim $dim in stub.");
        }

        return $this->dims[$dim];
    }

    /**
     * Increments a counter in a stub. Assumes the stub object has been initilized.
     * @param array $counter the counter field.
     */
    public function inc_value($counter) {

        if (!isset($this->$counter)) {
            throw new coding_exception("Bad counter name");
        }

        $this->$counter++;
    }

    /**
     * Set a value.
     * @param array $counter a stat aggregation stub.
     * @param array $value the stub record value.
     */
    public function set_value($counter, $value) {

        if (!isset($this->$counter)) {
            throw new coding_exception("Bad counter name : $counter ");
        }

        $this->$counter = $value;
    }

    /**
     * Get counter value in a stub. Assumes the stub object has been initilized.
     * @param array $stub a stat aggregation stub.
     * @param array $counter the counter field.
     * @param array $dimensions dimensions name and values in order top to down.
     */
    public function get_value($counter) {

        if (!isset($this->$counter)) {
            throw new coding_exception("Bad counter name");
        }

        return $this->$counter;
    }

    /**
     * check if a counter has data in a stub. Just checks memory stub.
     * @param array $stub a stat aggregation stub.
     * @param array $counter the counter field.
     * @param array $dimensions dimensions name and values in order top to down.
     */
    public function has_data() {
        return ($this->qcount > 0);
    }

    /**
     * Save the stub in DB.
     */
    public function save() {
        global $DB;

        $dimkeys = array_keys($this->dims);

        list($select, $params) = $this->get_dimension_select_sql($this->dims);
        if ($oldrec = $DB->get_record_select('report_examtraining', $select, $params)) {
            $oldrec->attempts = $this->attempts;
            $oldrec->qcount = $this->qcount;
            $oldrec->qmatched = $this->qmatched;
            $oldrec->acount = $this->acount;
            $oldrec->amatched = $this->amatched;
            $oldrec->ccount = $this->ccount;
            $oldrec->cmatched = $this->cmatched;
            $oldrec->qsize = $this->qsize;
            $oldrec->attemptdate = $this->attemptdate;
            $DB->update_record('report_examtraining', $oldrec);
        } else {
            $rec = new StdClass;
            $this->write_dims($rec);
            $rec->stubtype = $this->name;
            $rec->datecompiled = time();
            $rec->attempts = $this->attempts;
            $rec->qcount = $this->qcount;
            $rec->qmatched = $this->qmatched;
            $rec->acount = $this->acount;
            $rec->amatched = $this->amatched;
            $rec->ccount = $this->ccount;
            $rec->cmatched = $this->cmatched;
            $rec->qsize = $this->qsize;
            $rec->attemptdate = $this->attemptdate;
            $DB->insert_record('report_examtraining', $rec);
        }
    }

    /**
     * Helper function : Build a select clause in DB stats cube, nulling unwanted dimensions.
     * @param array $dimensions a keyed array of expected non nulled dimensions
     * @return a SQL select
     */
    function get_dimension_select_sql() {

        $selects = [];
        $params = [];
        $dims = [];

        foreach (self::$dimensionarr as $dim => $value) {
            if (array_key_exists($dim, $this->dims)) {
                $dims[$dim] = $this->dims[$dim];
            } else {
                $dims[$dim] = null;
            }
        }

        foreach ($dims as $dim => $value) {
            if (is_null($value)) {
                $selects[] = " $dim IS NULL "; 
            } else {
                $selects[] = " $dim = :$dim "; 
                $params[$dim] = $value;
            }
        }

        return [implode(' AND ', $selects), $params];
    }

    /**
     * Write all dimensions in record object.
     * @param StdClass $rec
     */
    public function write_dims($rec) {

        foreach (self::$dimensionarr as $dim => $value) {
            if (isset($this->dims[$dim])) {
                $rec->$dim = $this->dims[$dim];
            } else {
                $rec->$dim = null;
            }
        }

    }

}