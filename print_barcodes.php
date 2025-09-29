<?php
require_once 'vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Picqer\Barcode\BarcodeGeneratorSVG;

try {
    // Initialize the barcode generator
    $generator = new BarcodeGeneratorPNG();
    
    // Sample barcode data
    $barcodes = [
        ['code' => 'B1756738608481', 'type' => 'C128', 'description' => 'Product A'],
        ['code' => '123456789012', 'type' => 'C128', 'description' => 'Product B'],
        ['code' => '987654321098', 'type' => 'C128', 'description' => 'Product C'],
    ];
    
    // Check if we want to output a specific barcode
    if (isset($_GET['code']) && isset($_GET['format'])) {
        $code = $_GET['code'];
        $format = $_GET['format'];
        
        // Output single barcode as PNG
        if ($format === 'png') {
            header('Content-Type: image/png');
            echo $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, 50);
            exit;
        }
    }
    
    // Display HTML page with barcodes
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Barcode Generator</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                background-color: #f5f5f5;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .barcode-item {
                margin: 20px 0;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
                text-align: center;
            }
            .barcode-code {
                font-weight: bold;
                margin: 10px 0;
                font-family: monospace;
                font-size: 14px;
            }
            .barcode-description {
                color: #666;
                margin-bottom: 15px;
            }
            .barcode-image {
                margin: 10px 0;
            }
            .download-links {
                margin-top: 10px;
            }
            .download-links a {
                display: inline-block;
                margin: 0 5px;
                padding: 5px 10px;
                background: #007cba;
                color: white;
                text-decoration: none;
                border-radius: 3px;
                font-size: 12px;
            }
            .download-links a:hover {
                background: #005a87;
            }
            .print-button {
                background: #28a745;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                margin: 20px 0;
            }
            .print-button:hover {
                background: #218838;
            }
            @media print {
                body { background: white; }
                .container { box-shadow: none; }
                .download-links, .print-button { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Barcode Generator</h1>
            
            <button class="print-button" onclick="window.print()">🖨️ Print All Barcodes</button>
            
            <?php
            // Use HTML generator for display
            $htmlGenerator = new BarcodeGeneratorHTML();
            
            foreach ($barcodes as $item) {
                echo '<div class="barcode-item">';
                echo '<div class="barcode-description">' . htmlspecialchars($item['description']) . '</div>';
                echo '<div class="barcode-code">' . htmlspecialchars($item['code']) . '</div>';
                
                // Generate HTML barcode for display
                echo '<div class="barcode-image">';
                echo $htmlGenerator->getBarcode($item['code'], $htmlGenerator::TYPE_CODE_128, 2, 50);
                echo '</div>';
                
                // Download links
                echo '<div class="download-links">';
                echo '<a href="?code=' . urlencode($item['code']) . '&format=png" target="_blank">Download PNG</a>';
                echo '</div>';
                
                echo '</div>';
            }
            ?>
            
            <hr>
            
            <!-- Form to generate custom barcode -->
            <h3>Generate Custom Barcode</h3>
            <form method="GET">
                <div style="margin: 10px 0;">
                    <label for="custom_code">Barcode Value:</label><br>
                    <input type="text" id="custom_code" name="custom_code" value="<?= htmlspecialchars($_GET['custom_code'] ?? '') ?>" style="width: 200px; padding: 5px;">
                </div>
                <div style="margin: 10px 0;">
                    <label for="custom_type">Barcode Type:</label><br>
                    <select id="custom_type" name="custom_type" style="padding: 5px;">
                        <option value="C128" <?= ($_GET['custom_type'] ?? '') === 'C128' ? 'selected' : '' ?>>Code 128</option>
                        <option value="C39" <?= ($_GET['custom_type'] ?? '') === 'C39' ? 'selected' : '' ?>>Code 39</option>
                        <option value="EAN13" <?= ($_GET['custom_type'] ?? '') === 'EAN13' ? 'selected' : '' ?>>EAN 13</option>
                        <option value="UPCA" <?= ($_GET['custom_type'] ?? '') === 'UPCA' ? 'selected' : '' ?>>UPC-A</option>
                    </select>
                </div>
                <button type="submit" style="padding: 8px 15px; background: #007cba; color: white; border: none; border-radius: 3px;">Generate Barcode</button>
            </form>
            
            <?php
            // Display custom barcode if requested
            if (isset($_GET['custom_code']) && !empty($_GET['custom_code'])) {
                $customCode = $_GET['custom_code'];
                $customType = $_GET['custom_type'] ?? 'C128';
                
                // Map form values to generator constants
                $typeMap = [
                    'C128' => $htmlGenerator::TYPE_CODE_128,
                    'C39' => $htmlGenerator::TYPE_CODE_39,
                    'EAN13' => $htmlGenerator::TYPE_EAN_13,
                    'UPCA' => $htmlGenerator::TYPE_UPC_A,
                ];
                
                if (isset($typeMap[$customType])) {
                    echo '<div class="barcode-item">';
                    echo '<div class="barcode-description">Custom Barcode</div>';
                    echo '<div class="barcode-code">' . htmlspecialchars($customCode) . '</div>';
                    echo '<div class="barcode-image">';
                    
                    try {
                        echo $htmlGenerator->getBarcode($customCode, $typeMap[$customType], 2, 50);
                        echo '</div>';
                        echo '<div class="download-links">';
                        echo '<a href="?code=' . urlencode($customCode) . '&format=png" target="_blank">Download PNG</a>';
                        echo '</div>';
                    } catch (Exception $e) {
                        echo '<div style="color: red;">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
            }
            ?>
            
        </div>
    </body>
    </html>
    
    <?php
    
} catch (Exception $e) {
    // Handle any errors
    echo '<div style="color: red; padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; margin: 20px; border-radius: 5px;">';
    echo '<h3>Error:</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>Solution:</strong> Make sure either GD or ImageMagick extension is installed and enabled in your PHP configuration.</p>';
    echo '<p>For XAMPP: Edit php.ini and uncomment <code>extension=gd</code>, then restart Apache.</p>';
    echo '</div>';
}
?>