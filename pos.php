<?php
session_start();
require_once 'config.php';

// Modified access control to allow both cashier and admin roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['cashier', 'admin'])) {
    die("❌ Access denied.");
}

// Initialize cart (stored in session)
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fetch all inventory items for dropdown
$items = $db->query("SELECT id, name, stock, price, barcode FROM inventory")->fetchAll(PDO::FETCH_ASSOC);

// Function to get available stock for an item (considering cart items)
function getAvailableStock($item_id, $cart, $current_stock) {
    $cart_quantity = 0;
    foreach ($cart as $cart_item) {
        if ($cart_item['item_id'] == $item_id) {
            $cart_quantity += $cart_item['quantity'];
        }
    }
    return max(0, $current_stock - $cart_quantity);
}

// Handle barcode lookup (both manual and camera)
$itemDetails = null;
if (isset($_POST['barcode']) || isset($_POST['camera_barcode'])) {
    $barcode = isset($_POST['barcode']) ? $_POST['barcode'] : $_POST['camera_barcode'];
    $stmt = $db->prepare("SELECT id, name, stock, price, barcode FROM inventory WHERE barcode = ?");
    $stmt->execute([$barcode]);
    $itemDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If item found and auto_add is enabled, automatically add to cart
    if ($itemDetails && isset($_POST['auto_add']) && $_POST['auto_add'] === '1') {
        $quantity = 1; // Default quantity for camera scan
        $available_stock = getAvailableStock($itemDetails['id'], $_SESSION['cart'], $itemDetails['stock']);
        
        if ($available_stock >= $quantity) {
            $_SESSION['cart'][] = [
                'item_id' => $itemDetails['id'],
                'name' => $itemDetails['name'],
                'quantity' => $quantity,
                'price' => $itemDetails['price'],
                'total' => $itemDetails['price'] * $quantity
            ];
            $success_message = "Item automatically added to cart!";
        } else {
            $error_message = "Insufficient stock. Available: " . $available_stock . " units";
        }
    }
}

// Handle manual item selection
if (isset($_POST['add_to_cart'])) {
    $item_id = $_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    $stmt = $db->prepare("SELECT id, name, stock, price FROM inventory WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($item && $quantity > 0) {
        $available_stock = getAvailableStock($item['id'], $_SESSION['cart'], $item['stock']);
        
        if ($available_stock >= $quantity) {
            $_SESSION['cart'][] = [
                'item_id' => $item['id'],
                'name' => $item['name'],
                'quantity' => $quantity,
                'price' => $item['price'],
                'total' => $item['price'] * $quantity
            ];
            $success_message = "Item added to cart!";
        } else {
            $error_message = "Insufficient stock. Available: " . $available_stock . " units";
        }
    } else {
        $error_message = "Invalid quantity.";
    }
}

// Handle checkout
if (isset($_POST['checkout'])) {
    $cash_paid = (float)$_POST['cash_paid'];
    $cart_total = array_sum(array_column($_SESSION['cart'], 'total'));
    if ($cash_paid >= $cart_total && $cart_total > 0) {
        try {
            $db->beginTransaction();
            $user_id = $_SESSION['user_id'];
            $receipt_id = uniqid('RCP');
            
            // Validate stock one more time before checkout
            $stock_valid = true;
            foreach ($_SESSION['cart'] as $cart_item) {
                $stmt = $db->prepare("SELECT stock FROM inventory WHERE id = ?");
                $stmt->execute([$cart_item['item_id']]);
                $current_stock = $stmt->fetchColumn();
                
                $cart_total_for_item = 0;
                foreach ($_SESSION['cart'] as $check_item) {
                    if ($check_item['item_id'] == $cart_item['item_id']) {
                        $cart_total_for_item += $check_item['quantity'];
                    }
                }
                
                if ($current_stock < $cart_total_for_item) {
                    $stock_valid = false;
                    $error_message = "Stock insufficient for " . $cart_item['name'] . ". Please review cart.";
                    break;
                }
            }
            
            if ($stock_valid) {
                foreach ($_SESSION['cart'] as $cart_item) {
                    $db->prepare("INSERT INTO sales (user_id, item_id, quantity, total_amount) VALUES (?, ?, ?, ?)")
                       ->execute([$user_id, $cart_item['item_id'], $cart_item['quantity'], $cart_item['total']]);
                    $db->prepare("UPDATE inventory SET stock = stock - ? WHERE id = ?")
                       ->execute([$cart_item['quantity'], $cart_item['item_id']]);
                }
                $db->commit();
                $change = $cash_paid - $cart_total;
                
                // Store receipt data in session for printing
                $_SESSION['receipt_data'] = [
                    'receipt_id' => $receipt_id,
                    'items' => $_SESSION['cart'],
                    'subtotal' => $cart_total,
                    'cash_paid' => $cash_paid,
                    'change' => $change,
                    'date' => date('Y-m-d H:i:s'),
                    'cashier' => $_SESSION['username']
                ];
                
                $_SESSION['cart'] = [];
                $checkout_success = true;
            } else {
                $db->rollBack();
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Checkout failed: " . $e->getMessage());
            $error_message = "Failed to process sale. Please try again.";
        }
    } else {
        $error_message = "Insufficient payment or empty cart.";
    }
}

// Handle cart item removal
if (isset($_POST['remove_item'])) {
    $index = (int)$_POST['remove_index'];
    if (isset($_SESSION['cart'][$index])) {
        array_splice($_SESSION['cart'], $index, 1);
        $success_message = "Item removed from cart!";
    }
}

$cart_total = array_sum(array_column($_SESSION['cart'], 'total'));
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>POS Terminal - Cashier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --pos-primary: #4f46e5;
            --pos-primary-dark: #3730a3;
            --pos-secondary: #1f2937;
            --pos-success: #10b981;
            --pos-success-dark: #047857;
            --pos-warning: #f59e0b;
            --pos-danger: #ef4444;
            --pos-danger-dark: #dc2626;
            --pos-info: #3b82f6;
            --pos-dark: #111827;
            --pos-light: #f8fafc;
            --pos-gray-50: #f9fafb;
            --pos-gray-100: #f3f4f6;
            --pos-gray-200: #e5e7eb;
            --pos-gray-300: #d1d5db;
            --pos-gray-400: #9ca3af;
            --pos-gray-500: #6b7280;
            --pos-gray-600: #4b5563;
            --pos-gray-700: #374151;
            --pos-gray-800: #1f2937;
            --pos-gray-900: #111827;
            --pos-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --pos-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --pos-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --pos-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --pos-shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --pos-radius: 12px;
            --pos-radius-sm: 8px;
            --pos-radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow: hidden;
            font-weight: 400;
        }

        /* Top Navigation Bar */
        .pos-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--pos-gray-800);
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--pos-shadow-lg);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        .pos-navbar .brand {
            font-size: 18px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--pos-primary);
            text-shadow: 0 2px 4px rgba(79, 70, 229, 0.1);
        }

        .pos-navbar .brand i {
            font-size: 22px;
            background: linear-gradient(45deg, var(--pos-primary), var(--pos-info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .pos-navbar .nav-info {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .pos-navbar .nav-badge {
            background: linear-gradient(45deg, var(--pos-danger), #ff6b6b);
            color: white;
            padding: 5px 12px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 11px;
            box-shadow: var(--pos-shadow);
        }

        .pos-navbar .logout-btn {
            background: linear-gradient(45deg, var(--pos-gray-600), var(--pos-gray-700));
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: var(--pos-radius-sm);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--pos-shadow);
        }

        .pos-navbar .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--pos-shadow-md);
            background: linear-gradient(45deg, var(--pos-gray-700), var(--pos-gray-800));
            color: white;
        }

        /* Main Grid Layout */
        .pos-main {
            height: calc(100vh - 60px);
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 16px;
            padding: 16px;
            margin-top: 60px;
        }

        /* Alert Bar */
        .pos-alerts {
            grid-column: 1 / -1;
            margin-bottom: 0;
            position: fixed;
            top: 60px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 600px;
            z-index: 1001;
        }

        .alert-pos {
            border: none;
            border-radius: var(--pos-radius);
            padding: 12px 16px;
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            backdrop-filter: blur(10px);
            box-shadow: var(--pos-shadow-md);
            opacity: 1;
            transition: opacity 0.5s ease;
        }

        .alert-pos.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95));
            color: white;
            border-left: 4px solid rgba(255, 255, 255, 0.5);
        }

        .alert-pos.danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.95), rgba(220, 38, 38, 0.95));
            color: white;
            border-left: 4px solid rgba(255, 255, 255, 0.5);
        }

        .alert-pos.hidden {
            opacity: 0;
        }

        /* Input Area */
        .pos-input-area {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--pos-radius-lg);
            box-shadow: var(--pos-shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 16px;
            padding: 16px;
            overflow: hidden;
        }

        .input-controls {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Scanner Section - ENHANCED */
        .pos-scanner {
            padding: 20px;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.08), rgba(59, 130, 246, 0.08));
            border-radius: var(--pos-radius);
            border: 2px solid rgba(79, 70, 229, 0.1);
        }

        .pos-scanner-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .pos-scanner-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--pos-gray-800);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .scanner-toggle {
            background: linear-gradient(45deg, var(--pos-success), #22c55e);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: var(--pos-radius-sm);
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--pos-shadow);
        }

        .scanner-toggle:hover {
            transform: translateY(-2px);
            box-shadow: var(--pos-shadow-md);
        }

        .scanner-toggle.active {
            background: linear-gradient(45deg, var(--pos-danger), #ff4757);
        }

        .scanner-toggle.active:hover {
            background: linear-gradient(45deg, #ff4757, var(--pos-danger-dark));
        }

        /* ENLARGED CAMERA VIEWPORT */
        .camera-viewport {
            background: var(--pos-gray-900);
            border-radius: var(--pos-radius);
            position: relative;
            height: 240px;
            width: 100%;
            max-width: 380px;
            margin: 0 auto 16px auto;
            overflow: hidden;
            box-shadow: var(--pos-shadow-lg);
            border: 3px solid rgba(79, 70, 229, 0.2);
            transition: all 0.3s ease;
        }

        .camera-viewport.active {
            border: 3px solid var(--pos-success);
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.4);
            animation: flashBorder 0.8s ease;
        }

        @keyframes flashBorder {
            0% { border-color: var(--pos-success); box-shadow: 0 0 25px rgba(16, 185, 129, 0.4); }
            50% { border-color: #34d399; box-shadow: 0 0 35px rgba(52, 211, 153, 0.6); }
            100% { border-color: var(--pos-success); box-shadow: 0 0 25px rgba(16, 185, 129, 0.4); }
        }

        #camera-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scale(1.02);
            transform-origin: center center;
        }

        /* IMPROVED CAMERA OVERLAY */
        .camera-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 70%;
            height: 40%;
            border: 3px solid var(--pos-success);
            border-radius: 8px;
            box-shadow: inset 0 0 0 2px rgba(16, 185, 129, 0.3), 0 0 30px rgba(16, 185, 129, 0.5);
            animation: scannerPulse 2s ease-in-out infinite;
            display: none;
        }

        .camera-viewport.active .camera-overlay {
            display: block;
        }

        @keyframes scannerPulse {
            0%, 100% { 
                opacity: 0.9; 
                box-shadow: inset 0 0 0 2px rgba(16, 185, 129, 0.4), 0 0 30px rgba(16, 185, 129, 0.5);
                transform: translate(-50%, -50%) scale(1);
            }
            50% { 
                opacity: 1; 
                box-shadow: inset 0 0 0 2px rgba(16, 185, 129, 0.6), 0 0 40px rgba(16, 185, 129, 0.7);
                transform: translate(-50%, -50%) scale(1.02);
            }
        }

        /* ENHANCED SCANNING LINE */
        .camera-overlay::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.8), var(--pos-success), rgba(16, 185, 129, 0.8), transparent);
            animation: scanLine 2.5s linear infinite;
            border-radius: 2px;
        }

        @keyframes scanLine {
            0% { transform: translateY(-3px); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(calc(96px - 3px)); opacity: 0; }
        }

        /* CORNER INDICATORS */
        .camera-overlay::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            background: linear-gradient(45deg, var(--pos-success) 0%, transparent 20%), 
                        linear-gradient(-45deg, var(--pos-success) 0%, transparent 20%),
                        linear-gradient(135deg, var(--pos-success) 0%, transparent 20%),
                        linear-gradient(-135deg, var(--pos-success) 0%, transparent 20%);
            background-size: 25px 25px;
            background-position: top left, top right, bottom left, bottom right;
            background-repeat: no-repeat;
            border-radius: 8px;
        }

        .scanner-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 4px;
        }

        .auto-add-control {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .auto-add-control input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--pos-success);
        }

        .scanner-status {
            font-size: 11px;
            padding: 6px 12px;
            border-radius: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .scanner-status.active {
            background: linear-gradient(45deg, var(--pos-success), #22c55e);
            color: white;
            box-shadow: var(--pos-shadow);
        }

        .scanner-status.inactive {
            background: var(--pos-gray-100);
            color: var(--pos-gray-500);
        }

        .scanner-status.error {
            background: linear-gradient(45deg, var(--pos-danger), #ff4757);
            color: white;
        }

        .scanner-status.scanning {
            background: linear-gradient(45deg, var(--pos-warning), #fbbf24);
            color: white;
            animation: scanningPulse 1.5s ease-in-out infinite;
        }

        @keyframes scanningPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* PERMISSION HELPER */
        .camera-permission-helper {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
            background: rgba(0, 0, 0, 0.8);
            padding: 20px;
            border-radius: 10px;
            font-size: 14px;
            z-index: 10;
        }

        .permission-icon {
            font-size: 32px;
            margin-bottom: 10px;
            color: var(--pos-warning);
        }

        /* Item Display */
        .item-display {
            padding: 20px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(79, 70, 229, 0.08));
            border-radius: var(--pos-radius);
            overflow-y: auto;
            max-height: 100%;
        }

        /* Barcode Input */
        .pos-barcode {
            padding: 16px;
            border-top: 1px solid var(--pos-gray-200);
        }

        .barcode-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--pos-gray-800);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .barcode-input-group {
            display: flex;
            gap: 0;
            box-shadow: var(--pos-shadow-md);
            border-radius: var(--pos-radius-sm);
            overflow: hidden;
        }

        .barcode-input {
            flex: 1;
            padding: 10px 14px;
            border: none;
            font-size: 14px;
            font-family: 'SF Mono', 'Monaco', monospace;
            background: white;
            font-weight: 600;
        }

        .barcode-input:focus {
            outline: none;
            background: rgba(79, 70, 229, 0.05);
        }

        .barcode-btn {
            background: linear-gradient(45deg, var(--pos-primary), var(--pos-info));
            color: white;
            border: none;
            padding: 10px 18px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .barcode-btn:hover {
            transform: translateX(-2px);
            background: linear-gradient(45deg, var(--pos-primary-dark), var(--pos-primary));
        }

        /* Item Card */
        .item-card {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.08), rgba(59, 130, 246, 0.08));
            border: 2px solid rgba(79, 70, 229, 0.2);
            border-radius: var(--pos-radius);
            padding: 16px;
            box-shadow: var(--pos-shadow-lg);
            animation: slideInRight 0.3s ease-out;
            transition: all 0.3s ease;
        }

        .item-card-header {
            font-size: 18px;
            font-weight: 800;
            color: var(--pos-gray-800);
            margin-bottom: 12px;
            background: linear-gradient(45deg, var(--pos-primary), var(--pos-info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .item-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .item-detail {
            padding: 10px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: var(--pos-radius-sm);
            box-shadow: var(--pos-shadow-sm);
            text-align: center;
        }

        .item-detail-label {
            font-size: 10px;
            color: var(--pos-gray-500);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
        }

        .item-detail-value {
            font-size: 16px;
            font-weight: 800;
            color: var(--pos-gray-800);
        }

        .add-to-cart-section {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .quantity-input {
            width: 80px;
            padding: 10px 14px;
            border: 2px solid var(--pos-gray-200);
            border-radius: var(--pos-radius-sm);
            text-align: center;
            font-weight: 700;
            font-size: 14px;
            box-shadow: var(--pos-shadow-sm);
        }

        .quantity-input:focus {
            outline: none;
            border-color: var(--pos-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .add-btn {
            background: linear-gradient(45deg, var(--pos-success), #22c55e);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: var(--pos-radius-sm);
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--pos-shadow);
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--pos-shadow-md);
        }

        /* Manual Selection */
        .pos-manual {
            padding: 16px;
            border-top: 1px solid var(--pos-gray-200);
        }

        .manual-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--pos-gray-800);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .manual-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 10px;
            align-items: center;
        }

        .pos-select {
            padding: 10px 14px;
            border: 2px solid var(--pos-gray-200);
            border-radius: var(--pos-radius-sm);
            background: white;
            font-size: 13px;
            font-weight: 500;
            box-shadow: var(--pos-shadow-sm);
        }

        .pos-select:focus {
            outline: none;
            border-color: var(--pos-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Cart Area */
        .pos-cart-area {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            color: var(--pos-gray-800);
            border-radius: var(--pos-radius-lg);
            display: grid;
            grid-template-rows: auto 1fr auto auto;
            box-shadow: var(--pos-shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .cart-header {
            background: linear-gradient(135deg, var(--pos-primary), var(--pos-info));
            color: white;
            padding: 16px 20px;
        }

        .cart-title {
            font-size: 18px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .cart-count {
            background: rgba(255, 255, 255, 0.9);
            color: var(--pos-primary);
            padding: 4px 10px;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 800;
            box-shadow: var(--pos-shadow);
        }

        .cart-items {
            overflow-y: auto;
            background: white;
            max-height: 360px;
        }

        .cart-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--pos-gray-400);
        }

        .cart-empty i {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--pos-gray-300);
        }

        .cart-empty h5 {
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--pos-gray-500);
            font-size: 16px;
        }

        .cart-empty p {
            color: var(--pos-gray-400);
            margin: 0;
            font-size: 13px;
        }

        .cart-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--pos-gray-100);
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 12px;
            align-items: center;
            transition: all 0.2s ease;
            animation: slideInRight 0.3s ease-out;
        }

        .cart-item:hover {
            background: rgba(79, 70, 229, 0.05);
        }

        .cart-item-info h6 {
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 3px 0;
            color: var(--pos-gray-800);
        }

        .cart-item-info small {
            color: var(--pos-gray-500);
            font-size: 12px;
            font-weight: 500;
        }

        .cart-item-qty {
            background: linear-gradient(45deg, var(--pos-primary), var(--pos-info));
            color: white;
            padding: 5px 10px;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 800;
            text-align: center;
            min-width: 36px;
            box-shadow: var(--pos-shadow-sm);
        }

        .cart-item-total {
            font-weight: 800;
            font-size: 15px;
            color: var(--pos-gray-800);
            font-family: 'SF Mono', monospace;
        }

        .remove-btn {
            background: linear-gradient(45deg, var(--pos-danger), #ff4757);
            color: white;
            border: none;
            padding: 6px 8px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s ease;
            box-shadow: var(--pos-shadow-sm);
        }

        .remove-btn:hover {
            transform: scale(1.1);
            box-shadow: var(--pos-shadow);
        }

        .cart-total {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 20px;
            text-align: center;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .cart-total-label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .cart-total-amount {
            font-size: 32px;
            font-weight: 900;
            font-family: 'SF Mono', 'Monaco', monospace;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .pos-checkout {
            background: var(--pos-gray-50);
            padding: 16px;
        }

        .checkout-title {
            color: var(--pos-gray-800);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .cash-input-group {
            display: flex;
            margin-bottom: 12px;
            box-shadow: var(--pos-shadow-md);
            border-radius: var(--pos-radius-sm);
            overflow: hidden;
        }

        .cash-prefix {
            background: linear-gradient(45deg, var(--pos-gray-600), var(--pos-gray-700));
            color: white;
            padding: 8px 12px;
            font-weight: 800;
            font-size: 16px;
        }

        .cash-input {
            flex: 1;
            padding: 8px 12px;
            border: none;
            font-size: 18px;
            font-weight: 800;
            text-align: center;
            font-family: 'SF Mono', monospace;
            background: white;
        }

        .cash-input:focus {
            outline: none;
            background: rgba(79, 70, 229, 0.05);
        }

        .quick-cash {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            margin-bottom: 12px;
        }

        .quick-cash-btn {
            background: rgba(79, 70, 229, 0.1);
            color: var(--pos-primary);
            border: 1px solid rgba(79, 70, 229, 0.2);
            padding: 8px 6px;
            border-radius: var(--pos-radius-sm);
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quick-cash-btn:hover {
            background: var(--pos-primary);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--pos-shadow);
        }

        .checkout-btn {
            background: linear-gradient(135deg, var(--pos-success), #22c55e);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: var(--pos-radius);
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            box-shadow: var(--pos-shadow-lg);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .checkout-btn:hover {
            background: linear-gradient(135deg, var(--pos-success-dark), var(--pos-success));
            transform: translateY(-2px);
            box-shadow: var(--pos-shadow-xl);
        }

        .cart-items::-webkit-scrollbar,
        .item-display::-webkit-scrollbar {
            width: 5px;
        }

        .cart-items::-webkit-scrollbar-track,
        .item-display::-webkit-scrollbar-track {
            background: var(--pos-gray-100);
        }

        .cart-items::-webkit-scrollbar-thumb,
        .item-display::-webkit-scrollbar-thumb {
            background: var(--pos-gray-300);
            border-radius: 3px;
        }

        .cart-items::-webkit-scrollbar-thumb:hover,
        .item-display::-webkit-scrollbar-thumb:hover {
            background: var(--pos-gray-400);
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .alert-pos {
            animation: slideInRight 0.5s ease-out;
        }

        .pos-btn-press:active {
            transform: scale(0.95);
        }

        @media (max-width: 1200px) {
            .pos-main {
                grid-template-columns: 1fr 350px;
                gap: 12px;
                padding: 12px;
            }

            .pos-input-area {
                grid-template-columns: 1fr 320px;
            }

            .camera-viewport {
                max-width: 350px;
                height: 220px;
            }
        }

        @media (max-width: 768px) {
            .pos-main {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
                height: auto;
                gap: 10px;
                padding: 10px;
            }

            .pos-input-area {
                grid-template-columns: 1fr;
            }

            .item-display {
                max-height: 200px;
            }

            .camera-viewport {
                height: 200px;
                max-width: 320px;
            }

            @keyframes scanLine {
                0% { transform: translateY(-3px); opacity: 0; }
                10% { opacity: 1; }
                90% { opacity: 1; }
                100% { transform: translateY(calc(80px - 3px)); opacity: 0; }
            }

            .pos-navbar {
                padding: 10px 12px;
            }

            .pos-navbar .brand {
                font-size: 16px;
            }

            .pos-navbar .nav-info {
                gap: 12px;
                font-size: 12px;
            }

            .manual-form {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }

        @media (max-width: 576px) {
            .camera-viewport {
                height: 180px;
                max-width: 300px;
            }

            .cart-total-amount {
                font-size: 28px;
            }

            .quick-cash {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="pos-navbar">
        <div class="brand">
            <i class="fas fa-cash-register"></i>
            POS TERMINAL
        </div>
        <div class="nav-info">
            <span><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Cashier') ?></span>
            <span class="nav-badge"><?= $cart_count ?> ITEMS</span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-power-off"></i> LOGOUT
            </a>
        </div>
    </nav>

    <!-- Main POS Grid -->
    <div class="pos-main">
        <!-- Alert Bar -->
        <div class="pos-alerts">
            <?php if (isset($success_message)): ?>
                <div class="alert-pos success" id="alert-message">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php elseif (isset($error_message)): ?>
                <div class="alert-pos danger" id="alert-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php elseif (isset($checkout_success)): ?>
                <div class="alert-pos success" id="alert-message">
                    <i class="fas fa-receipt"></i>
                    SALE COMPLETED! Change: ₱<?= number_format($change, 2) ?>
                    <button onclick="printReceipt()" style="background: var(--pos-primary); color: white; border: none; padding: 4px 12px; border-radius: 4px; margin-left: 10px; font-size: 11px; cursor: pointer;">
                        <i class="fas fa-print"></i> PRINT
                    </button>
                </div>
            <?php else: ?>
                <div style="color: #6b7280; font-size: 13px; text-align: center;">
                    <i class="fas fa-info-circle"></i>
                    Scan barcode or select items to start
                </div>
            <?php endif; ?>
        </div>

        <!-- Input Area -->
        <div class="pos-input-area">
            <div class="input-controls">
                <!-- Scanner Section -->
                <div class="pos-scanner">
                    <div class="pos-scanner-header">
                        <div class="pos-scanner-title">
                            <i class="fas fa-camera"></i> BARCODE SCANNER
                        </div>
                        <button id="scanner-toggle" class="scanner-toggle">
                            <i class="fas fa-play"></i> START
                        </button>
                    </div>
                    <div class="camera-viewport" id="camera-viewport">
                        <video id="camera-preview" autoplay playsinline muted></video>
                        <div class="camera-overlay"></div>
                        <div class="camera-permission-helper" id="permission-helper" style="display: none;">
                            <div class="permission-icon">
                                <i class="fas fa-camera"></i>
                            </div>
                            <div>Camera permission needed</div>
                            <small>Please allow camera access in your browser</small>
                        </div>
                    </div>
                    <div class="scanner-controls">
                        <div class="auto-add-control">
                            <input type="checkbox" id="auto-add" checked>
                            <label for="auto-add">AUTO ADD TO CART</label>
                        </div>
                        <div id="scanner-status" class="scanner-status inactive">INACTIVE</div>
                    </div>
                    <canvas id="barcode-canvas" style="display: none;"></canvas>
                </div>

                <!-- Barcode Input -->
                <div class="pos-barcode">
                    <div class="barcode-title">
                        <i class="fas fa-barcode"></i> MANUAL ENTRY
                    </div>
                    <form method="post">
                        <div class="barcode-input-group">
                            <input type="text" name="barcode" id="barcode-input" class="barcode-input" placeholder="Enter barcode..." autofocus>
                            <button type="submit" class="barcode-btn">
                                <i class="fas fa-search"></i> LOOKUP
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Manual Selection -->
                <div class="pos-manual">
                    <div class="manual-title">
                        <i class="fas fa-list"></i> ITEM SELECTION
                    </div>
                    <form method="post" class="manual-form">
                        <select name="item_id" class="pos-select" required>
                            <option value="">Select an item...</option>
                            <?php foreach ($items as $item): ?>
                                <?php $available = getAvailableStock($item['id'], $_SESSION['cart'], $item['stock']); ?>
                                <option value="<?= $item['id'] ?>" <?= $available <= 0 ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($item['name']) ?> - ₱<?= number_format($item['price'], 2) ?> 
                                    (<?= $available ?> left)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="quantity" value="1" min="1" class="quantity-input" required>
                        <button type="submit" name="add_to_cart" class="add-btn">
                            <i class="fas fa-plus"></i> ADD
                        </button>
                    </form>
                </div>
            </div>

            <!-- Item Display -->
            <div class="item-display">
                <?php if ($itemDetails): ?>
                    <?php $available_stock = getAvailableStock($itemDetails['id'], $_SESSION['cart'], $itemDetails['stock']); ?>
                    <div class="item-card">
                        <div class="item-card-header">
                            <?= htmlspecialchars($itemDetails['name']) ?>
                        </div>
                        <div class="item-details-grid">
                            <div class="item-detail">
                                <div class="item-detail-label">Price</div>
                                <div class="item-detail-value">₱<?= number_format($itemDetails['price'], 2) ?></div>
                            </div>
                            <div class="item-detail">
                                <div class="item-detail-label">Available</div>
                                <div class="item-detail-value"><?= $available_stock ?></div>
                            </div>
                            <div class="item-detail">
                                <div class="item-detail-label">Barcode</div>
                                <div class="item-detail-value"><?= htmlspecialchars($itemDetails['barcode']) ?></div>
                            </div>
                        </div>
                        <?php if ($available_stock > 0): ?>
                            <form method="post" class="add-to-cart-section">
                                <input type="hidden" name="item_id" value="<?= $itemDetails['id'] ?>">
                                <div>
                                    <div class="item-detail-label">QTY</div>
                                    <input type="number" name="quantity" value="1" min="1" max="<?= $available_stock ?>" class="quantity-input">
                                </div>
                                <button type="submit" name="add_to_cart" class="add-btn">
                                    <i class="fas fa-cart-plus"></i> ADD
                                </button>
                            </form>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--pos-danger); font-weight: 600; margin-top: 10px;">
                                <i class="fas fa-exclamation-circle"></i> OUT OF STOCK
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif (isset($_POST['barcode']) && !isset($_POST['auto_add'])): ?>
                    <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 10px; border-radius: 6px; text-align: center;">
                        <i class="fas fa-search"></i> Item not found: <?= htmlspecialchars($_POST['barcode']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cart Area -->
        <div class="pos-cart-area">
            <div class="cart-header">
                <div class="cart-title">
                    <i class="fas fa-shopping-cart"></i>
                    SHOPPING CART
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-count"><?= $cart_count ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="cart-items">
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="cart-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <h5>Cart Empty</h5>
                        <p>Scan or select items to begin</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($_SESSION['cart'] as $index => $cart_item): ?>
                        <div class="cart-item">
                            <div class="cart-item-info">
                                <h6><?= htmlspecialchars($cart_item['name']) ?></h6>
                                <small>₱<?= number_format($cart_item['price'], 2) ?> each</small>
                            </div>
                            <div class="cart-item-qty"><?= $cart_item['quantity'] ?></div>
                            <div class="cart-item-total">₱<?= number_format($cart_item['total'], 2) ?></div>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="remove_index" value="<?= $index ?>">
                                <button type="submit" name="remove_item" class="remove-btn" title="Remove item">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($_SESSION['cart'])): ?>
                <div class="cart-total">
                    <div class="cart-total-label">TOTAL AMOUNT</div>
                    <div class="cart-total-amount">₱<?= number_format($cart_total, 2) ?></div>
                </div>
                <div class="pos-checkout">
                    <div class="checkout-title">
                        <i class="fas fa-credit-card"></i> PAYMENT
                    </div>
                    <form method="post">
                        <div class="cash-input-group">
                            <div class="cash-prefix">₱</div>
                            <input type="number" name="cash_paid" id="cash-input" class="cash-input" step="0.01" min="<?= $cart_total ?>" placeholder="0.00" required>
                        </div>
                        <div class="quick-cash">
                            <?php
                            $amounts = [
                                ceil($cart_total),
                                ceil($cart_total / 100) * 100,
                                ceil($cart_total / 500) * 500,
                                ceil($cart_total / 1000) * 1000
                            ];
                            $amounts = array_unique($amounts);
                            sort($amounts);
                            foreach (array_slice($amounts, 0, 4) as $amount):
                                if ($amount >= $cart_total):
                            ?>
                                <button type="button" class="quick-cash-btn" onclick="setCash(<?= $amount ?>)">
                                    ₱<?= number_format($amount, 0) ?>
                                </button>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                        <button type="submit" name="checkout" class="checkout-btn">
                            <i class="fas fa-check-circle"></i> COMPLETE SALE
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <script>
        // FIXED POS CAMERA SCANNER SCRIPT
        let cameraActive = false;
        let lastScannedCode = '';
        let scanCooldown = false;
        let currentStream = null;

        // DOM Elements
        const scannerToggle = document.getElementById('scanner-toggle');
        const cameraPreview = document.getElementById('camera-preview');
        const scannerStatus = document.getElementById('scanner-status');
        const barcodeInput = document.getElementById('barcode-input');
        const autoAddCheckbox = document.getElementById('auto-add');
        const cashInput = document.getElementById('cash-input');
        const cameraViewport = document.querySelector('.camera-viewport');

        // Auto-hide alerts after 2 seconds
        function hideAlert() {
            const alert = document.getElementById('alert-message');
            if (alert) {
                setTimeout(() => {
                    alert.classList.add('hidden');
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500); // Match CSS transition duration
                }, 2000); // Show for 2 seconds
            }
        }

        // Scanner Toggle
        scannerToggle.addEventListener('click', function() {
            if (cameraActive) {
                stopScanner();
            } else {
                startScanner();
            }
        });

        // FIXED: Start Camera Scanner
        function startScanner() {
            console.log('Starting camera scanner...');
            
            const permissionHelper = document.getElementById('permission-helper');
            scannerStatus.textContent = 'REQUESTING CAMERA...';
            scannerStatus.classList.remove('inactive', 'active', 'error', 'scanning');
            scannerStatus.classList.add('active');
            cameraViewport.classList.add('active');
            
            permissionHelper.style.display = 'block';
            permissionHelper.innerHTML = `
                <div class="permission-icon"><i class="fas fa-camera"></i></div>
                <div>Starting Camera...</div>
                <small>Please allow camera access when prompted</small>
            `;

            // FIXED: Better camera constraints
            navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: { ideal: 1280, min: 640 },
                    height: { ideal: 720, min: 480 },
                    facingMode: "environment"
                } 
            })
            .then(stream => {
                console.log('Camera stream obtained');
                cameraPreview.srcObject = stream;
                currentStream = stream; // Store stream reference
                
                // CRITICAL FIX: Wait for video to load before initializing scanner
                cameraPreview.addEventListener('loadedmetadata', function() {
                    console.log('Video metadata loaded');
                    cameraActive = true;
                    permissionHelper.style.display = 'none';
                    scannerToggle.innerHTML = '<i class="fas fa-stop"></i> STOP';
                    scannerToggle.classList.add('active');
                    scannerStatus.textContent = 'INITIALIZING SCANNER...';
                    
                    // Initialize Quagga after video is ready
                    Quagga.init({
                        inputStream: {
                            name: "Live",
                            type: "LiveStream",
                            target: cameraPreview
                        },
                        locator: {
                            patchSize: "medium",
                            halfSample: true
                        },
                        numOfWorkers: 2,
                        decoder: {
                            readers: [
                                "code_128_reader",
                                "ean_reader",
                                "ean_8_reader",
                                "code_39_reader",
                                "upc_reader"
                            ]
                        },
                        locate: true,
                        frequency: 10
                    }, function(err) {
                        if (err) {
                            console.error('Scanner init failed:', err);
                            scannerStatus.textContent = 'SCANNER ERROR';
                            scannerStatus.classList.add('error');
                            return;
                        }
                        
                        console.log('Scanner ready');
                        Quagga.start();
                        scannerStatus.textContent = 'SCANNING...';
                        scannerStatus.classList.add('scanning');
                        
                        Quagga.onDetected(function(result) {
                            const code = result.codeResult.code;
                            
                            if (code === lastScannedCode && scanCooldown) return;
                            
                            lastScannedCode = code;
                            scanCooldown = true;
                            
                            console.log('Barcode detected:', code);
                            
                            cameraViewport.style.animation = 'flashBorder 0.5s ease';
                            setTimeout(() => cameraViewport.style.animation = '', 500);
                            
                            playBeep();
                            
                            scannerStatus.textContent = 'SCAN SUCCESS: ' + code;
                            
                            if (autoAddCheckbox.checked) {
                                submitBarcode(code, true);
                            } else {
                                barcodeInput.value = code;
                                submitBarcode(code, false);
                            }
                            
                            setTimeout(() => {
                                scanCooldown = false;
                                if (cameraActive) {
                                    scannerStatus.textContent = 'SCANNING...';
                                }
                            }, 2000);
                        });
                    });
                }, { once: true });
            })
            .catch(err => {
                console.error('Camera error:', err);
                scannerStatus.textContent = 'CAMERA ERROR';
                scannerStatus.classList.add('error');
                cameraViewport.classList.remove('active');
                
                let errorMsg = 'Camera access failed';
                if (err.name === 'NotAllowedError') {
                    errorMsg = 'Camera permission denied. Please allow camera access.';
                } else if (err.name === 'NotFoundError') {
                    errorMsg = 'No camera found. Please connect a camera.';
                } else if (err.name === 'SecurityError') {
                    errorMsg = 'Camera requires HTTPS or localhost.';
                }
                
                permissionHelper.innerHTML = `
                    <div class="permission-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>Camera Error</div>
                    <small>${errorMsg}</small>
                `;
                
                alert(errorMsg);
            });
        }

        // FIXED: Proper cleanup in stop function
        function stopScanner() {
            console.log('Stopping scanner');
            
            if (typeof Quagga !== 'undefined') {
                Quagga.stop();
                Quagga.offDetected();
            }
            
            if (currentStream) {
                currentStream.getTracks().forEach(track => track.stop());
                currentStream = null;
            }
            
            if (cameraPreview) {
                cameraPreview.srcObject = null;
            }
            
            cameraActive = false;
            scannerToggle.innerHTML = '<i class="fas fa-play"></i> START';
            scannerToggle.classList.remove('active');
            scannerStatus.textContent = 'INACTIVE';
            scannerStatus.classList.remove('active', 'error', 'scanning');
            scannerStatus.classList.add('inactive');
            cameraViewport.classList.remove('active');
            document.getElementById('permission-helper').style.display = 'none';
        }

        // Submit Barcode
        function submitBarcode(code, autoAdd = false) {
            const form = document.createElement('form');
            form.method = 'post';
            form.style.display = 'none';
            
            const barcodeField = document.createElement('input');
            barcodeField.type = 'hidden';
            barcodeField.name = autoAdd ? 'camera_barcode' : 'barcode';
            barcodeField.value = code;
            
            form.appendChild(barcodeField);
            
            if (autoAdd) {
                const autoAddField = document.createElement('input');
                autoAddField.type = 'hidden';
                autoAddField.name = 'auto_add';
                autoAddField.value = '1';
                form.appendChild(autoAddField);
            }
            
            document.body.appendChild(form);
            form.submit();
        }

        // Play Beep Sound
        function playBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                
                osc.connect(gain);
                gain.connect(ctx.destination);
                
                osc.frequency.value = 800;
                osc.type = 'square';
                
                gain.gain.setValueAtTime(0.1, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);
                
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.2);
            } catch (e) {
                console.log('Audio not available');
            }
        }

        // Set Cash Amount
        function setCash(amount) {
            if (cashInput) {
                cashInput.value = amount;
                cashInput.focus();
            }
        }

        // Auto-focus barcode input
        function focusBarcodeInput() {
            if (!cameraActive && barcodeInput) {
                barcodeInput.focus();
            }
        }

        // Print Receipt Directly
        function printReceipt() {
            const printWindow = window.open('print_receipt_pos.php', '_blank');
            printWindow.onload = function() {
                printWindow.print();
                printWindow.onafterprint = function() {
                    printWindow.close();
                };
            };
        }

        // Initialize everything when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('POS System initializing...');
            
            // Auto-hide alerts
            hideAlert();
            
            // Set up event listeners
            if (barcodeInput) {
                barcodeInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitBarcode(this.value, false);
                    }
                });
            }
            
            // Update quantity max when item selected
            const itemSelect = document.querySelector('select[name="item_id"]');
            const quantityInput = document.querySelector('.pos-manual input[name="quantity"]');
            
            if (itemSelect && quantityInput) {
                itemSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        const text = selectedOption.textContent;
                        const match = text.match(/\((\d+) left\)/);
                        if (match) {
                            const available = parseInt(match[1]);
                            quantityInput.max = available;
                            if (parseInt(quantityInput.value) > available) {
                                quantityInput.value = Math.max(1, available);
                            }
                        }
                    }
                });
            }
            
            // Cash input validation
            if (cashInput) {
                cashInput.addEventListener('input', function() {
                    const amount = parseFloat(this.value) || 0;
                    const total = <?= $cart_total ?>;
                    
                    if (amount >= total) {
                        this.style.borderColor = 'var(--pos-success)';
                        this.style.background = '#f0fdf4';
                    } else {
                        this.style.borderColor = 'var(--pos-danger)';
                        this.style.background = '#fef2f2';
                    }
                });
                
                cashInput.addEventListener('focus', function() {
                    this.select();
                });
            }
            
            // Auto-focus barcode input
            focusBarcodeInput();
            
            <?php if (isset($checkout_success)): ?>
                setTimeout(printReceipt, 300);
            <?php endif; ?>
            
            console.log('POS System ready');
        });

        // Keyboard Shortcuts
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'F1':
                    e.preventDefault();
                    if (cameraActive) stopScanner(); else startScanner();
                    break;
                case 'F2':
                    e.preventDefault();
                    focusBarcodeInput();
                    break;
                case 'Escape':
                    break;
            }
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (cameraActive) {
                stopScanner();
            }
        });
    </script>
</body>
</html>