<?php
require_once 'config.php';

// Check if user is logged in and is a cashier
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    die("❌ Access denied.");
}

// Check if receipt data exists
if (!isset($_SESSION['receipt_data'])) {
    die("❌ No receipt data found.");
}

$receipt = $_SESSION['receipt_data'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= $receipt['receipt_id'] ?></title>
    <style>
        @media print {
            body {
                margin: 0;
                padding: 0;
                font-family: 'Courier New', monospace;
                font-size: 12px;
                line-height: 1.4;
                color: #000;
                background: #fff;
            }
            
            .no-print {
                display: none !important;
            }
            
            .receipt-container {
                width: 80mm;
                max-width: 300px;
                margin: 0 auto;
                padding: 10px;
            }
            
            @page {
                size: 80mm auto;
                margin: 0;
            }
        }

        @media screen {
            body {
                font-family: 'Courier New', monospace;
                font-size: 14px;
                line-height: 1.4;
                background-color: #f5f5f5;
                margin: 0;
                padding: 20px;
            }
            
            .receipt-container {
                max-width: 350px;
                margin: 0 auto;
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .print-controls {
                text-align: center;
                margin-bottom: 20px;
                padding: 15px;
                background: #e3f2fd;
                border-radius: 8px;
            }
            
            .btn {
                display: inline-block;
                padding: 10px 20px;
                margin: 0 5px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                text-decoration: none;
                font-weight: bold;
                transition: all 0.3s ease;
            }
            
            .btn-primary {
                background-color: #2196F3;
                color: white;
            }
            
            .btn-primary:hover {
                background-color: #1976D2;
            }
            
            .btn-secondary {
                background-color: #757575;
                color: white;
            }
            
            .btn-secondary:hover {
                background-color: #616161;
            }
        }

        .receipt {
            text-align: center;
            width: 100%;
        }

        .store-header {
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .store-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .store-info {
            font-size: 11px;
            margin-bottom: 3px;
        }

        .receipt-info {
            margin: 15px 0;
            font-size: 11px;
        }

        .receipt-number {
            font-size: 12px;
            font-weight: bold;
            margin: 8px 0;
        }

        .items-section {
            text-align: left;
            margin: 15px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 10px 0;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
            font-size: 11px;
        }

        .item-name {
            flex: 1;
            margin-right: 10px;
        }

        .item-details {
            font-size: 10px;
            color: #666;
            margin: 2px 0 8px 10px;
        }

        .totals-section {
            text-align: right;
            margin: 15px 0;
            padding-top: 10px;
            border-top: 2px dashed #000;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 12px;
        }

        .total-row.grand-total {
            font-weight: bold;
            font-size: 14px;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 10px;
        }

        .footer-section {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #000;
            text-align: center;
            font-size: 11px;
        }

        .thank-you {
            font-weight: bold;
            margin: 10px 0;
        }

        .barcode-section {
            margin: 15px 0;
            text-align: center;
            font-family: 'Libre Barcode 39', monospace;
            font-size: 24px;
            letter-spacing: 2px;
        }

        @media print {
            .barcode-section {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <h2>Receipt Preview</h2>
        <button class="btn btn-primary" onclick="printReceipt()">
            🖨️ Print Receipt
        </button>
        <a href="pos.php" class="btn btn-secondary">
            ← Back to POS
        </a>
    </div>

    <div class="receipt-container">
        <div class="receipt">
            <!-- Store Header -->
            <div class="store-header">
                <div class="store-name">TIPTOP MARKET</div>
                <div class="store-info">Tupi Public Market</div>
                <div class="store-info">South Cotabato 9505</div>
                <div class="store-info">Phone: (02) 123-4567</div>
                <div class="store-info">Email: tiptop@gmail.com</div>
            </div>

            <!-- Receipt Information -->
            <div class="receipt-info">
                <div class="receipt-number">Receipt #: <?= htmlspecialchars($receipt['receipt_id']) ?></div>
                <div>Date: <?= date('M d, Y', strtotime($receipt['date'])) ?></div>
                <div>Time: <?= date('h:i A', strtotime($receipt['date'])) ?></div>
                <div>Cashier: <?= htmlspecialchars($receipt['cashier']) ?></div>
            </div>

            <!-- Items Section -->
            <div class="items-section">
                <?php foreach ($receipt['items'] as $item): ?>
                <div class="item-row">
                    <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                    <span>₱<?= number_format($item['total'], 2) ?></span>
                </div>
                <div class="item-details">
                    <?= $item['quantity'] ?> x ₱<?= number_format($item['price'], 2) ?> each
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Totals Section -->
            <div class="totals-section">
                <div class="total-row">
                    <span>Items Count:</span>
                    <span><?= array_sum(array_column($receipt['items'], 'quantity')) ?></span>
                </div>
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>₱<?= number_format($receipt['subtotal'], 2) ?></span>
                </div>
                <div class="total-row">
                    <span>Tax (0%):</span>
                    <span>₱0.00</span>
                </div>
                <div class="total-row grand-total">
                    <span>TOTAL:</span>
                    <span>₱<?= number_format($receipt['subtotal'], 2) ?></span>
                </div>
                <div class="total-row" style="margin-top: 10px; border-top: 1px dashed #000; padding-top: 5px;">
                    <span>Cash Paid:</span>
                    <span>₱<?= number_format($receipt['cash_paid'], 2) ?></span>
                </div>
                <div class="total-row">
                    <span>Change:</span>
                    <span>₱<?= number_format($receipt['change'], 2) ?></span>
                </div>
            </div>

            <!-- Barcode Section (Receipt ID as barcode) -->
            <div class="barcode-section">
                *<?= strtoupper($receipt['receipt_id']) ?>*
            </div>

            <!-- Footer Section -->
            <div class="footer-section">
                <div class="thank-you">THANK YOU FOR YOUR BUSINESS!</div>
                <div>Please keep this receipt for your records</div>
                <div style="margin-top: 10px;">
                    Visit us again soon!
                </div>
                <div style="margin-top: 15px; font-size: 10px;">
                    No returns without receipt
                </div>
                <div style="font-size: 10px;">
                    Valid for 30 days from purchase
                </div>
            </div>

            <!-- Transaction Details for Records -->
            <div style="margin-top: 20px; font-size: 10px; text-align: left; border-top: 1px dashed #000; padding-top: 10px;">
                <div><strong>Transaction Details:</strong></div>
                <div>Receipt ID: <?= $receipt['receipt_id'] ?></div>
                <div>Date/Time: <?= $receipt['date'] ?></div>
                <div>Cashier ID: <?= $_SESSION['user_id'] ?></div>
                <div>Terminal: POS-01</div>
            </div>
        </div>
    </div>

    <script>
        function printReceipt() {
            // Hide print controls before printing
            const controls = document.querySelector('.print-controls');
            if (controls) {
                controls.style.display = 'none';
            }
            
            // Print the page
            window.print();
            
            // Show controls again after printing
            setTimeout(() => {
                if (controls) {
                    controls.style.display = 'block';
                }
            }, 1000);
        }

        // Auto-print on load (optional - you can remove this if you don't want auto-print)
        window.addEventListener('load', function() {
            // Uncomment the next line if you want to auto-print when page loads
            // setTimeout(printReceipt, 1000);
        });

        // Handle print dialog close
        window.addEventListener('afterprint', function() {
            // You can add any post-print logic here
            console.log('Print dialog closed');
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+P or F12 to print
            if ((e.ctrlKey && e.key === 'p') || e.key === 'F12') {
                e.preventDefault();
                printReceipt();
            }
            
            // ESC to go back to POS
            if (e.key === 'Escape') {
                window.location.href = 'pos.php';
            }
        });
    </script>
</body>
</html>

<?php
// Clear the receipt data after displaying (optional)
// unset($_SESSION['receipt_data']);
?>