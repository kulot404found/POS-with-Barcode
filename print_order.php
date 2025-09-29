<?php
session_start();
require_once 'config.php';

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("❌ Access denied.");
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    die("Invalid order ID");
}

try {
    // Fetch order details with supplier information
    $stmt = $db->prepare("
        SELECT po.*, s.name AS supplier_name, s.email AS supplier_email, 
               s.phone AS supplier_phone, s.address AS supplier_address,
               u.username AS created_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found");
    }

    // Fetch order items based on your exact table structure
    $stmt = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY item_name");
    $stmt->execute([$order_id]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error fetching order: " . $e->getMessage());
}

// Calculate totals based on your table columns
$subtotal = 0;
$totalItems = 0;
$totalQuantity = 0;
foreach ($orderItems as $item) {
    $subtotal += $item['total_cost']; // Using total_cost column
    $totalItems++;
    $totalQuantity += $item['quantity'];
}

$grandTotal = $subtotal; // VAT removed
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $order['id'] ?> - Print</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            border: 3px solid #000;
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #000;
            padding-bottom: 15px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
        }
        
        .header h1 {
            font-size: 26px;
            margin: 0;
            font-weight: bold;
            color: #222;
        }
        
        .header p {
            margin: 3px 0;
            font-size: 12px;
            color: #555;
        }
        
        .receipt-info {
            margin-bottom: 20px;
            border: 2px solid #000;
            padding: 15px;
            border-radius: 6px;
            background-color: #fafafa;
        }
        
        .receipt-info .row {
            margin-bottom: 10px;
        }
        
        .supplier-details, .order-summary {
            padding: 15px;
            border: 2px solid #000;
            border-radius: 8px;
            background-color: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .supplier-details h6, .order-summary h6 {
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            font-weight: bold;
            color: #222;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 3px solid #000;
            background-color: #fff;
        }
        
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 10px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #e8e8e8;
            font-weight: bold;
            border-bottom: 2px solid #000;
            text-transform: uppercase;
            font-size: 11px;
            color: #222;
        }
        
        .items-table td {
            background-color: #fff;
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .totals {
            max-width: 300px;
            margin-left: auto;
            border-top: 3px solid #000;
            border-bottom: 3px solid #000;
            padding: 10px 0;
            background-color: #f8f9fa;
            border-radius: 6px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #000;
        }
        
        .grand-total {
            font-weight: bold;
            font-size: 14px;
            padding: 10px 0;
            color: #222;
        }
        
        .terms {
            margin-top: 20px;
            font-size: 11px;
            border: 2px solid #000;
            padding: 15px;
            border-radius: 6px;
            background-color: #fafafa;
        }
        
        .terms h6 {
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            font-weight: bold;
            color: #222;
        }
        
        .terms ul {
            padding-left: 20px;
            margin: 10px 0;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #333;
            border-top: 3px solid #000;
            padding-top: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <!-- Print Button (hidden when printing) -->
    <div class="no-print text-center mb-3">
        <button onclick="window.print()" class="btn btn-primary me-2">Print Receipt</button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>

    <div class="container">
        <!-- Company Header -->
        <div class="header">
            <h1>TIPTOP MARKET</h1>
            <p>Tupi Public Market, South Cotabato 9505</p>
            <p>Phone: (02) 123-4567 | Email: tiptop@gmail.com</p>

        </div>

        <!-- Receipt Title and Info -->
        <h2 class="text-center mb-3">OFFICIAL RECEIPT</h2>
        <div class="receipt-info">
            <div class="row">
                <div class="col-6">
                    <strong>Receipt #:</strong> <?= $order['id'] ?><br>
                    <strong>Date:</strong> <?= date('F j, Y', strtotime($order['created_at'])) ?><br>
                    <?php if ($order['expected_delivery_date']): ?>
                        <strong>Delivery Date:</strong> <?= date('F j, Y', strtotime($order['expected_delivery_date'])) ?><br>
                    <?php endif; ?>
                    <?php if ($order['created_by_name']): ?>
                        <strong>Prepared By:</strong> <?= htmlspecialchars($order['created_by_name']) ?><br>
                    <?php endif; ?>
                </div>
                <div class="col-6 text-end">
                    <strong>Status:</strong> <?= htmlspecialchars($order['status']) ?><br>
                    <strong>Priority:</strong> <?= htmlspecialchars($order['priority']) ?>
                </div>
            </div>
        </div>

        <!-- Supplier and Summary -->
        <div class="row mb-4">
            <div class="col-6">
                <div class="supplier-details">
                    <h6>Supplier Details</h6>
                    <p><strong>Name:</strong> <?= htmlspecialchars($order['supplier_name']) ?></p>
                    <?php if ($order['supplier_address']): ?>
                        <p><strong>Address:</strong> <?= htmlspecialchars($order['supplier_address']) ?></p>
                    <?php endif; ?>
                    <?php if ($order['supplier_phone']): ?>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($order['supplier_phone']) ?></p>
                    <?php endif; ?>
                    <?php if ($order['supplier_email']): ?>
                        <p><strong>Email:</strong> <?= htmlspecialchars($order['supplier_email']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6">
                <div class="order-summary">
                    <h6>Order Summary</h6>
                    <p><strong>Total Items:</strong> <?= $totalItems ?></p>
                    <p><strong>Total Quantity:</strong> <?= $totalQuantity ?> pcs</p>
                    <p><strong>Total Value:</strong> ₱<?= number_format($grandTotal, 2) ?></p>
                    <?php if ($order['notes']): ?>
                        <p><strong>Notes:</strong> <?= htmlspecialchars($order['notes']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Description</th>
                    <th class="text-center">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orderItems)): ?>
                    <tr>
                        <td colspan="5" class="text-center">No items found.</td>
                    </tr>
                <?php else: ?>
                    <?php $itemNum = 1; ?>
                    <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td><?= $itemNum++ ?></td>
                            <td>
                                <?= htmlspecialchars($item['item_name']) ?><br>
                                <small>Item ID: <?= $item['item_id'] ?></small>
                            </td>
                            <td class="text-center"><?= number_format($item['quantity']) ?> pcs</td>
                            <td class="text-right">₱<?= number_format($item['unit_cost'], 2) ?></td>
                            <td class="text-right">₱<?= number_format($item['total_cost'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Total:</span>
                <span>₱<?= number_format($grandTotal, 2) ?></span>
            </div>
            <div class="total-row grand-total">
                <span>Grand Total:</span>
                <span>₱<?= number_format($grandTotal, 2) ?></span>
            </div>
        </div>

        <!-- Terms -->
        <div class="terms">
            <h6>Terms and Conditions:</h6>
            <ul>
                <li>Payment terms: Net 30 days from delivery</li>
                <li>Goods must be delivered in good condition</li>
                <li>Delivery should be made during business hours (8:00 AM - 5:00 PM)</li>
                <li>Any discrepancies must be reported within 24 hours of delivery</li>
                <li>Supplier must provide delivery receipt and invoice</li>
                <li>Price includes delivery to specified location</li>
            </ul>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated receipt. No signature required.</p>
            <p>Generated on <?= date('F j, Y \a\t g:i A') ?> | Receipt #<?= $order['id'] ?></p>
        </div>
    </div>

    <script>
        // Optional auto-print
        // window.addEventListener('load', () => setTimeout(window.print, 500));
        
        // Optional close after print
        window.addEventListener('afterprint', () => {
            // window.close();
        });
    </script>
</body>
</html>