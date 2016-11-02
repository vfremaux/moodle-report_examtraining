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

require("../../../../config.php");

ob_start();

require_once($CFG->dirroot.'/report/examtraining/locallib.php');

$radar = explode(',', required_param('radar', PARAM_RAW));
$headers = explode(',', optional_param('headers', PARAM_RAW));

// Operates a quarter rotation.
array_push($radar, array_shift($radar));
array_push($radar, array_shift($radar));
array_push($radar, array_shift($radar));
array_push($headers, array_shift($headers));
array_push($headers, array_shift($headers));
array_push($headers, array_shift($headers));

// Output special situations messages.

$imagewidth = 500;
$imageheight = 500;

$im = imagecreate($imagewidth, $imageheight);

$colors['white'] = imagecolorallocate($im, 255, 255, 255);
$colors['black'] = imagecolorallocate($im, 0, 0, 0);
$colors['gray'] = imagecolorallocate($im, 127, 127, 127);
$colors['green'] = imagecolorallocate($im, 160, 201, 77);
$colors['darkgreen'] = imagecolorallocate($im, 88, 111, 40);
$colors['lightgray'] = imagecolorallocate($im, 200, 200, 200);

imagefill($im, 0, 0, $colors['white']);

$c->x = 250;
$c->y = 250;

$font = $CFG->dirroot.'/report/examtraining/gdgenerators/arial.ttf';

for ($j = 5; $j < 200;) {
    for ($i = 0; $i < 12; $i++) {
        $r1->x = $c->x + $j * cos($i * 2 * pi() / 12);
        $r1->y = $c->y + $j * sin($i * 2 * pi() / 12);
        $r2->x = $c->x + $j * cos(($i + 1) * 2 * pi() / 12);
        $r2->y = $c->y + $j * sin(($i + 1) * 2 * pi() / 12);
        imageline($im, $r1->x, $r1->y, $r2->x, $r2->y, $colors['lightgray']);
    }
    $j = $j + 5;
}

$points = array();
for ($i = 0; $i < 12; $i++) {
    $points[] = $c->x + 2 * @$radar[$i] * cos($i * 2 * pi() / 12);
    $points[] = $c->y + 2 * @$radar[$i] * sin($i * 2 * pi() / 12);
    $boxes[] = $c->x + (2 * @$radar[$i] + 15) * cos($i * 2 * pi() / 12);
    $boxes[] = $c->y + (2 * @$radar[$i] + 15) * sin($i * 2 * pi() / 12);
}

imagefilledpolygon($im, $points, 12, $colors['green']);
imagepolygon($im, $points, 12, $colors['darkgreen']);

// Draw percent boxes.
for ($i = 0; $i < 12; $i++) {
    $b->x = $boxes[2 * $i];
    $b->y = $boxes[2 * $i + 1];
    imagefilledrectangle($im, $b->x, $b->y, $b->x + 40, $b->y + 18, $colors['green']);
    imagerectangle($im, $b->x, $b->y, $b->x + 40, $b->y + 18, $colors['darkgreen']);
    imagefttext($im, 10, 0, $b->x + 2, $b->y + 14, $colors['white'], $font, sprintf('%0.2d', @$radar[$i]).' %');
}

for ($i = 0; $i < 12; $i++) {
    $r->x = $c->x + 200 * cos($i * 2 * pi() / 12);
    $r->y = $c->y + 200 * sin($i * 2 * pi() / 12);
    $t->x = $c->x + 210 * cos($i * 2 * pi() / 12) - 10;
    $t->y = $c->y + 210 * sin($i * 2 * pi() / 12);
    imageline($im, $c->x, $c->y, $r->x, $r->y, $colors['lightgray']);
    $catnum = ((($i + 4) % 12));

    if (!$catnum) {
        $catnum = 12;
    }

    if (empty($headers[$i])) {
        imagefttext($im, 9, 0, $t->x, $t->y, $colors['black'], $font, 'Cat. '.$catnum);
    } else {
        imagefttext($im, 9, 0, $t->x, $t->y, $colors['black'], $font, $headers[$i]);
    }
}

// Delivering image.
ob_end_clean();
header("Content-type: image/png");
imagepng($im);
imagedestroy($im);
die;