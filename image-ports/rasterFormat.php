#!/usr/bin/env php
<?php
/**
 * This is a minimal ESC/POS printing script which uses the 'raster format'
 * of image output.
 *
 * The snippet is designed to efficiently delegate image processing to
 * ImageMagick, rather than spend CPU cycles looping over pixels.
 * 
 * Do not attempt to use this snippet in production, get a copy of escpos-php instead!
 */

/**
 * Return data in raster format. This leverages the fact that the PBM image format data
 * is identical to ESC/POS raster data (just the header changes)
 *
 * @param Imagick $im
 * @param int $lineHeight
 *        	Height of printed line in dots. 8 or 24.
 * @return string[]
 */
function toRasterFormat($im) {
	$im -> setFormat('pbm');
	$blob = $im -> getImageBlob();
	$i = strpos($blob, "\n", 3); // Find where header ends
	return substr($blob, $i + 1);
}

/**
 * Generate number as ESC/POS image header
 *
 * @param int $input
 *        	number
 * @param int $length
 *        	number of bytes output
 * @return string
 */
function intLowHigh($input, $length) {
	$outp = "";
	for($i = 0; $i < $length; $i ++) {
		$outp .= chr ( $input % 256 );
		$input = ( int ) ($input / 256);
	}
	return $outp;
}

// Configure
$highDensityHorizontal = true;
$highDensityVertical = true;
$filename = isset($argv[1]) ? $argv[1] : 'tulips.png';

// Load image
$im = new Imagick();
$im->setResourceLimit ( 6, 1 ); // Prevent libgomp1 segfaults, grumble grumble.
$im -> readimage($filename);

// Print
$GS = "\x1d";
$widthBytes = (int)(($im -> getimagewidth() + 7) / 8);
$heightPixels = $im -> getimageheight();
$densityByte = ($highDensityHorizontal ? 0 : 1) + ($highDensityVertical ? 0 : 2);
$header = $GS . "v0" . chr($densityByte) . intLowHigh($widthBytes, 2) . intLowHigh($heightPixels, 2);
echo $header . toRasterFormat($im);
