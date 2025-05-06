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
 * @copyright   2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class jqplot_renderer {

    public function horiz_bar_headgraph(&$data, $title, $htmlid, $height = 'large') {
        global $plotid, $OUTPUT;
        static $instance = 0;

        if (empty($data)) {
            return;
        }

        $template = new StdClass;

        $template->htmlid = $htmlid.'_'.$instance;
        $template->plotid = $plotid;
        $template->graphheight = ($height == 'thin') ? 300 : 400;

        $template->title = addslashes($title);

        $answeredarr = [$data->acount];
        $hitratioarr = [$data->aratio];
        if (isset($data->ccount)) {
            // Dual series.
            $answeredarr[] = $data->ccount;
            $hitratioarr[] = $data->cratio;
            $template->dualseries = true;
        }

        $template->matchedstr = get_string('matched', 'report_examtraining');
        $template->questionstr = get_string('question', 'report_examtraining');
        $template->hitratiostr = get_string('hitratio', 'report_examtraining');

        $template->answereddata = local_vflibs_jqplot_barline('answered', $answeredarr);
        $template->hitratiodata = local_vflibs_jqplot_barline('hitratio', $hitratioarr);

        $plotid++;
        $instance++;

        return $OUTPUT->render_from_template('report_examtraining/jqplot_horizbarheadgraph', $template);
    }

    /**
     *
     *
     */
    public function assiduity_bargraph(&$data, $ticks, $title, $htmlid) {
        global $plotid, $OUTPUT;
        static $instance = 0;

        $template = new StdClass();

        $template->xticks = "'".implode("','", $ticks)."'";
        $template->htmlid = $htmlid.'_'.$instance;

        $template->title = addslashes($title);
        $template->qstr = addslashes(get_string('assiduity', 'report_examtraining'));
        $template->numberstr = addslashes(get_string('attempts', 'report_examtraining'));

        $template->graphdata = local_vflibs_jqplot_simplebarline('data_'.$htmlid.'_'.$instance, $data);

        $plotid++;
        $instance++;

        return $OUTPUT->render_from_template('report_examtraining/jqplot_assiduitygraph', $template);
    }

    /**
     *
     *
     */
    public function modules_bargraph(&$data, $title, $htmlid) {
        global $plotid;
        static $instance = 0;

        $htmlid = $htmlid.'_'.$instance;
        $instance++;

        // Preformat data with empty values.
        for ($i = 1; $i <= 10; $i++) {
            $data[$i * 10] = 0 + @$data[$i * 10];
        }
        ksort($data);

        $xticks = implode(',', array_keys($data));

        $str = '<center>';
        $str .= '<div id="'.$htmlid.'" style="width:500px; height:320px;"></div>';
        $str .= '</center>';

        $str .= '<script type="text/javascript" language="javascript">';
        $str .= '
            $.jqplot.config.enablePlugins = true;
        ';

        $title = addslashes($title);
        $qstr = addslashes(get_string('questions', 'report_examtraining'));
        $numberstr = addslashes(get_string('quantity', 'report_examtraining'));

        $str .= local_vflibs_jqplot_simplebarline('data_'.$htmlid, $data);

        $str .= "

            xticks = [{$xticks}];

            plot{$plotid} = \$.jqplot(
                '$htmlid',
                [data_$htmlid],
                {
                title:'$title',
                seriesDefaults:{
                    renderer: \$.jqplot.BarRenderer,
                    rendererOptions:{barPadding: 6, barMargin:4}
                },
                series:[
                    {color:'#FF0000'}
                ],
                highlighter: {
                    show: false,
                },
                axes:{
                    xaxis:{
                        renderer: \$.jqplot.CategoryAxisRenderer,
                        label:'{$qstr}',
                        ticks:xticks
                    },
                    yaxis:{
                        label:'{$numberstr}',
                        autoscale:true
                    }
                },
            });
        ";

        $str .= '</script>';
        $plotid++;

        return $str;
    }

    /**
     * data as array groupname=> workratio
     *
     */
    public function groupworkratio_bargraph(&$data, $title, $htmlid) {
        global $plotid;
        static $instance = 0;

        $htmlid = $htmlid.'_'.$instance;
        $instance++;

        // Preformat data with empty values.
        ksort($data);

        $xticks = "'".implode("','", array_keys($data))."'";

        $str = '<center>';
        $str .= '<div id="'.$htmlid.'" style="width:1500px; height:500px;"></div>';
        $str .= '</center>';

        $str .= '<script type="text/javascript" language="javascript">';
        $str .= '
            $.jqplot.config.enablePlugins = true;
        ';

        $title = addslashes($title);
        $qstr = addslashes(get_string('group', 'report_examtraining'));
        $numberstr = addslashes(get_string('workratio', 'report_examtraining'));

        $str .= local_vflibs_jqplot_simplebarline('data_'.$htmlid, $data);

        $str .= "

            xticks = [{$xticks}];

            plot{$plotid} = \$.jqplot(
                '$htmlid',
                [data_$htmlid],
                {
                title:'$title',
                seriesDefaults:{
                    renderer: \$.jqplot.BarRenderer,
                    rendererOptions:{barPadding: 6, barMargin:4}
                },
                axesDefaults:{
                    tickRenderer: \$.jqplot.CanvasAxisTickRenderer,
                },
                series:[
                    {color:'#FF0000'}
                ],
                highlighter: {
                    show: false,
                },
                axes:{
                    xaxis:{
                        renderer: \$.jqplot.CategoryAxisRenderer,
                        tickOptions: {
                            angle: -90
                        },
                        label:'{$qstr}',
                        ticks:xticks
                     },
                     yaxis:{
                        label:'{$numberstr}',
                        autoscale:true
                    }
                },
            });
        ";

        $str .= '</script>';
        $plotid++;

        return $str;
    }

    /**
     *
     *
     */
    public function questionuse_graph(&$data, $title, $htmlid) {
        global $plotid;
        static $instance = 0;

        $htmlid = $htmlid.'_'.$instance;
        $instance++;

        $str = '<center>';
        $str .= '<div id="'.$htmlid.'"
                   style="width:1024px; height:500px;"></div>';
        $str .= '</center>';

        $str .= '<script type="text/javascript" language="javascript">';
        $str .= '
            $.jqplot.config.enablePlugins = true;
        ';

        $title = addslashes($title);
        $usedstr = get_string('used', 'report_examtraining');
        $matchedstr = get_string('matched', 'report_examtraining');

        if (!empty($data[0][1])) {
            $maxscale = max($data[0][1]) + 100;
        } else {
            $maxscale = 100;
        }

        $str .= local_vflibs_jqplot_labelled_rawline($data[0], 'quse_'.$htmlid);
        $str .= local_vflibs_jqplot_rawline($data[1], 'qmatched_'.$htmlid);
        $str .= local_vflibs_jqplot_rawline($data[2], 'qhitratio_'.$htmlid);

        $str .= "
            plot{$plotid} = \$.jqplot(
                '$htmlid',
                [quse_$htmlid, qmatched_$htmlid, qhitratio_$htmlid],
                {
                title:'$title',
                seriesDefaults:{
                    renderer: \$.jqplot.LineRenderer,
                      showLine: true,
                      showMarker: false,
                      shadowAngle: 135,
                      shadowDepth: 2,
                      lineWidth: 1
                },
                series:[
                    {label: 'Used'},
                    {label: 'Matched'},
                    {label: 'ErrorRatio', yaxis:'y2axis', lineWidth:1, color:'#FF0000'}
                ],
                axes:{ xaxis:{autoscale:true, min:0, tickOptions:{formatString:'%d'}},
                       yaxis:{autoscale:true, min:0, max:{$maxscale}, tickOptions:{formatString:'%d'}},
                       y2axis:{min:0, max:100, tickOptions:{formatString:'%d\%'}}
                },
                cursor:{
                      showVerticalLine: true,
                      showHorizontalLine: false,
                      showCursorLegend: true,
                      showTooltip: false,
                      zoom: true
                  }
            });
        ";

        $str .= '</script>';
        $plotid++;

        return $str;
    }

}