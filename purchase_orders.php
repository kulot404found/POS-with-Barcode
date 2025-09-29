<?php
session_start();
require_once 'config.php';

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("❌ Access denied.");
}

// Add Purchase Order with Items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supplier_id']) && !isset($_POST['edit_id'])) {
    $supplier_id = (int)$_POST['supplier_id'];
    $expected_delivery = $_POST['expected_delivery'] ?? null;
    $status = $_POST['status'] ?? 'Pending';
    $notes = $_POST['notes'] ?? '';
    $priority = $_POST['priority'] ?? 'Medium';
    
    try {
        $db->beginTransaction();
        
        // Insert purchase order
        $stmt = $db->prepare("INSERT INTO purchase_orders (supplier_id, expected_delivery_date, status, notes, priority, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$supplier_id, $expected_delivery, $status, $notes, $priority, $_SESSION['user_id']]);
        $order_id = $db->lastInsertId();
        
        // Insert order items if provided
        if (!empty($_POST['items'])) {
            $total_amount = 0;
            foreach ($_POST['items'] as $item) {
                if (isset($item['item_id']) && is_numeric($item['item_id']) &&
                    isset($item['quantity']) && is_numeric($item['quantity']) && $item['quantity'] > 0 &&
                    isset($item['unit_price']) && is_numeric($item['unit_price']) && $item['unit_price'] >= 0) {
                    
                    // Verify inventory item exists
                    $itemCheckStmt = $db->prepare("SELECT id, name FROM inventory WHERE id = ?");
                    $itemCheckStmt->execute([$item['item_id']]);
                    $inventoryItem = $itemCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$inventoryItem) {
                        throw new Exception("Inventory item with ID {$item['item_id']} does not exist");
                    }
                    
                    $item_name = $inventoryItem['name'];
                    $unit_cost = $item['unit_price']; // Use unit_price as unit_cost
                    $item_total = $item['quantity'] * $item['unit_price'];
                    $total_amount += $item_total;
                    
                    // Fixed: Insert into item_id column instead of inventory_id
                    $itemInsertStmt = $db->prepare("INSERT INTO purchase_order_items (po_id, item_id, item_name, quantity, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?)");
                    $itemInsertStmt->execute([$order_id, $item['item_id'], $item_name, $item['quantity'], $unit_cost, $item_total]);
                }
            }
            
            // Update total amount in purchase_orders
            $updateStmt = $db->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?");
            $updateStmt->execute([$total_amount, $order_id]);
        }
        
        $db->commit();
        $success = "Purchase order created successfully!";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error creating purchase order: " . $e->getMessage();
    }
}

// Edit Purchase Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $supplier_id = (int)$_POST['supplier_id'];
    $expected_delivery = $_POST['expected_delivery'] ?? null;
    $status = $_POST['status'] ?? 'Pending';
    $notes = $_POST['notes'] ?? '';
    $priority = $_POST['priority'] ?? 'Medium';
    
    $stmt = $db->prepare("UPDATE purchase_orders SET supplier_id=?, expected_delivery_date=?, status=?, notes=?, priority=? WHERE id=?");
    $stmt->execute([$supplier_id, $expected_delivery, $status, $notes, $priority, $id]);
    $success = "Purchase order updated successfully!";
}

// Delete Purchase Order
if (isset($_GET['delete'])) {
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("DELETE FROM purchase_order_items WHERE po_id=?");
        $stmt->execute([$_GET['delete']]);
        
        $stmt = $db->prepare("DELETE FROM purchase_orders WHERE id=?");
        $stmt->execute([$_GET['delete']]);
        
        $db->commit();
        $success = "Purchase order deleted successfully!";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error deleting purchase order: " . $e->getMessage();
    }
}

// Fetch suppliers and items for dropdowns
$suppliers = $db->query("SELECT * FROM suppliers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$items = $db->query("SELECT id, name, price, stock FROM inventory ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Pagination and filtering
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$supplier_filter = $_GET['supplier'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($supplier_filter)) {
    $where_conditions[] = "po.supplier_id = ?";
    $params[] = $supplier_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "po.status = ?";
    $params[] = $status_filter;
}

if (!empty($priority_filter)) {
    $where_conditions[] = "po.priority = ?";
    $params[] = $priority_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(po.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(po.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(s.name LIKE ? OR po.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Count total orders
$countQuery = "SELECT COUNT(*) FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id $where_clause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Fetch orders with supplier names and enhanced info
$query = "SELECT po.*, s.name AS supplier_name, s.email AS supplier_email, s.phone AS supplier_phone,
                 u.username AS created_by_name
          FROM purchase_orders po
          JOIN suppliers s ON po.supplier_id = s.id
          LEFT JOIN users u ON po.created_by = u.id
          $where_clause
          ORDER BY po.created_at DESC
          LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch items for each order - Fixed query to use item_id
$orderItems = [];
$orderStats = [];
foreach ($orders as $key => $order) {
    // Fixed: Use item_id instead of inventory_id in WHERE clause
    $stmt = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?");
    $stmt->execute([$order['id']]);
    $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $orderItems[$order['id']] = $items_data;

    // Calculate totals and statistics
    $total_amount = 0;
    $total_items = 0;
    $total_quantity = 0;
    foreach ($items_data as $item) {
        $total_amount += $item['total_cost'];
        $total_items += 1;
        $total_quantity += $item['quantity'];
    }
    
    $orders[$key]['calculated_total'] = $total_amount;
    $orderStats[$order['id']] = [
        'total_items' => $total_items,
        'total_quantity' => $total_quantity,
        'total_value' => $total_amount
    ];

    // Update database total if different
    if (abs($order['total_amount'] - $total_amount) > 0.01) {
        $updateStmt = $db->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?");
        $updateStmt->execute([$total_amount, $order['id']]);
        $orders[$key]['total_amount'] = $total_amount;
    }
}

// Get dashboard statistics
$dashboardStats = [];
$dashboardStats['total_orders'] = $db->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn();
$dashboardStats['pending_orders'] = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'Pending'")->fetchColumn();
$dashboardStats['completed_orders'] = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'Completed'")->fetchColumn();
$dashboardStats['total_value'] = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders")->fetchColumn();
$dashboardStats['avg_order_value'] = $dashboardStats['total_orders'] > 0 ? $dashboardStats['total_value'] / $dashboardStats['total_orders'] : 0;
$dashboardStats['overdue_orders'] = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE expected_delivery_date < NOW() AND status != 'Completed'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Enhanced Purchase Orders Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
            transition: width 0.3s;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
            min-height: 100vh;
        }
        .container {
            max-width: 100%; /* Ensure it doesn't exceed screen width */
            padding: 0 15px; /* Add padding for smaller screens */
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.2s;
            width: 100%; /* Ensure cards don't stretch */
            box-sizing: border-box;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            background: linear-gradient(135deg, #4e54c8 0%, #667eea 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }
        .dashboard-card {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: transform 0.2s;
            width: 100%; /* Ensure dashboard cards don't stretch */
            box-sizing: border-box;
        }
        .dashboard-card:hover {
            transform: scale(1.05);
        }
        .dashboard-card h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }
        .priority-high { border-left: 4px solid #dc3545; }
        .priority-medium { border-left: 4px solid #ffc107; }
        .priority-low { border-left: 4px solid #28a745; }
        .status-pending { background-color: #fff3cd; }
        .status-completed { background-color: #d1e7dd; }
        .status-cancelled { background-color: #f8d7da; }
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .order-timeline {
            border-left: 3px solid #dee2e6;
            padding-left: 20px;
            margin-left: 10px;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 15px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4e54c8;
        }
        .item-row {
            background: #f8f9fa;
            margin: 5px 0;
            padding: 8px;
            border-radius: 5px;
            border-left: 3px solid #4e54c8;
        }
        @media (max-width: 1200px) {
            .sidebar {
                width: 0; /* Hide sidebar on small screens */
                min-width: 0;
                overflow-x: hidden;
            }
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
            .card {
                width: 100% !important; /* Ensure cards don't stretch */
            }
        }
        @media (max-width: 768px) {
            .card {
                margin-bottom: 15px;
            }
            .row > [class^="col-"] {
                flex: 0 0 100%; /* Full width on small screens */
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="main-content col">
                <div class="container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-cart4"></i> Purchase Orders Management</h2>
                        <div>
                            <button class="btn btn-gradient me-2" onclick="exportOrders()">
                                <i class="bi bi-download"></i> Export
                            </button>
                            <a href="suppliers.php" class="btn btn-outline-primary me-2">
                                <i class="bi bi-building"></i> Manage Suppliers
                            </a>
                            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addOrderModal">
                                <i class="bi bi-plus-circle"></i> Create Order
                            </button>
                        </div>
                    </div>
                    
                    <!-- Alerts -->
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i> <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Dashboard Statistics -->
                    <div class="row mb-4">
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="dashboard-card">
                                <h3><?= $dashboardStats['total_orders'] ?></h3>
                                <p class="mb-0">Total Orders</p>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="dashboard-card">
                                <h3 class="text-warning"><?= $dashboardStats['pending_orders'] ?></h3>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="dashboard-card">
                                <h3 class="text-success"><?= $dashboardStats['completed_orders'] ?></h3>
                                <p class="mb-0">Completed</p>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="dashboard-card">
                                <h3 class="text-danger"><?= $dashboardStats['overdue_orders'] ?></h3>
                                <p class="mb-0">Overdue</p>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="dashboard-card">
                                <h3>₱<?= number_format($dashboardStats['total_value'], 0) ?></h3>
                                <p class="mb-0">Total Value</p>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="dashboard-card">
                                <h3>₱<?= number_format($dashboardStats['avg_order_value'], 0) ?></h3>
                                <p class="mb-0">Avg Order</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-lg-2 col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <select name="supplier" class="form-control">
                                    <option value="">All Suppliers</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['id'] ?>" <?= $supplier_filter == $supplier['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($supplier['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Completed" <?= $status_filter == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Cancelled" <?= $status_filter == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <select name="priority" class="form-control">
                                    <option value="">All Priorities</option>
                                    <option value="High" <?= $priority_filter == 'High' ? 'selected' : '' ?>>High</option>
                                    <option value="Medium" <?= $priority_filter == 'Medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="Low" <?= $priority_filter == 'Low' ? 'selected' : '' ?>>Low</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <button type="submit" class="btn btn-gradient w-100">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Orders List -->
                    <?php if (empty($orders)): ?>
                        <div class="alert alert-info text-center" role="alert">
                            <i class="bi bi-info-circle"></i> No purchase orders found. Create your first order!
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($orders as $order): 
                                $stats = $orderStats[$order['id']];
                                $isOverdue = $order['expected_delivery_date'] && strtotime($order['expected_delivery_date']) < time() && $order['status'] != 'Completed';
                            ?>
                                <div class="col-xl-6 col-lg-12 mb-4">
                                    <div class="card priority-<?= strtolower($order['priority']) ?> <?= $isOverdue ? 'border-danger' : '' ?>">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="mb-0">Order #<?= $order['id'] ?></h5>
                                                <small><?= htmlspecialchars($order['supplier_name']) ?></small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?= strtolower($order['priority']) == 'high' ? 'danger' : (strtolower($order['priority']) == 'medium' ? 'warning' : 'success') ?>">
                                                    <?= htmlspecialchars($order['priority']) ?>
                                                </span>
                                                <?php if ($isOverdue): ?>
                                                    <span class="badge bg-danger">Overdue</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-body status-<?= strtolower($order['status']) ?>">
                                            <!-- Order Summary -->
                                            <div class="row mb-3">
                                                <div class="col-4 text-center">
                                                    <strong><?= $stats['total_items'] ?></strong>
                                                    <small class="d-block text-muted">Items</small>
                                                </div>
                                                <div class="col-4 text-center">
                                                    <strong><?= $stats['total_quantity'] ?></strong>
                                                    <small class="d-block text-muted">Quantity</small>
                                                </div>
                                                <div class="col-4 text-center">
                                                    <strong>₱<?= number_format($order['total_amount'], 2) ?></strong>
                                                    <small class="d-block text-muted">Total</small>
                                                </div>
                                            </div>
                                            
                                            <!-- Order Details -->
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar"></i> Created: <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                                    <?php if ($order['expected_delivery_date']): ?>
                                                        | <i class="bi bi-truck"></i> Expected: <?= date('M j, Y', strtotime($order['expected_delivery_date'])) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            
                                            <!-- Status -->
                                            <div class="mb-3">
                                                <span class="badge bg-<?= strtolower($order['status']) == 'pending' ? 'warning text-dark' : (strtolower($order['status']) == 'completed' ? 'success' : 'danger') ?> me-2">
                                                    <?= htmlspecialchars($order['status']) ?>
                                                </span>
                                                <?php if ($order['created_by_name']): ?>
                                                    <small class="text-muted">by <?= htmlspecialchars($order['created_by_name']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Notes -->
                                            <?php if ($order['notes']): ?>
                                                <div class="mb-3">
                                                    <small><strong>Notes:</strong> <?= htmlspecialchars($order['notes']) ?></small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Order Items (collapsible) -->
                                            <div class="accordion" id="accordion-<?= $order['id'] ?>">
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#items-<?= $order['id'] ?>">
                                                            <i class="bi bi-list-ul"></i>&nbsp; View Items (<?= $stats['total_items'] ?>)
                                                        </button>
                                                    </h2>
                                                    <div id="items-<?= $order['id'] ?>" class="accordion-collapse collapse">
                                                        <div class="accordion-body">
                                                            <?php if (empty($orderItems[$order['id']])): ?>
                                                                <p class="text-muted">No items in this order.</p>
                                                            <?php else: ?>
                                                                <?php foreach ($orderItems[$order['id']] as $item): ?>
                                                                    <div class="item-row">
                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                            <div>
                                                                                <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                                                                <small class="d-block text-muted">
                                                                                    <?= $item['quantity'] ?> <?= htmlspecialchars('pcs') ?> 
                                                                                    @ ₱<?= number_format($item['unit_cost'], 2) ?>
                                                                                </small>
                                                                            </div>
                                                                            <strong>₱<?= number_format($item['total_cost'], 2) ?></strong>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card-footer bg-transparent">
                                            <div class="btn-group w-100" role="group">
                                                <a href="tel:<?= $order['supplier_phone'] ?>" class="btn btn-outline-primary btn-sm" 
                                                   <?= !$order['supplier_phone'] ? 'style="display:none"' : '' ?>>
                                                    <i class="bi bi-telephone"></i>
                                                </a>
                                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewOrderModal-<?= $order['id'] ?>">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editOrderModal-<?= $order['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <a href="purchase_orders.php?delete=<?= $order['id'] ?>" class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Delete this order and all its items?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Orders pagination">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">Previous</a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Order Modal -->
    <div class="modal fade" id="addOrderModal" tabindex="-1" aria-labelledby="addOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-gradient text-white">
                    <h5 class="modal-title" id="addOrderModalLabel">Create New Purchase Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="addOrderForm">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Supplier *</label>
                                <select name="supplier_id" class="form-control" required>
                                    <option value="">Select supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['id'] ?>" data-email="<?= htmlspecialchars($supplier['email'] ?? '') ?>" data-phone="<?= htmlspecialchars($supplier['phone'] ?? '') ?>">
                                            <?= htmlspecialchars($supplier['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-control">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expected Delivery</label>
                                <input type="date" name="expected_delivery" class="form-control" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="Pending" selected>Pending</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Order notes or special instructions..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Order Items Section -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Order Items</h6>
                            </div>
                            <div class="card-body">
                                <div id="orderItems">
                                    <div class="order-item-row row g-3 mb-3">
                                        <div class="col-md-4">
                                            <select name="items[0][item_id]" class="form-control item-select" required>
                                                <option value="">Select item</option>
                                                <?php foreach ($items as $item): ?>
                                                    <option value="<?= $item['id'] ?>" data-price="<?= $item['price'] ?>" data-stock="<?= $item['stock'] ?>">
                                                        <?= htmlspecialchars($item['name']) ?> (Stock: <?= $item['stock'] ?>, ₱<?= number_format($item['price'], 2) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" name="items[0][quantity]" class="form-control quantity-input" placeholder="Qty" min="1" step="1" required>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" name="items[0][unit_price]" class="form-control unit-price-input" placeholder="Unit Price" min="0" step="0.01" readonly>
                                        </div>
                                        <div class="col-md-2">
                                            <span class="form-control-plaintext item-total">₱0.00</span>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger btn-sm remove-item" style="display: none;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="addItem">
                                    <i class="bi bi-plus"></i> Add Item
                                </button>
                                <div class="text-end mt-3">
                                    <h5>Total: <span id="orderTotal">₱0.00</span></h5>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-gradient">
                                <i class="bi bi-check-circle"></i> Create Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Order Modals -->
    <?php foreach ($orders as $order): ?>
        <div class="modal fade" id="editOrderModal-<?= $order['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-gradient text-white">
                        <h5 class="modal-title">Edit Order #<?= $order['id'] ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="edit_id" value="<?= $order['id'] ?>">
                            <div class="col-md-6">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" class="form-control" required>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['id'] ?>" <?= $supplier['id'] == $order['supplier_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($supplier['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-control">
                                    <option value="Low" <?= $order['priority'] == 'Low' ? 'selected' : '' ?>>Low</option>
                                    <option value="Medium" <?= $order['priority'] == 'Medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="High" <?= $order['priority'] == 'High' ? 'selected' : '' ?>>High</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expected Delivery</label>
                                <input type="date" name="expected_delivery" class="form-control" 
                                       value="<?= $order['expected_delivery_date'] ? date('Y-m-d', strtotime($order['expected_delivery_date'])) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="Pending" <?= $order['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Completed" <?= $order['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Cancelled" <?= $order['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-gradient w-100">
                                    <i class="bi bi-check-circle"></i> Update Order
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- View Order Details Modals -->
    <?php foreach ($orders as $order): ?>
        <div class="modal fade" id="viewOrderModal-<?= $order['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-gradient text-white">
                        <h5 class="modal-title">Order #<?= $order['id'] ?> Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Order Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Supplier:</strong></td><td><?= htmlspecialchars($order['supplier_name']) ?></td></tr>
                                    <tr><td><strong>Status:</strong></td><td><span class="badge bg-<?= strtolower($order['status']) == 'pending' ? 'warning text-dark' : (strtolower($order['status']) == 'completed' ? 'success' : 'danger') ?>"><?= htmlspecialchars($order['status']) ?></span></td></tr>
                                    <tr><td><strong>Priority:</strong></td><td><span class="badge bg-<?= strtolower($order['priority']) == 'high' ? 'danger' : (strtolower($order['priority']) == 'medium' ? 'warning' : 'success') ?>"><?= htmlspecialchars($order['priority']) ?></span></td></tr>
                                    <tr><td><strong>Created:</strong></td><td><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></td></tr>
                                    <?php if ($order['expected_delivery_date']): ?>
                                    <tr><td><strong>Expected Delivery:</strong></td><td><?= date('M j, Y', strtotime($order['expected_delivery_date'])) ?></td></tr>
                                    <?php endif; ?>
                                    <tr><td><strong>Total Amount:</strong></td><td><strong>₱<?= number_format($order['total_amount'], 2) ?></strong></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Supplier Contact</h6>
                                <table class="table table-sm">
                                    <?php if ($order['supplier_email']): ?>
                                    <tr><td><strong>Email:</strong></td><td><a href="mailto:<?= $order['supplier_email'] ?>"><?= htmlspecialchars($order['supplier_email']) ?></a></td></tr>
                                    <?php endif; ?>
                                    <?php if ($order['supplier_phone']): ?>
                                    <tr><td><strong>Phone:</strong></td><td><a href="tel:<?= $order['supplier_phone'] ?>"><?= htmlspecialchars($order['supplier_phone']) ?></a></td></tr>
                                    <?php endif; ?>
                                    <?php if ($order['notes']): ?>
                                    <tr><td><strong>Notes:</strong></td><td><?= htmlspecialchars($order['notes']) ?></td></tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        
                        <h6 class="mt-4">Order Items</h6>
                        <?php if (empty($orderItems[$order['id']])): ?>
                            <p class="text-muted">No items in this order.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Total Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orderItems[$order['id']] as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                <td><?= $item['quantity'] ?> pcs</td>
                                                <td>₱<?= number_format($item['unit_cost'], 2) ?></td>
                                                <td>₱<?= number_format($item['total_cost'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-info">
                                            <th colspan="3">Total</th>
                                            <th>₱<?= number_format($order['total_amount'], 2) ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-gradient" onclick="printOrder(<?= $order['id'] ?>)">
                            <i class="bi bi-printer"></i> Print Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let itemIndex = 1;

        // Add new item row
        document.getElementById('addItem').addEventListener('click', function() {
            const orderItems = document.getElementById('orderItems');
            const newRow = document.querySelector('.order-item-row').cloneNode(true);
            
            // Update input names and clear values
            newRow.querySelectorAll('input, select').forEach(input => {
                if (input.name) {
                    input.name = input.name.replace('[0]', '[' + itemIndex + ']');
                }
                if (input.tagName === 'SELECT') {
                    input.value = '';
                } else {
                    input.value = '';
                }
            });
            
            // Show remove button
            newRow.querySelector('.remove-item').style.display = 'block';
            
            // Clear item total
            newRow.querySelector('.item-total').textContent = '₱0.00';
            
            orderItems.appendChild(newRow);
            itemIndex++;
            
            // Add event listeners to new row
            addRowEventListeners(newRow);
        });

        // Remove item row
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item')) {
                e.target.closest('.order-item-row').remove();
                calculateOrderTotal();
            }
        });

        // Add event listeners to a row
        function addRowEventListeners(row) {
            const select = row.querySelector('.item-select');
            const qtyInput = row.querySelector('.quantity-input');
            const priceInput = row.querySelector('.unit-price-input');
            
            select.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                priceInput.value = selectedOption ? selectedOption.getAttribute('data-price') : '';
                calculateRowTotal(row);
                calculateOrderTotal();
            });

            [qtyInput, priceInput].forEach(input => {
                input.addEventListener('input', function() {
                    calculateRowTotal(row);
                    calculateOrderTotal();
                });
            });
        }

        // Calculate row total
        function calculateRowTotal(row) {
            const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
            const price = parseFloat(row.querySelector('.unit-price-input').value) || 0;
            const total = qty * price;
            
            row.querySelector('.item-total').textContent = '₱' + total.toFixed(2);
        }

        // Calculate order total
        function calculateOrderTotal() {
            let total = 0;
            document.querySelectorAll('.order-item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(row.querySelector('.unit-price-input').value) || 0;
                total += qty * price;
            });
            
            document.getElementById('orderTotal').textContent = '₱' + total.toFixed(2);
        }

        // Initialize event listeners for the first row
        document.addEventListener('DOMContentLoaded', function() {
            addRowEventListeners(document.querySelector('.order-item-row'));
            
            // Auto-hide alerts
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.classList.contains('alert-success')) {
                        alert.style.transition = 'opacity 0.5s';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 500);
                    }
                });
            }, 3000);
        });

        // Export orders
        function exportOrders() {
            const orders = <?= json_encode($orders) ?>;
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Order ID,Supplier,Status,Priority,Created Date,Expected Delivery,Total Amount,Notes\n";
            
            orders.forEach(order => {
                const row = [
                    order.id,
                    `"${order.supplier_name}"`,
                    order.status,
                    order.priority,
                    order.created_at,
                    order.expected_delivery_date || '',
                    order.total_amount,
                    `"${order.notes || ''}"`
                ].join(",");
                csvContent += row + "\n";
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "purchase_orders_export.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Print orders
        function printOrder(orderId) {
            window.open(`print_order.php?id=${orderId}`, '_blank');
        }

        // Real-time search
        let searchTimeout;
        function performSearch() {
            const searchTerm = document.querySelector('input[name="search"]').value;
            if (searchTerm.length > 2 || searchTerm.length === 0) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.querySelector('form').submit();
                }, 500);
            }
        }
        
        if (document.querySelector('input[name="search"]')) {
            document.querySelector('input[name="search"]').addEventListener('input', performSearch);
        }
    </script>
</body>
</html>