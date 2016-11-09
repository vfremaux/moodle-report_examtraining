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

class jqplot_renderer {

    public function horiz_bar_headgraph(&$data, $title, $htmlid, $height = 'large') {
        global $plotid;
        static $instance = 0;

        $htmlid = $htmlid.'_'.$instance;
        $instance++;

        $graphheight = ($height == 'thin') ? 300 : 400 ;

        if (empty($data)) {
            return;
        }

        $str = '';

        $str .= "<center><div id=\"$htmlid\" style=\"margin-bottom:20px; margin-left:20px; width:700px; height:{$graphheight}px;\"></div></center>";
        $str .= "<script type=\"text/javascript\" language=\"javascript\">";
        $str .= "
            $.jqplot.config.enablePlugins = true;
        ";

        $title = addslashes($title);

        $answeredarr = array($data->aanswered, $data->canswered);
        $hitratioarr = array($data->ahitratio, $data->chitratio);

        $matchedstr = get_string('matched', 'report_examtraining');
        $questionstr = get_string('question', 'report_examtraining');
        $hitratiostr = get_string('hitratio', 'report_examtraining');

        $str .= local_vflibs_jqplot_barline('answered', $answeredarr);
        $str .= local_vflibs_jqplot_barline('hitratio', $hitratioarr);
        $str .= "
            plot{$plotid} = $.jqplot(
                '$htmlid',
                [answered, hitratio],
                { legend:{show:true, location:'e', placement:'outsideGrid'},
                title:'$title',
                seriesDefaults:{ renderer:$.jqplot.BarRenderer,
                                   rendererOptions:{barDirection:'horizontal',
                                                    barPadding: 6,
                                                    barMargin:15}, 
                                   shadowAngle:135
                },
                series:[
                    {label:'Questions', color:'#C54F27'},
                    {label:'{$hitratiostr}', xaxis:'x2axis', color:'#A5A9DA'}
                ],
                highlighter: {
                    show: false,
                },
                axesDefaults:{useSeriesColor: false, syncTicks:true},
                axes:{ xaxis:{label:'Questions', min:0, tickOptions:{textColor:'#D67B16', formatString:'%d'}}, 
                       x2axis:{label:'{$hitratiostr}', min:0, max:100, tickOptions:{formatString:'%d\%', textColor:'#A5A9DA'}},
                          yaxis:{renderer:$.jqplot.CategoryAxisRenderer, ticks:['A', 'C']}
                }
            });
        ";

        $str .= "</script>";

        $plotid++;

        return $str;
    }

    /**
     *
     *
     */
    function assiduity_bargraph(&$data, $ticks, $title, $htmlid) {
        global $plotid;
        static $instance = 0;

        $htmlid = $htmlid.'_'.$instance;
        $instance++;

        $xticks = "'".implode("','", $ticks)."'";

        $str = '';

        $str .= '<center>';
        $str .= '<div id="'.$htmlid.'"
                      class=""
                      style="width:880px; height:320px;"></div>';
        $str .= '</center>';

        $str .= '<script type="text/javascript" language="javascript">';
        $str .= '
            $.jqplot.config.enablePlugins = true;
        ';

        $title = addslashes($title);
        $qstr = addslashes(get_string('assiduity', 'report_examtraining'));
        $numberstr = addslashes(get_string('attempts', 'report_examtraining'));

        $str .= local_vflibs_jqplot_simplebarline('data_'.$htmlid, $data);

        $str .= "
            xticks = [{$xticks}];

            plot{$plotid} = $.jqplot(
                '$htmlid',
                [data_{$htmlid}],
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
                        tickRenderer: \$.jqplot.CanvasAxisTickRenderer,
                        tickOptions:{angle: -45, fontSize: '8pt'},
                        label:'{$qstr}',
                        ticks:xticks
                    },
                    yaxis:{
                        autoscale:true,
                        label:'{$numberstr}'
                    }
                },
            });
        ";

        echo '</script>';
        $plotid++;
    
    }

    /**
     *
     *
     */
    function modules_bargraph(&$data, $title, $htmlid) {
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

        echo '<script type="text/javascript" language="javascript">';
        echo '
            \$.jqplot.config.enablePlugins = true;
        ';

        $title = addslashes($title);
        $qstr = addslashes(get_string('questions', 'report_examtraining'));
        $numberstr = addslashes(get_string('quantity', 'report_examtraining'));

        $str .= local_vflibs_jqplot_simplebarline('data_'.$htmlid, $data);

        $str .= "

            xticks = [{$xticks}];

            plot{$plotid} = $.jqplot(
                '$htmlid',
                [data_$htmlid],
                {
                title:'$title',
                seriesDefaults:{
                    renderer:$.jqplot.BarRenderer,
                    rendererOptions:{barPadding: 6, barMargin:4}
                },
                series:[
                    {color:'#FF0000'}
                ],
                highlighter: {
                    show: false,
                },
                axes:{ xaxis:{renderer:$.jqplot.CategoryAxisRenderer, label:'{$qstr}', ticks:xticks},
                       yaxis:{label:'{$numberstr}', autoscale:true}
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
    function questionuse_graph(&$data, $title, $htmlid) {
        global $plotid;
        static $instance = 0;

        $htmlid = $htmlid.'_'.$instance;
        $instance++;

        $str = '<center>';
        $str .= '<div id="'.$htmlid.'"
                   style="width:700px; height:500px;"></div>';
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