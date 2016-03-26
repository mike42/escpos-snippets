#!/usr/bin/env php
<?php
/**
 * This is a minimal ESC/POS printing script which uses the 'column format'
 * of image output.
 * 
 * The snippet is designed to efficiently delegate image processing to
 * ImageMagick, rather than spend CPU cycles looping over pixels.
 * 
 * Do not attempt to use this snippet in production, get a copy of escpos-php instead!
 */

/**
 * Return data in column format as array of slices.
 * Operates recursively to save cloning larger image many times.
 *
 * @param Imagick $im        	
 * @param int $lineHeight
 *        	Height of printed line in dots. 8 or 24.
 * @return string[]
 */
function toColumnFormat(Imagick $im, $lineHeight) {
	$imgWidth = $im->getimagewidth ();
	if ($imgWidth == $lineHeight) {
		// Return glob of this panel
		$blob = $im->getimageblob ();
		$i = strpos ( $blob, "\n", 3 );
		return array (
				substr ( $blob, $i + 1 ) 
		);
	} else {
		// Calculations
		$slicesLeft = ceil ( $imgWidth / $lineHeight / 2 );
		$widthLeft = $slicesLeft * $lineHeight;
		$widthRight = $imgWidth - $widthLeft;
		// Slice up
		$left = $im->clone ();
		$left->extentimage ( $widthLeft, $left->getimageheight (), 0, 0 );
		$right = $im->clone ();
		$right->extentimage ( $widthRight < $lineHeight ? $lineHeight : $widthRight, $right->getimageheight (), $widthLeft, 0 );
		return array_merge ( toColumnFormat ( $left, $lineHeight ), toColumnFormat ( $right, $lineHeight ) );
	}
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
$im = new Imagick ();
$im->setResourceLimit ( 6, 1 ); // Prevent libgomp1 segfaults, grumble grumble.
$im->readimage ( $filename );

// Initial rotate. mirror, and extract blobs for each 8 or 24-pixel row
$im->setformat ( 'pbm' );
$im->getimageblob (); // Forces 1-bit rendering now, so that subsequent operations are faster
$im->rotateImage ( '#fff', 90.0 );
$im->flopImage ();
$lineHeight = $highDensityVertical ? 3 : 1;
$blobs = toColumnFormat ( $im, $lineHeight * 8 );

// Generate ESC/POS header and print image
$ESC = "\x1b";
$widthPixels = $im->getimageheight ();
$densityCode = ($highDensityHorizontal ? 1 : 0) + ($highDensityVertical ? 32 : 0);
$header = $ESC . "*" . chr ( $densityCode ) . intLowHigh ( $widthPixels, 2 );
echo $ESC . "3" . chr ( 16 ); // Adjust line-feed size
foreach ( $blobs as $blob ) {
	echo $header . $blob . "\n";
}
echo $ESC . "2"; // Reset line-feed size
