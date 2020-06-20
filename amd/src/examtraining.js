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
 * Contain the JS logic for the examtraining report.
 *
 * @package    report_examtraining
 * @copyright  2020 Valery Fremaux <valery.fremaux@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log'], function($, log) {

    var examtraining = {

        init: function() {
            $('input[name="gofromstart_btn"]').bind('click', this.submitfromstart);

            log.debug('AMD report examtraining initialized');
        },

        submitfromstart: function() {
            $('#examtraining-selector-fromstart').val(1);
            $('form[name="selector"]').submit();
        }

    };

    return examtraining;

});