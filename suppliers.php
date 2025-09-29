<?php
session_start();
require_once 'config.php';

// Only allow admin access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    die("❌ Access denied.");
}

// Add supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name']) && !isset($_POST['edit_id'])) {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $payment_terms = trim($_POST['payment_terms'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);
    
    if (empty($name)) {
        $error = "Supplier name is required.";
    } else {
        $stmt = $db->prepare("INSERT INTO suppliers (name, contact, email, address, phone, payment_terms, rating) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $contact, $email, $address, $phone, $payment_terms, $rating]);
        $success = "Supplier added successfully!";
    }
}

// Edit supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $payment_terms = trim($_POST['payment_terms'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);
    
    if (empty($name)) {
        $error = "Supplier name is required.";
    } else {
        $stmt = $db->prepare("UPDATE suppliers SET name = ?, contact = ?, email = ?, address = ?, phone = ?, payment_terms = ?, rating = ? WHERE id = ?");
        $stmt->execute([$name, $contact, $email, $address, $phone, $payment_terms, $rating, $id]);
        $success = "Supplier updated successfully!";
    }
}

// Delete supplier (with safety check)
if (isset($_GET['delete'])) {
    $supplier_id = (int)$_GET['delete'];
    
    // Check if supplier has pending orders
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ? AND status = 'Pending'");
    $checkStmt->execute([$supplier_id]);
    $pendingOrders = $checkStmt->fetchColumn();
    
    if ($pendingOrders > 0) {
        $error = "Cannot delete supplier with pending orders. Please complete or cancel pending orders first.";
    } else {
        $stmt = $db->prepare("DELETE FROM suppliers WHERE id=?");
        $stmt->execute([$supplier_id]);
        $success = "Supplier deleted successfully!";
    }
}

// Pagination and filtering
try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Search and filter functionality
    $search = $_GET['search'] ?? '';
    $rating_filter = $_GET['rating'] ?? '';
    $sort_by = $_GET['sort'] ?? 'name';
    $sort_order = $_GET['order'] ?? 'ASC';
    
    // Build query with filters
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(contact LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($rating_filter)) {
        $where_conditions[] = "rating >= ? AND rating <= ?";
        $params[] = $rating_filter;
        $params[] = $rating_filter; // This ensures only the selected rating and above are included, but we'll adjust logic below
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Count total suppliers with filters
    $countQuery = "SELECT COUNT(*) FROM suppliers $where_clause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalSuppliers = $countStmt->fetchColumn();
    $totalPages = ceil($totalSuppliers / $limit);
    
    // Fetch suppliers with filters and sorting
    $query = "SELECT * FROM suppliers $where_clause ORDER BY $sort_by $sort_order LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch detailed statistics for each supplier
    $supplierStats = [];
    foreach ($suppliers as $s) {
        $stats = [];
        
        // Order statistics
        $orderStmt = $db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_value,
                COUNT(CASE WHEN UPPER(status) = 'PENDING' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN UPPER(status) = 'COMPLETED' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN UPPER(status) = 'CANCELLED' THEN 1 END) as canceled_orders,
                MAX(created_at) as last_order_date
            FROM purchase_orders 
            WHERE supplier_id = ?
        ");
        $orderStmt->execute([$s['id']]);
        $orderStats = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (!$orderStats) {
            $orderStats = [
                'total_orders' => 0,
                'total_value' => 0,
                'pending_orders' => 0,
                'completed_orders' => 0,
                'canceled_orders' => 0,
                'last_order_date' => null
            ];
        }
        
        // Recent orders
        $recentOrdersStmt = $db->prepare("
            SELECT id, created_at, status, total_amount 
            FROM purchase_orders 
            WHERE supplier_id = ? 
            ORDER BY created_at DESC 
            LIMIT 3
        ");
        $recentOrdersStmt->execute([$s['id']]);
        $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Delivery performance (using purchase_orders with fallback)
        $deliveryStmt = $db->prepare("
            SELECT 
                COUNT(*) as total_deliveries,
                COUNT(CASE 
                    WHEN status = 'Completed' 
                    AND COALESCE(actual_delivery_date, created_at) IS NOT NULL 
                    AND expected_delivery_date IS NOT NULL 
                    AND COALESCE(actual_delivery_date, created_at) <= expected_delivery_date 
                    THEN 1 
                END) as on_time_deliveries,
                AVG(CASE 
                    WHEN status = 'Completed' 
                    AND COALESCE(actual_delivery_date, created_at) IS NOT NULL 
                    AND expected_delivery_date IS NOT NULL 
                    THEN DATEDIFF(COALESCE(actual_delivery_date, created_at), expected_delivery_date) 
                    ELSE 0 
                END) as avg_delay_days
            FROM purchase_orders 
            WHERE supplier_id = ? 
            AND status = 'Completed'
        ");
        try {
            $deliveryStmt->execute([$s['id']]);
            $deliveryStats = $deliveryStmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_deliveries' => 0,
                'on_time_deliveries' => 0,
                'avg_delay_days' => 0
            ];
        } catch (PDOException $e) {
            error_log("Delivery query error for supplier {$s['id']}: " . $e->getMessage());
            $deliveryStats = [
                'total_deliveries' => 0,
                'on_time_deliveries' => 0,
                'avg_delay_days' => 0
            ];
        }
        
        // Ensure $deliveryRate is a valid number
        $deliveryRate = $deliveryStats['total_deliveries'] > 0 ? 
            ($deliveryStats['on_time_deliveries'] / $deliveryStats['total_deliveries']) * 100 : 0;
        if (!is_numeric($deliveryRate)) $deliveryRate = 0; // Fallback to 0 if invalid
        
        $stats['orders'] = $orderStats;
        $stats['recent_orders'] = $recentOrders;
        $stats['deliveries'] = $deliveryStats;
        $stats['delivery_rate'] = $deliveryRate;
        $supplierStats[$s['id']] = $stats;
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Enhanced Suppliers Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
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
            max-width: 100%;
            padding: 0 15px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.2s;
            width: 100%;
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
        .supplier-stats {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin: 5px 0;
        }
        .performance-badge {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.8em;
        }
        .rating-stars {
            color: #ffc107;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
        .stats-card {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        @media (max-width: 1200px) {
            .sidebar {
                width: 0;
                min-width: 0;
                overflow-x: hidden;
            }
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
            .card {
                width: 100% !important;
            }
        }
        @media (max-width: 768px) {
            .card {
                margin-bottom: 15px;
            }
            .row > [class^="col-"] {
                flex: 0 0 100%;
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
                        <h2><i class="bi bi-building"></i> Enhanced Suppliers Management</h2>
                        <div>
                            <button class="btn btn-gradient me-2" onclick="exportSuppliers()">
                                <i class="bi bi-download"></i> Export
                            </button>
                            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                <i class="bi bi-plus-circle"></i> Add Supplier
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
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search by contact or email..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="rating" class="form-control" onchange="this.form.submit()">
                                    <option value="">All Ratings</option>
                                    <option value="2" <?= $rating_filter == '2' ? 'selected' : '' ?>>2+ Stars</option>
                                    <option value="3" <?= $rating_filter == '3' ? 'selected' : '' ?>>3+ Stars</option>
                                    <option value="4" <?= $rating_filter == '4' ? 'selected' : '' ?>>4+ Stars</option>
                                    <option value="5" <?= $rating_filter == '5' ? 'selected' : '' ?>>5+ Stars</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="sort" class="form-control" onchange="this.form.submit()">
                                    <option value="name" <?= $sort_by == 'name' ? 'selected' : '' ?>>Name</option>
                                    <option value="rating" <?= $sort_by == 'rating' ? 'selected' : '' ?>>Rating</option>
                                    <option value="created_at" <?= $sort_by == 'created_at' ? 'selected' : '' ?>>Date Added</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="order" class="form-control" onchange="this.form.submit()">
                                    <option value="ASC" <?= $sort_order == 'ASC' ? 'selected' : '' ?>>Ascending</option>
                                    <option value="DESC" <?= $sort_order == 'DESC' ? 'selected' : '' ?>>Descending</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-gradient w-100">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Suppliers List -->
                    <?php if (empty($suppliers)): ?>
                        <div class="alert alert-info text-center" role="alert">
                            <i class="bi bi-info-circle"></i> No suppliers found. Add a supplier to get started!
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($suppliers as $s): 
                                $stats = $supplierStats[$s['id']];
                                $deliveryRate = $stats['delivery_rate'];
                            ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0"><?= htmlspecialchars($s['name']) ?></h5>
                                            <div class="rating-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?= $i <= $s['rating'] ? '-fill' : '' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- Contact Info -->
                                            <div class="mb-3">
                                                <?php if ($s['contact']): ?>
                                                    <small><i class="bi bi-person"></i> <?= htmlspecialchars($s['contact']) ?></small><br>
                                                <?php endif; ?>
                                                <?php if ($s['email']): ?>
                                                    <small><i class="bi bi-envelope"></i> <?= htmlspecialchars($s['email']) ?></small><br>
                                                <?php endif; ?>
                                                <?php if ($s['phone']): ?>
                                                    <small><i class="bi bi-telephone"></i> <?= htmlspecialchars($s['phone']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Statistics -->
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <div class="stats-card">
                                                        <h6 class="mb-1"><?= $stats['orders']['total_orders'] ?></h6>
                                                        <small>Total Orders</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="stats-card">
                                                        <h6 class="mb-1">₱<?= number_format($stats['orders']['total_value'], 0) ?></h6>
                                                        <small>Total Value</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Performance Indicators -->
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small><b>Delivery Performance</b>:</small>
                                                    <?php if ($stats['deliveries']['total_deliveries'] == 0): ?>
                                                        <span class="performance-badge">No deliveries</span>
                                                    <?php else: ?>
                                                        <span class="performance-badge"><?= number_format($deliveryRate, 1) ?>% On Time</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($stats['deliveries']['total_deliveries'] > 0): ?>
                                                    <div class="progress" style="height: 5px;">
                                                        <div class="progress-bar bg-success" style="width: <?= is_numeric($deliveryRate) ? $deliveryRate : 0 ?>%"></div>
                                                    </div>
                                                    <small class="text-muted">
                                                        Avg. Delay: <?= number_format($stats['deliveries']['avg_delay_days'], 1) ?> days
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Order Status -->
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <span class="badge bg-warning text-dark"><?= $stats['orders']['pending_orders'] ?> Pending</span>
                                                    <span class="badge bg-success"><?= $stats['orders']['completed_orders'] ?> Completed</span>
                                                    <span class="badge bg-secondary"><?= $stats['orders']['canceled_orders'] ?> Canceled</span>
                                                </small>
                                            </div>
                                            
                                            <!-- Recent Orders -->
                                            <?php if (!empty($stats['recent_orders'])): ?>
                                                <div class="mb-3">
                                                    <h6><i class="bi bi-clock-history"></i> Recent Orders History</h6>
                                                    <?php foreach (array_slice($stats['recent_orders'], 0, 2) as $order): ?>
                                                        <small class="d-block text-muted">
                                                            #<?= $order['id'] ?> - <?= date('M j', strtotime($order['created_at'])) ?> 
                                                            (₱<?= number_format($order['total_amount'], 0) ?>)
                                                        </small>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="btn-group w-100" role="group">
                                                <a href="purchase_orders.php?supplier_id=<?= $s['id'] ?>" class="btn btn-info btn-sm">
                                                    <i class="bi bi-cart"></i> Orders
                                                </a>
                                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editSupplierModal-<?= $s['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <a href="suppliers.php?delete=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this supplier?');">
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
                            <nav aria-label="Suppliers pagination">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($_GET) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient text-white">
                    <h5 class="modal-title">Add New Supplier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" class="row g-3 needs-validation" novalidate>
                        <div class="col-md-6">
                            <label class="form-label">Supplier Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Terms</label>
                            <select name="payment_terms" class="form-control">
                                <option value="">Select terms</option>
                                <option value="Net 30">Net 30</option>
                                <option value="Net 60">Net 60</option>
                                <option value="COD">Cash on Delivery</option>
                                <option value="Prepaid">Prepaid</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-control">
                                <option value="0">No Rating</option>
                                <option value="1">1 Star</option>
                                <option value="2">2 Stars</option>
                                <option value="3">3 Stars</option>
                                <option value="4">4 Stars</option>
                                <option value="5">5 Stars</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-gradient w-100">
                                <i class="bi bi-check-circle"></i> Add Supplier
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Supplier Modals -->
    <?php foreach ($suppliers as $s): ?>
        <div class="modal fade" id="editSupplierModal-<?= $s['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-gradient text-white">
                        <h5 class="modal-title">Edit Supplier</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="edit_id" value="<?= $s['id'] ?>">
                            <div class="col-md-6">
                                <label class="form-label">Supplier Name *</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($s['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($s['contact'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($s['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($s['phone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($s['address'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Terms</label>
                                <select name="payment_terms" class="form-control">
                                    <option value="">Select terms</option>
                                    <option value="Net 30" <?= ($s['payment_terms'] ?? '') == 'Net 30' ? 'selected' : '' ?>>Net 30</option>
                                    <option value="Net 60" <?= ($s['payment_terms'] ?? '') == 'Net 60' ? 'selected' : '' ?>>Net 60</option>
                                    <option value="COD" <?= ($s['payment_terms'] ?? '') == 'COD' ? 'selected' : '' ?>>Cash on Delivery</option>
                                    <option value="Prepaid" <?= ($s['payment_terms'] ?? '') == 'Prepaid' ? 'selected' : '' ?>>Prepaid</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Rating</label>
                                <select name="rating" class="form-control">
                                    <option value="0" <?= ($s['rating'] ?? 0) == 0 ? 'selected' : '' ?>>No Rating</option>
                                    <option value="1" <?= ($s['rating'] ?? 0) == 1 ? 'selected' : '' ?>>1 Star</option>
                                    <option value="2" <?= ($s['rating'] ?? 0) == 2 ? 'selected' : '' ?>>2 Stars</option>
                                    <option value="3" <?= ($s['rating'] ?? 0) == 3 ? 'selected' : '' ?>>3 Stars</option>
                                    <option value="4" <?= ($s['rating'] ?? 0) == 4 ? 'selected' : '' ?>>4 Stars</option>
                                    <option value="5" <?= ($s['rating'] ?? 0) == 5 ? 'selected' : '' ?>>5 Stars</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-gradient w-100">
                                    <i class="bi bi-check-circle"></i> Update Supplier
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        // Export functionality
        function exportSuppliers() {
            const suppliers = <?= json_encode($suppliers) ?>;
            const stats = <?= json_encode($supplierStats) ?>;
            
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Name,Contact,Email,Phone,Rating,Total Orders,Total Value,Pending Orders,Completed Orders,Canceled Orders,Delivery Rate\n";
            
            suppliers.forEach(supplier => {
                const supplierStats = stats[supplier.id];
                const deliveryRate = supplierStats.deliveries.total_deliveries > 0 ? 
                    ((supplierStats.deliveries.on_time_deliveries / supplierStats.deliveries.total_deliveries) * 100).toFixed(1) : '0.0';
                
                const row = [
                    `"${supplier.name}"`,
                    `"${supplier.contact || ''}"`,
                    `"${supplier.email || ''}"`,
                    `"${supplier.phone || ''}"`,
                    supplier.rating || 0,
                    supplierStats.orders.total_orders,
                    supplierStats.orders.total_value,
                    supplierStats.orders.pending_orders,
                    supplierStats.orders.completed_orders,
                    supplierStats.orders.canceled_orders,
                    `${deliveryRate}%`
                ].join(",");
                csvContent += row + "\n";
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "suppliers_export.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Auto-refresh alerts
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
        
        // Search enhancement with real-time filtering
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
        
        document.querySelector('input[name="search"]').addEventListener('input', performSearch);
    </script>
</html>