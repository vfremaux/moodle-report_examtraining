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
 * @package     report_examtraining
 * @category    report
 * @author      Valery Fremaux <valery.fremaux@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright   (C) 2016 onwards Valery Fremaux
 */
defined('MOODLE_INTERNAL') || die();

/**
 * performs a generic batch process using HTTP bounces
 * @uses $CFG->backgroundrunsenabled to control dumb loop
 */
function batch($prework = '', $work = '', $postwork = '', $source = '', $where = ' 1 ', &$workcontext = null, $fromid = '') {
    global $CFG, $SITE;

    $fromidclause = ($fromid) ? " AND id >= $fromid " : '';

    $limit = optional_param('limit', 20, PARAM_INT);
    $auto = optional_param('auto', 0, PARAM_INT);
    $maxruns = optional_param('maxruns', 20, PARAM_INT);
    $running = optional_param('running', 0, PARAM_INT);
    $output = optional_param('output', 0, PARAM_INT);

    if (!$running) {
        if ($output) {
             echo 'resetting runs config <br/>';
        } else {
            if (function_exists('debug_trace')) {
                debug_trace('resetting runs config ');
            }
        }
        set_config('runs', 0);
    } else {
        if (function_exists('debug_trace')) {
            debug_trace("Run n° : $CFG->runs");
        }
    }

    // If not handled before.
    if (!isset($workcontext->sourcerecs)) {
        $sourcereccount = $DB->count_records_select($source, " $where $fromidclause ", array());
        if ($limit) {
            $out = "processing $limit out of $sourcereccount records ({$CFG->runs} out of runlimit = $maxruns)";
            if ($output) {
                echo $out;
            } else {
                if (function_exists('debug_trace')) {
                    debug_trace($out);
                }
            }
            $start = ($range) ? 0 : $CFG->runs * $limit;
            $workcontext->sourcerecs = $DB->get_records_select($source, $where.$fromidclause, array(), 'id', '*', $start, $limit);
        } else {
            $out = "processing $sourcereccount records";
            if ($output) {
                echo $out;
            } else {
                if (function_exists('debug_trace')) {
                    debug_trace($out);
                }
            }
            $workcontext->sourcerecs = $DB->get_records_select($source, $where . $fromidclause, array(), 'id', '*');
        }
    }

    // Only process finished attempts.
    if (!empty($workcontext->sourcerecs)) {

        // Do prework only before the first run.
        if (!empty($prework) && function_exists($prework)) {
            if ($output) {
                 echo "preworking<br/>";
            } else {
                debug_trace("preworking ");
            }
            $prework($workcontext);
        } else {
            if ($output) {
                 echo "no prework<br/>";
            } else {
                if (function_exists('debug_trace')) {
                    debug_trace("no prework");
                }
            }
        }

        foreach ($workcontext->sourcerecs as $rec) {

            // We call a worker task on each item.
            $out = "working rec $rec->id";
            if ($output) {
                 echo "$out<br/>";
            } else {
                if (function_exists('debug_trace')) {
                    debug_trace("$out ");
                }
            }
            if (!empty($work) && function_exists($work)) {
                $work($rec, $workcontext);
            } else {
                $out = "no work or not existing work";
                if ($output) {
                     echo "$out<br/>";
                } else {
                    if (function_exists('debug_trace')) {
                        debug_trace("$out ");
                    }
                }
            }
        }

        if (!empty($CFG->backgroundrunsenabled) && $limit && $auto) {
            sleep($auto);
            if (!$maxruns || @$CFG->runs < $maxruns) {
                set_config('runs', 1 + @$CFG->runs);
                $redirecturl = $_SERVER['PHP_SELF']."?course={$workcontext->course->id}&auto=$auto&limit=$limit";
                $redirecturl .= "&maxruns=$maxruns&running=1&fromid=$fromid&from={$workcontext->from}&to={$workcontext->to}";
                $redirecturl .= "&filename={$workcontext->filename}";
                if ($output) {
                    flush();
                    redirect($redirecturl);
                } else {
                    header("Location: $redirecturl");
                }
            } else {
                set_config('runs', 0);

                // When everything is finished, terminate the job with whatever postwork task.
                if (!empty($postwork) && function_exists($postwork)) {
                    if ($output) {
                        echo "postworking<br/>";
                    } else {
                        if (function_exists('debug_trace')) {
                            debug_trace('postworking');
                        }
                    }
                    $postwork($workcontext);
                } else {
                    if ($output) {
                        echo "no postwork<br/>";
                    } else {
                        if (function_exists('debug_trace')) {
                            debug_trace('no postwork');
                        }
                    }
                }

                $admin = get_admin();

                email_to_user($admin, $admin, $SITE->fullname." : Userquiz Report Compilation : ", 'Done.', 'Done.');
            }
        }
        return true;
    }
    return false;
}

/**
 * launches a taskset using a batch URL and some parameter arrays
 * for variability. Acts as controller of CURL launched subprocesses.
 * @uses $CFG->backgroundrunsenabled to control dumb loop
 */
function batch_task($baseurl, $params) {
    global $CFG;

    // Avoids misusing for external attacks.
    if (!preg_match("/{$CFG->wwwroot}/", $baseurl)) {
        die("Required Url not in domain");
    }

    foreach ($params as $param) {
        $url = $baseurl.$param;

        $ch = curl_init($url);

        $timeout = 5000;

        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Examtraning Report Batch');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_PROXY, $CFG->proxyhost);
        curl_setopt($ch, CURLOPT_PROXYPORT, $CFG->proxyport);
        curl_setopt($ch, CURLOPT_PROXYTYPE, $CFG->proxytype);
        if (!empty($CFG->proxyuser)) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $CFG->proxyuser.':'.$CFG->proxypassword);
        }

        $rawresponse = curl_exec($ch);
    }
}
