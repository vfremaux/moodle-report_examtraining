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

}