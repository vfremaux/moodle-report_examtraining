<?php

define('CLI_SCRIPT', true);
require('../../../config.php');

require_once($CFG->dirroot.'/report/examtraining/statscompilelib.php');

echo "Starting test\n";

$compiler = new \report_examtraining\stats\compiler();

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
$compiler->flatten_rec($stub, array_keys($dimensions), $flat);

print_object($flat);
