<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Date range handling
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today
$reportType = $_GET['report_type'] ?? 'overview';

// Enhanced KPI Calculations
function getKPIData($db, $dateFrom, $dateTo) {
    // Total Sales with date range
    $totalSales = $db->prepare("SELECT IFNULL(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) BETWEEN ? AND ?");
    $totalSales->execute([$dateFrom, $dateTo]);
    $totalSales = $totalSales->fetch(PDO::FETCH_ASSOC)['total'];

    // Previous period comparison
    $prevDateFrom = date('Y-m-d', strtotime($dateFrom . ' -1 month'));
    $prevDateTo = date('Y-m-d', strtotime($dateTo . ' -1 month'));
    
    $prevSales = $db->prepare("SELECT IFNULL(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) BETWEEN ? AND ?");
    $prevSales->execute([$prevDateFrom, $prevDateTo]);
    $prevSales = $prevSales->fetch(PDO::FETCH_ASSOC)['total'];
    
    $salesGrowth = $prevSales > 0 ? (($totalSales - $prevSales) / $prevSales) * 100 : 0;

    // Total Orders with date range
    $totalOrders = $db->prepare("SELECT COUNT(*) as count FROM purchase_orders WHERE DATE(created_at) BETWEEN ? AND ?");
    $totalOrders->execute([$dateFrom, $dateTo]);
    $totalOrders = $totalOrders->fetch(PDO::FETCH_ASSOC)['count'];

    // Average order value
    $avgOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

    // Low stock items count
    $lowStockCount = $db->query("SELECT COUNT(*) as count FROM inventory WHERE stock < 10")->fetch(PDO::FETCH_ASSOC)['count'];

    // Total inventory value
    $inventoryValue = $db->query("SELECT IFNULL(SUM(price * stock), 0) as total FROM inventory")->fetch(PDO::FETCH_ASSOC)['total'];

    return [
        'totalSales' => $totalSales,
        'salesGrowth' => $salesGrowth,
        'totalOrders' => $totalOrders,
        'avgOrderValue' => $avgOrderValue,
        'lowStockCount' => $lowStockCount,
        'inventoryValue' => $inventoryValue
    ];
}

// Sales Analytics
function getSalesAnalytics($db, $dateFrom, $dateTo) {
    // Daily sales for the period
    $dailySales = $db->prepare("
        SELECT DATE(created_at) as date, SUM(total_amount) as total, COUNT(*) as orders
        FROM sales
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $dailySales->execute([$dateFrom, $dateTo]);
    
    // Top selling items
    $topItems = $db->prepare("
        SELECT i.name, SUM(s.quantity) as total_sold, SUM(s.total_amount) as revenue
        FROM sales s
        JOIN inventory i ON s.item_id = i.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY s.item_id, i.name
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $topItems->execute([$dateFrom, $dateTo]);
    
    return [
        'dailySales' => $dailySales->fetchAll(PDO::FETCH_ASSOC),
        'topItems' => $topItems->fetchAll(PDO::FETCH_ASSOC)
    ];
}

// Inventory Analytics
function getInventoryAnalytics($db) {
    // Stock levels analysis
    $stockAnalysis = $db->query("
        SELECT 
            CASE 
                WHEN stock = 0 THEN 'Out of Stock'
                WHEN stock < 10 THEN 'Low Stock'
                WHEN stock < 50 THEN 'Medium Stock'
                ELSE 'Well Stocked'
            END as stock_level,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM inventory), 2) as percentage
        FROM inventory
        GROUP BY stock_level
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Inventory turnover (items with sales data)
    $turnoverAnalysis = $db->query("
        SELECT 
            i.name,
            i.stock,
            i.price,
            COUNT(s.id) as sales_frequency,
            IFNULL(SUM(s.quantity), 0) as total_sold,
            IFNULL(SUM(s.total_amount), 0) as revenue,
            CASE 
                WHEN COUNT(s.id) >= 20 THEN 'Fast Moving'
                WHEN COUNT(s.id) >= 5 THEN 'Moderate Moving'
                WHEN COUNT(s.id) > 0 THEN 'Slow Moving'
                ELSE 'No Sales'
            END as movement_category
        FROM inventory i
        LEFT JOIN sales s ON i.id = s.item_id
        GROUP BY i.id, i.name, i.stock, i.price
        ORDER BY sales_frequency DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'stockAnalysis' => $stockAnalysis,
        'turnoverAnalysis' => $turnoverAnalysis
    ];
}

// Get data based on current selections
$kpiData = getKPIData($db, $dateFrom, $dateTo);
$salesAnalytics = getSalesAnalytics($db, $dateFrom, $dateTo);
$inventoryAnalytics = getInventoryAnalytics($db);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Advanced Reports & Analytics</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
    body {
        background: #f8fafc;
        margin: 0;
        padding: 0;
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
    }
    .sidebar-link {
        color: white;
        text-decoration: none;
        transition: all 0.3s ease;
        border: none;
        display: block;
        padding: 12px 16px;
        margin-bottom: 8px;
        border-radius: 8px;
    }
    .sidebar-link:hover {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        transform: translateX(5px);
    }
    .sidebar-link.active {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }
    .main-content {
        margin-left: 250px;
        padding: 20px;
        width: calc(100% - 250px);
    }
    .card {
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border: none;
        transition: transform 0.2s ease;
    }
    .card:hover {
        transform: translateY(-2px);
    }
    .kpi-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }
    .kpi-card.success {
        background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
    }
    .kpi-card.warning {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    .kpi-card.info {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    .growth-positive {
        color: #28a745;
        font-weight: bold;
    }
    .growth-negative {
        color: #dc3545;
        font-weight: bold;
    }
    .chart-container {
        position: relative;
        height: 300px;
        margin: 20px 0;
    }
    .filter-section {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .badge-custom {
        font-size: 0.8em;
        padding: 6px 12px;
    }
    .report-tabs {
        border-bottom: 2px solid #e9ecef;
        margin-bottom: 30px;
    }
    .report-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #6c757d;
        font-weight: 500;
        padding: 15px 20px;
        transition: all 0.3s ease;
    }
    .report-tabs .nav-link:hover {
        border-bottom-color: #667eea;
        color: #667eea;
    }
    .report-tabs .nav-link.active {
        border-bottom-color: #667eea;
        color: #667eea;
        background: none;
    }
    @media (max-width: 1200px) {
        .main-content {
            margin-left: 0;
            padding: 10px;
            width: 100%;
        }
        .sidebar {
            position: relative;
            width: 100%;
            height: auto;
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
        <div class="main-content">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-bar-chart-line"></i> Advanced Reports & Analytics</h2>
                <button class="btn btn-primary" onclick="exportReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
            </div>

            <!-- Filters Section -->
            <div class="filter-section">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?= $dateFrom ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?= $dateTo ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="report_type">
                            <option value="overview" <?= $reportType == 'overview' ? 'selected' : '' ?>>Overview</option>
                            <option value="sales" <?= $reportType == 'sales' ? 'selected' : '' ?>>Sales Focus</option>
                            <option value="inventory" <?= $reportType == 'inventory' ? 'selected' : '' ?>>Inventory Focus</option>
                            <option value="detailed" <?= $reportType == 'detailed' ? 'selected' : '' ?>>Detailed Analysis</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Enhanced KPI Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="card kpi-card success text-center">
                        <div class="card-body">
                            <i class="bi bi-currency-dollar fs-1 mb-2"></i>
                            <h6>Total Sales</h6>
                            <h4>₱<?= number_format($kpiData['totalSales'], 2) ?></h4>
                            <small class="<?= $kpiData['salesGrowth'] >= 0 ? 'growth-positive' : 'growth-negative' ?>">
                                <i class="bi bi-<?= $kpiData['salesGrowth'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= number_format(abs($kpiData['salesGrowth']), 1) ?>% vs last period
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4">
                    <div class="card kpi-card text-center">
                        <div class="card-body">
                            <i class="bi bi-cart-check fs-1 mb-2"></i>
                            <h6>Total Orders</h6>
                            <h4><?= number_format($kpiData['totalOrders']) ?></h4>
                            <small>Orders in period</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4">
                    <div class="card kpi-card info text-center">
                        <div class="card-body">
                            <i class="bi bi-calculator fs-1 mb-2"></i>
                            <h6>Avg Order Value</h6>
                            <h4>₱<?= number_format($kpiData['avgOrderValue'], 2) ?></h4>
                            <small>Per transaction</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4">
                    <div class="card kpi-card <?= $kpiData['lowStockCount'] > 0 ? 'warning' : 'success' ?> text-center">
                        <div class="card-body">
                            <i class="bi bi-exclamation-triangle fs-1 mb-2"></i>
                            <h6>Low Stock Items</h6>
                            <h4><?= $kpiData['lowStockCount'] ?></h4>
                            <small>Need attention</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4">
                    <div class="card kpi-card text-center">
                        <div class="card-body">
                            <i class="bi bi-box-seam fs-1 mb-2"></i>
                            <h6>Inventory Value</h6>
                            <h4>₱<?= number_format($kpiData['inventoryValue'], 2) ?></h4>
                            <small>Total stock value</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Tabs -->
            <ul class="nav nav-tabs report-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#salesReport" role="tab">
                        <i class="bi bi-graph-up"></i> Sales Analysis
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#inventoryReport" role="tab">
                        <i class="bi bi-boxes"></i> Inventory Analysis
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#performanceReport" role="tab">
                        <i class="bi bi-speedometer2"></i> Performance Insights
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Sales Analysis Tab -->
                <div class="tab-pane fade show active" id="salesReport">
                    <div class="row g-4 mb-4">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <i class="bi bi-bar-chart"></i> Daily Sales Trend
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="salesTrendChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <i class="bi bi-trophy"></i> Top Selling Items
                                </div>
                                <div class="card-body">
                                    <?php foreach (array_slice($salesAnalytics['topItems'], 0, 5) as $index => $item): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <span class="badge bg-primary rounded-pill me-2"><?= $index + 1 ?></span>
                                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                                <br><small class="text-muted">₱<?= number_format($item['revenue'], 2) ?> revenue</small>
                                            </div>
                                            <span class="badge bg-success badge-custom"><?= $item['total_sold'] ?> sold</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Analysis Tab -->
                <div class="tab-pane fade" id="inventoryReport">
                    <!-- Critical Items Alert Section -->
                    <div class="row g-4 mb-4">
                        <div class="col-lg-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <i class="bi bi-exclamation-triangle"></i> 🚨 Items Need Restocking (Stock < 10)
                                </div>
                                <div class="card-body">
                                    <?php 
                                    $restockItems = $db->query("
                                        SELECT name, stock, price 
                                        FROM inventory 
                                        WHERE stock < 10 
                                        ORDER BY stock ASC
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php if (!empty($restockItems)): ?>
                                        <div class="alert alert-danger mb-3">
                                            <strong><?= count($restockItems) ?> items</strong> need immediate restocking!
                                        </div>
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <?php foreach ($restockItems as $item): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                                    <div>
                                                        <strong class="text-danger"><?= htmlspecialchars($item['name']) ?></strong>
                                                        <br><small class="text-muted">Price: ₱<?= number_format($item['price'], 2) ?></small>
                                                    </div>
                                                    <span class="badge bg-danger fs-6">
                                                        <?= $item['stock'] ?> left
                                                        <?php if ($item['stock'] == 0): ?>
                                                            <br><small>OUT OF STOCK!</small>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-success">
                                            <i class="bi bi-check-circle"></i> All items are well stocked!
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <i class="bi bi-turtle"></i> 🐢 Slow Moving Items (Low Sales)
                                </div>
                                <div class="card-body">
                                    <?php 
                                    $slowMovingItems = $db->query("
                                        SELECT i.name, i.stock, i.price, COUNT(s.id) as sales_count,
                                               IFNULL(SUM(s.quantity), 0) as total_sold
                                        FROM inventory i
                                        LEFT JOIN sales s ON i.id = s.item_id
                                        GROUP BY i.id, i.name, i.stock, i.price
                                        HAVING sales_count <= 2
                                        ORDER BY sales_count ASC, i.stock DESC
                                        LIMIT 15
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php if (!empty($slowMovingItems)): ?>
                                        <div class="alert alert-warning mb-3">
                                            <strong><?= count($slowMovingItems) ?> items</strong> are moving slowly. Consider promotions!
                                        </div>
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <?php foreach ($slowMovingItems as $item): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                                    <div>
                                                        <strong class="text-warning"><?= htmlspecialchars($item['name']) ?></strong>
                                                        <br><small class="text-muted">Stock: <?= $item['stock'] ?> | Price: ₱<?= number_format($item['price'], 2) ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-warning text-dark"><?= $item['sales_count'] ?> sales</span>
                                                        <br><small class="text-muted"><?= $item['total_sold'] ?> sold total</small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-success">
                                            <i class="bi bi-check-circle"></i> All items have good sales performance!
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Section -->
                    <div class="row g-4 mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <i class="bi bi-pie-chart"></i> Stock Level Distribution
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="stockLevelChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <i class="bi bi-trophy"></i> Fast Moving Items (Your Best Sellers!)
                                </div>
                                <div class="card-body">
                                    <?php 
                                    $fastMovingItems = $db->query("
                                        SELECT i.name, i.stock, COUNT(s.id) as sales_count,
                                               IFNULL(SUM(s.quantity), 0) as total_sold,
                                               IFNULL(SUM(s.total_amount), 0) as revenue
                                        FROM inventory i
                                        LEFT JOIN sales s ON i.id = s.item_id
                                        GROUP BY i.id, i.name, i.stock
                                        HAVING sales_count >= 5
                                        ORDER BY sales_count DESC
                                        LIMIT 10
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php if (!empty($fastMovingItems)): ?>
                                        <div class="alert alert-success mb-3">
                                            <i class="bi bi-graph-up-arrow"></i> <strong><?= count($fastMovingItems) ?> items</strong> are your top performers!
                                        </div>
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <?php $rank = 1; foreach ($fastMovingItems as $item): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                                    <div>
                                                        <span class="badge bg-primary rounded-pill me-2"><?= $rank++ ?></span>
                                                        <strong class="text-success"><?= htmlspecialchars($item['name']) ?></strong>
                                                        <br><small class="text-muted">Stock: <?= $item['stock'] ?> | Revenue: ₱<?= number_format($item['revenue'], 2) ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-success"><?= $item['sales_count'] ?> sales</span>
                                                        <br><small class="text-success"><?= $item['total_sold'] ?> units sold</small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> No fast-moving items yet. Keep selling!
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Inventory Table -->
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <i class="bi bi-table"></i> Detailed Inventory Performance
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Item Name</th>
                                            <th class="text-center">Stock</th>
                                            <th class="text-center">Price</th>
                                            <th class="text-center">Sales Frequency</th>
                                            <th class="text-center">Total Sold</th>
                                            <th class="text-center">Revenue</th>
                                            <th class="text-center">Category</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inventoryAnalytics['turnoverAnalysis'] as $item): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                                <td class="text-center">
                                                    <span class="badge <?= $item['stock'] < 10 ? 'bg-danger' : ($item['stock'] < 50 ? 'bg-warning' : 'bg-success') ?>">
                                                        <?= $item['stock'] ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">₱<?= number_format($item['price'], 2) ?></td>
                                                <td class="text-center"><?= $item['sales_frequency'] ?></td>
                                                <td class="text-center"><?= $item['total_sold'] ?></td>
                                                <td class="text-center">₱<?= number_format($item['revenue'], 2) ?></td>
                                                <td class="text-center">
                                                    <span class="badge <?= 
                                                        $item['movement_category'] == 'Fast Moving' ? 'bg-success' : 
                                                        ($item['movement_category'] == 'Moderate Moving' ? 'bg-warning' : 
                                                        ($item['movement_category'] == 'Slow Moving' ? 'bg-info' : 'bg-secondary'))
                                                    ?>"><?= $item['movement_category'] ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Insights Tab -->
                <div class="tab-pane fade" id="performanceReport">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <i class="bi bi-lightbulb"></i> Key Insights
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <h6><i class="bi bi-info-circle"></i> Sales Performance</h6>
                                        <p class="mb-1">Total sales: ₱<?= number_format($kpiData['totalSales'], 2) ?></p>
                                        <p class="mb-0">Growth: <?= number_format($kpiData['salesGrowth'], 1) ?>% compared to previous period</p>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <h6><i class="bi bi-exclamation-triangle"></i> Inventory Alerts</h6>
                                        <p class="mb-1"><?= $kpiData['lowStockCount'] ?> items need restocking</p>
                                        <p class="mb-0">Total inventory value: ₱<?= number_format($kpiData['inventoryValue'], 2) ?></p>
                                    </div>
                                    
                                    <div class="alert alert-success">
                                        <h6><i class="bi bi-check-circle"></i> Recommendations</h6>
                                        <ul class="mb-0">
                                            <?php if ($kpiData['lowStockCount'] > 0): ?>
                                                <li>Restock <?= $kpiData['lowStockCount'] ?> low inventory items</li>
                                            <?php endif; ?>
                                            <?php if ($kpiData['salesGrowth'] < 0): ?>
                                                <li>Focus on marketing to improve sales performance</li>
                                            <?php endif; ?>
                                            <li>Monitor fast-moving items for potential upselling</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header bg-dark text-white">
                                    <i class="bi bi-calendar3"></i> Period Summary
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-6 mb-3">
                                            <h4 class="text-primary"><?= $kpiData['totalOrders'] ?></h4>
                                            <small class="text-muted">Total Orders</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <h4 class="text-success">₱<?= number_format($kpiData['avgOrderValue'], 2) ?></h4>
                                            <small class="text-muted">Avg Order Value</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <h4 class="text-info"><?= count($inventoryAnalytics['turnoverAnalysis']) ?></h4>
                                            <small class="text-muted">Total Products</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <h4 class="text-warning">₱<?= number_format($kpiData['inventoryValue'], 2) ?></h4>
                                            <small class="text-muted">Stock Value</small>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <h6>Report Period</h6>
                                    <p class="text-muted mb-1">From: <?= date('F d, Y', strtotime($dateFrom)) ?></p>
                                    <p class="text-muted mb-0">To: <?= date('F d, Y', strtotime($dateTo)) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Sales Trend Chart
const salesDates = <?= json_encode(array_column($salesAnalytics['dailySales'], 'date')) ?>;
const salesAmounts = <?= json_encode(array_column($salesAnalytics['dailySales'], 'total')) ?>;

const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
new Chart(salesTrendCtx, {
    type: 'line',
    data: {
        labels: salesDates,
        datasets: [{
            label: 'Daily Sales (₱)',
            data: salesAmounts,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => "₱" + new Intl.NumberFormat().format(ctx.parsed.y)
                }
            }
        },
        scales: {
            y: { 
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱' + new Intl.NumberFormat().format(value);
                    }
                }
            }
        }
    }
});

// Stock Level Distribution Chart
const stockLabels = <?= json_encode(array_column($inventoryAnalytics['stockAnalysis'], 'stock_level')) ?>;
const stockCounts = <?= json_encode(array_column($inventoryAnalytics['stockAnalysis'], 'count')) ?>;

const stockLevelCtx = document.getElementById('stockLevelChart').getContext('2d');
new Chart(stockLevelCtx, {
    type: 'doughnut',
    data: {
        labels: stockLabels,
        datasets: [{
            data: stockCounts,
            backgroundColor: [
                '#dc3545', // Out of Stock - Red
                '#ffc107', // Low Stock - Yellow
                '#17a2b8', // Medium Stock - Info
                '#28a745'  // Well Stocked - Green
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const percentage = context.dataset.data[context.dataIndex];
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percent = Math.round((percentage / total) * 100);
                        return context.label + ': ' + percentage + ' items (' + percent + '%)';
                    }
                }
            }
        }
    }
});

// Export Report Function
function exportReport() {
    const reportData = {
        dateRange: '<?= $dateFrom ?> to <?= $dateTo ?>',
        kpis: {
            totalSales: '₱<?= number_format($kpiData['totalSales'], 2) ?>',
            salesGrowth: '<?= number_format($kpiData['salesGrowth'], 1) ?>%',
            totalOrders: <?= $kpiData['totalOrders'] ?>,
            avgOrderValue: '₱<?= number_format($kpiData['avgOrderValue'], 2) ?>',
            lowStockCount: <?= $kpiData['lowStockCount'] ?>,
            inventoryValue: '₱<?= number_format($kpiData['inventoryValue'], 2) ?>'
        }
    };
    
    // Create downloadable report
    const reportContent = `
SALES & INVENTORY REPORT
========================
Period: ${reportData.dateRange}
Generated: ${new Date().toLocaleString()}

KEY PERFORMANCE INDICATORS
--------------------------
Total Sales: ${reportData.kpis.totalSales}
Sales Growth: ${reportData.kpis.salesGrowth}
Total Orders: ${reportData.kpis.totalOrders}
Average Order Value: ${reportData.kpis.avgOrderValue}
Low Stock Items: ${reportData.kpis.lowStockCount}
Total Inventory Value: ${reportData.kpis.inventoryValue}

TOP SELLING ITEMS
-----------------
<?php foreach (array_slice($salesAnalytics['topItems'], 0, 10) as $index => $item): ?>
<?= ($index + 1) ?>. <?= htmlspecialchars($item['name']) ?> - <?= $item['total_sold'] ?> sold (₱<?= number_format($item['revenue'], 2) ?>)
<?php endforeach; ?>

INVENTORY ALERTS
----------------
<?php 
$alerts = $db->query("SELECT name, stock FROM inventory WHERE stock < 10 ORDER BY stock ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($alerts as $alert): ?>
- <?= htmlspecialchars($alert['name']) ?>: <?= $alert['stock'] ?> remaining
<?php endforeach; ?>

RECOMMENDATIONS
---------------
<?php if ($kpiData['lowStockCount'] > 0): ?>
- Restock <?= $kpiData['lowStockCount'] ?> low inventory items immediately
<?php endif; ?>
<?php if ($kpiData['salesGrowth'] < 0): ?>
- Implement marketing strategies to improve sales performance
<?php endif; ?>
- Monitor fast-moving items for potential promotional opportunities
- Consider adjusting pricing for slow-moving inventory
    `;
    
    const blob = new Blob([reportContent], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `sales_inventory_report_${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    // Show success message
    const toast = document.createElement('div');
    toast.className = 'position-fixed top-0 end-0 p-3';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="toast show" role="alert">
            <div class="toast-header bg-success text-white">
                <i class="bi bi-check-circle me-2"></i>
                <strong class="me-auto">Export Successful</strong>
            </div>
            <div class="toast-body">
                Report has been downloaded successfully.
            </div>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => document.body.removeChild(toast), 3000);
}

// Auto-refresh functionality (optional)
function setupAutoRefresh() {
    setInterval(() => {
        // Only refresh if user is on the overview tab and hasn't interacted recently
        const activeTab = document.querySelector('.nav-tabs .nav-link.active');
        if (activeTab && activeTab.getAttribute('href') === '#salesReport') {
            // You can add AJAX refresh logic here
            console.log('Auto-refresh triggered');
        }
    }, 300000); // 5 minutes
}

// Initialize auto-refresh
// setupAutoRefresh();

// Print Report Function
function printReport() {
    window.print();
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportReport();
    }
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printReport();
    }
});

// Add tooltips to KPI cards
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips if any are added
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Responsive chart handling
window.addEventListener('resize', function() {
    // Charts will automatically resize with Chart.js responsive option
});

// Enhanced error handling for charts
Chart.defaults.plugins.legend.onClick = function(e, legendItem, legend) {
    const index = legendItem.datasetIndex;
    const chart = legend.chart;
    
    if (chart.isDatasetVisible(index)) {
        chart.hide(index);
        legendItem.hidden = true;
    } else {
        chart.show(index);
        legendItem.hidden = false;
    }
};

// Add loading states for better UX
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = `
            <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
    }
}

// Function to refresh data via AJAX (optional enhancement)
function refreshData() {
    showLoading('salesReport');
    // Add AJAX call here to refresh data without full page reload
    // location.reload(); // Fallback for now
}
</script>

<!-- Print Styles -->
<style media="print">
    .sidebar { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
    .btn { display: none !important; }
    .nav-tabs { display: none !important; }
    .tab-content .tab-pane { display: block !important; }
    .card { break-inside: avoid; margin-bottom: 20px; }
    .chart-container { height: 300px !important; }
</style>

</body>
</html>