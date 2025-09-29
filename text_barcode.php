<?php
require_once 'vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;

try {
    $generator = new BarcodeGeneratorPNG();
    $barcode = $generator->getBarcode('TEST123', $generator::TYPE_CODE_128, 2, 50);
    
    header('Content-Type: image/png');
    echo $barcode;
    echo "SUCCESS: Barcode generated!";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>