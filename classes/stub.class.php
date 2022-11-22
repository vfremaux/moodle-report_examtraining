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
 * A stub is a memory cache that compiles an attempt and prepare agregation in a multidimensional stats DB table.
 */
class stub {

    static $counters = ['attempts', 'qcount', 'qmatched', 'acount', 'amatched', 'ccount', 'cmatched'];

    /**
     * stub name for identification.
     */
    public $name;

    /**
     * Description
     */
    public $desc;

    /**
     * Data storage. an array of precompiled counter structures.
     */
    protected $data;

    /**
     * dimensions in DB storage. dimensions is an array of dimension variables
     * that are locked to an explicit values. All non explicited dimensional values will be turned
     * to NULL.
     */
    protected $dims;

    /**
     * mark that questions will be counted as distinct. distinct is a pseudo dimension.
     */
    protected $distinct;

    /**
     * defines and initialises a stat stub.
     */
    public function __construct($name, $desc, $dims, $distinct = 0) {
        $this->name = $name;
        $this->desc = $desc;
        $this->dims = $dims;
        $this->distinct = $distinct;
        $this->data = [];
        $this->init();
    }

    public function get_dims() {
        return $this->dims;
    }

    protected function init() {

        $numdim = count($this->dims);

        $i = 1;
        // Dig down into stub to initialise deepest object.
        $stub = &$this->data;
        foreach ($this->dims as $dim => $value) {
            if (!array_key_exists($value, $stub)) {
                if ($i < $numdim) {
                    $stub[$value] = [];
                } else {
                    $stub[$value] = new StdClass;
                }
            }
            $stub = &$stub[$value];
            $i++;
        }

        // initialize properties.
        $stub->attempts = 0;
        $stub->qcount = 0;
        $stub->qmatched = 0;
        $stub->acount = 0;
        $stub->ccount = 0;
        $stub->amatched = 0;
        $stub->cmatched = 0;
    }

    /**
     * Increments a counter in a stub. Assumes the stub object has been initilized.
     * @param array $counter the counter field.
     */
    public function inc_value($counter) {

        if (!in_array($counter, self::$counters)) {
            throw new coding_exception("Bad counter name");
        }

        $numdim = count($this->dims);
        $stub = &$this->data;
        foreach ($this->dims as $dim => $value) {
            $stub = &$stub[$value];
        }
        if (!is_object($stub)) {
            $stub = new StdClass;
        }
        $stub->$counter++;
    }

    /**
     * adds some extra data in a stub. Assumes the stub object has been initialized.
     * @param array $stub a stat aggregation stub.
     * @param array $key the stub record field key.
     * @param array $value the stub record value.
     * @param array $dimensions dimensions name and values in order top to down.
     */
    public function set_value($counter, $value) {

        $numdim = count($this->dims);
        $stub = &$this->data;
        foreach ($this->dims as $dim => $v) {
            $stub = &$stub[$v];
        }
        if (!is_object($stub)) {
            $stub = new StdClass;
        }
        $stub->$counter = $value;
    }

    /**
     * Get counter value in a stub. Assumes the stub object has been initilized.
     * @param array $stub a stat aggregation stub.
     * @param array $counter the counter field.
     * @param array $dimensions dimensions name and values in order top to down.
     */
    public function get_value($counter) {

        if (!in_array($counter, self::$counters)) {
            throw new coding_exception("Bad counter name");
        }

        $numdim = count($this->dims);
        $stub = &$this->data;
        foreach ($this->dims as $dim => $value) {
            $stub = &$stub[$value];
        }
        return $stub->$counter;
    }

    /**
     * check if a counter has data in a stub. Just checks memory stub.
     * @param array $stub a stat aggregation stub.
     * @param array $counter the counter field.
     * @param array $dimensions dimensions name and values in order top to down.
     */
    public function has_data() {

        $numdim = count($this->dims);
        $stub = &$this->data;
        foreach ($this->dims as $dim => $value) {
            if (!array_key_exists($value, $stub)) {
                return false;
            }
            $stub = $stub[$value];
        }

        return ($stub->qcount > 0);
    }

    /**
     * check if a counter has data in a stub. Also checks if DB records has data based on qcount.
     */
    public function is_in_db() {
        global $DB;

        $return = true;
        $numdim = count($this->dims);
        $stub = &$this->data;
        foreach ($this->dims as $dim => $value) {
            if (!array_key_exists($value, $stub)) {
                $return = false;
                break;
            }
            $stub = &$stub[$value];
        }

        if ($return) {
            // was not trapped by an uninitialized counter.
            $return = ($stub->qcount > 0);
        }

        if (!$return) {
            // finally check in DB.
            $select = self::write_dimension_select($this->dims);
            $dimensions = $this->dims;
            $dimensions['qdistinct'] = 0;
            $counter = $DB->get_field_select('report_examtraining', 'qcount', $select, $dimensions);
            $return = ($counter > 0);
        }
        return $return;
    }

    /**
     * Savec the stub in DB.
     */
    public function save() {
        global $DB;

        $dimkeys = array_keys($this->dims);

        $flat = [];
        self::flatten_rec($this->data, array_keys($this->dims), $flat);

        foreach ($flat as $rec) {
            // reload rec real dimension values in $dimension
            foreach ($dimkeys as $dim) {
                $this->dims[$dim] = $rec->$dim;
            }

            $select = self::write_dimension_select($this->dims);
            if ($oldrec = $DB->get_record_select('report_examtraining', $select, array_merge($this->dims, ['qdistinct' => $this->distinct]))) {
                $oldrec->qcount += $rec->qcount;
                $oldrec->qmatched += $rec->qmatched;
                $oldrec->acount += $rec->acount;
                $oldrec->amatched += $rec->amatched;
                $oldrec->ccount += $rec->ccount;
                $oldrec->cmatched += $rec->cmatched;
                $oldrec->qdistinct = $this->distinct;
                $DB->update_record('report_examtraining', $oldrec);
            } else {
                $rec->qdistinct = $this->distinct;
                $DB->insert_record('report_examtraining', $rec);
            }
        }
    }

    /**
     * Helper function : Build a select clause in DB stats cube, nulling unwanted dimensions.
     * @param array $dimensions a keyed array of expected non nulled dimensions
     * @return a SQL select
     */
    static function write_dimension_select($dimensions) {
        $dimensionarr = [
            'course' => null,
            'blockid' => null,
            'categoryid' => null,
            'groupid' => null,
            'userid' => null,
            'questionid' => null,
            'uniqueid' => null,
            'qdistinct' => 0
        ];

        $selects = [];
        foreach ($dimensions as $dim => $value) {
            $dimensionarr[$dim] = $value;
        }

        foreach ($dimensionarr as $dim => $value) {
            if (is_null($value)) {
                $selects[] = " $dim IS NULL "; 
            } else {
                $selects[] = " $dim = :$dim "; 
            }
        }

        return implode(' AND ', $selects);
    }

    /**
     * Recursive helper to flatten a stub.
     * @param array $stub
     * @param array $dimensions an array of dimension keys.
     * @param array $keyvalues array of key values collected in the path
     */
    static function flatten_rec($stub, $dimensions, &$flat) {
        static $keyvals = [];

        if (is_array($stub)) {
            foreach ($stub as $keyval => $substub) {
                array_push($keyvals, $keyval);
                self::flatten_rec($substub, $dimensions, $flat);
                array_pop($keyvals);
            }
        } else {
            // a terminal object.
            // combine dimensions and add to flat.
            $out = clone($stub);
            for ($i = 0; $i < count($keyvals); $i++) {
                $dim = $dimensions[$i];
                $val = $keyvals[$i];
                $out->$dim = $val;
            }
            $flat[] = $out;
        }
    }
}