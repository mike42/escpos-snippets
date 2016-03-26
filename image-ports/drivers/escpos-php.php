#!/usr/bin/env php
<?php
/* Print-outs using the newer graphics print command */

require __DIR__ . '/../../drivers/escpos-php/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
$connector = new FilePrintConnector("php://stdout");
$printer = new Printer($connector);

try {
    $path = isset($argv[1]) ? $argv[1] : __DIR__ . "/../tulips.png";
	$im = new EscposImage($path);
	$printer -> bitImage($im);
} catch(Exception $e) {
	/* Images not supported on your PHP, or image file not found */
	$printer -> text($e -> getMessage() . "\n");
}

$printer -> close();
?>
