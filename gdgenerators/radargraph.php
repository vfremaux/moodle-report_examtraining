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
 * This file contains functions used by the examtraining report
 *
 * @package    report
 * @subpackage examtraining
 * @copyright  2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require("../../../config.php");

ob_start();

require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$radar = explode(',', required_param('radar', PARAM_RAW));
$headers = explode(',', optional_param('headers', '',  PARAM_RAW));
$fcolor = optional_param('fcolor', '#A0D04D', PARAM_TEXT);
$bcolor = optional_param('bcolor', '#708030', PARAM_TEXT);
$width = optional_param('width', 700, PARAM_INT);
$height = optional_param('height', 500, PARAM_INT);
$branches = optional_param('branches', 12, PARAM_INT);

// Operates a quarter rotation.
array_push($radar, array_shift($radar));
array_push($radar, array_shift($radar));
array_push($radar, array_shift($radar));
array_push($headers, array_shift($headers));
array_push($headers, array_shift($headers));
array_push($headers, array_shift($headers));

// Output special situations messages.

$im = imagecreatetruecolor($width, $height);
imageantialias($im, true);

$colors['white'] = imagecolorallocate($im, 255, 255, 255);
$colors['black'] = imagecolorallocate($im, 0, 0, 0);
$colors['gray'] = imagecolorallocate($im, 127, 127, 127);
$colors['fill'] = examtraining_allocate_html_color($im, $fcolor); // #A0D04D
$colors['border'] = examtraining_allocate_html_color($im, $bcolor); // #708030
$colors['lightgray'] = imagecolorallocate($im, 200, 200, 200);

imagefill($im, 0, 0, $colors['white']);

$curvefactor = 0.8;

$c->x = $width / 2 - 100;
$c->y = $height / 2;
$maxx = floor($c->x * $curvefactor);

$font = $CFG->dirroot.'/report/examtraining/gdgenerators/arial.ttf';

for ($j = 20; $j <= $maxx;) {
    for ($i = 0; $i < $branches; $i++) {
        $r1->x = $c->x + $j * cos($i * 2 * pi() / $branches);
        $r1->y = $c->y + $j * sin($i * 2 * pi() / $branches);
        $r2->x = $c->x + $j * cos(($i + 1) * 2 * pi() / $branches);
        $r2->y = $c->y + $j * sin(($i + 1) * 2 * pi() / $branches);
        imageline($im, $r1->x, $r1->y, $r2->x, $r2->y, $colors['lightgray']);
    }
    $j = $j + 20;
}

$points = [];

for ($i = 0; $i < $branches; $i++) {
    $X = $c->x + 2 * @$radar[$i] * cos($i * 2 * pi() / $branches) * $curvefactor;
    $points[] = $X;
    $Y = $c->y + 2 * @$radar[$i] * sin($i * 2 * pi() / $branches) * $curvefactor;
    $points[] = $Y;
    if (@$radar[$i] > 70) {
        $boxes[] = $c->x + (2 * @$radar[$i] - 40) * cos($i * 2 * pi() / $branches) * $curvefactor;
        $boxes[] = $c->y + (2 * @$radar[$i] - 40) * sin($i * 2 * pi() / $branches) * $curvefactor;
    } else {
        $boxes[] = $c->x + (2 * @$radar[$i] + 20) * cos($i * 2 * pi() / $branches);
        $boxes[] = $c->y + (2 * @$radar[$i] + 20) * sin($i * 2 * pi() / $branches);
    }
}

imagefilledpolygon($im, $points, $branches, $colors['fill']);
imagesetthickness($im, 4);
imagepolygon($im, $points, $branches, $colors['border']);
imagesetthickness($im, 1);

// Draw percent boxes.
for ($i = 0; $i < $branches; $i++) {
    $b->x = $boxes[2 * $i];
    $b->y = $boxes[2 * $i + 1];
    imagefilledrectangle($im, $b->x, $b->y, $b->x + 40, $b->y + 18, $colors['fill']);
    imagerectangle($im, $b->x, $b->y, $b->x + 40, $b->y + 18, $colors['border']);
    imagefttext($im, 10, 0, $b->x + 2, $b->y + 14, $colors['white'], $font, sprintf('%0.2d', @$radar[$i]).' %');
}

for ($i = 0; $i < $branches; $i++) {
    $r->x = $c->x + $maxx * cos($i * 2 * pi() / $branches);
    $r->y = $c->y + $maxx * sin($i * 2 * pi() / $branches);
    $t->x = $c->x + ($maxx + 10) * cos($i * 2 * pi() / $branches) - 10;
    $t->y = $c->y + ($maxx + 10) * sin($i * 2 * pi() / $branches);
    imageline($im, $c->x, $c->y, $r->x, $r->y, $colors['lightgray']);
    $catnum = ((($i + 4) % 12));

    if (!$catnum) {
        $catnum = $branches;
    }

    if (empty($headers[$i])) {
        imagefttext($im, 10, 0, $t->x, $t->y + 10, $colors['black'], $font, 'Cat. '.$catnum);
    } else {
        imagefttext($im, 10, 0, $t->x, $t->y + 10, $colors['black'], $font, $headers[$i]);
    }
}

// Delivering image.
ob_end_clean();
header("Content-type: image/png");
imagepng($im);
imagedestroy($im);
die;