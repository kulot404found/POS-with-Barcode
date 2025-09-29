<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("❌ Access denied.");
}


try {
    // KPI: Total Users
    $totalUsers = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // KPI: Total Items
    $totalItems = (int) $db->query("SELECT COUNT(*) FROM inventory")->fetchColumn();

    // KPI: Total Sales
    $totalSales = (float) $db->query("SELECT IFNULL(SUM(total_amount), 0) FROM sales")->fetchColumn();

    // ✅ KPI: Low Stock Items (use COUNT(*) to match reports.php Restock Alerts)
    $lowStockItems = (int) $db->query("SELECT COUNT(*) FROM inventory WHERE stock < 10")->fetchColumn();

} catch (PDOException $e) {
    error_log("Database error (index): " . $e->getMessage());
    die("Database error: Unable to fetch dashboard statistics.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Dashboard - POS System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { background:#f4f6f9; margin:0; padding:0; }
    .sidebar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color:white; min-height:100vh; position:fixed; top:0; left:0; width:250px; z-index:1000;
    }
    .main-content { margin-left:250px; padding:20px; width:calc(100% - 250px); }
    .card-hover { transition:.3s; border:none; border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,.1); }
    .card-hover:hover { transform: translateY(-5px); box-shadow:0 8px 25px rgba(0,0,0,.2); }
    @media (max-width:1200px){
        .main-content { margin-left:0; padding:10px; width:100%; }
        .sidebar { position:relative; width:100%; height:auto; }
    }
</style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column">
      <?php include 'includes/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
      <h2 class="mb-3">Welcome, Admin!</h2>
      <p class="text-muted mb-4">Manage your store efficiently from this dashboard.</p>

      <!-- Navigation Cards -->
      <div class="row">
        <div class="col-12 col-sm-6 col-md-4 mb-4">
          <a href="inventory.php" class="text-decoration-none">
            <div class="card card-hover">
              <div class="card-body text-center">
                <i class="bi bi-box-seam display-4 mb-3 text-primary"></i>
                <h5 class="card-title text-dark">Inventory</h5>
                <p class="text-muted">Manage stock and items</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-md-4 mb-4">
          <a href="suppliers.php" class="text-decoration-none">
            <div class="card card-hover">
              <div class="card-body text-center">
                <i class="bi bi-truck display-4 mb-3 text-success"></i>
                <h5 class="card-title text-dark">Suppliers</h5>
                <p class="text-muted">Track supplier details</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-md-4 mb-4">
          <a href="users.php" class="text-decoration-none">
            <div class="card card-hover">
              <div class="card-body text-center">
                <i class="bi bi-people display-4 mb-3 text-info"></i>
                <h5 class="card-title text-dark">Users</h5>
                <p class="text-muted">Manage system users</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-md-4 mb-4">
          <a href="purchase_orders.php" class="text-decoration-none">
            <div class="card card-hover">
              <div class="card-body text-center">
                <i class="bi bi-card-list display-4 mb-3 text-warning"></i>
                <h5 class="card-title text-dark">Purchase Orders</h5>
                <p class="text-muted">Track orders & deliveries</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-md-4 mb-4">
          <a href="reports.php" class="text-decoration-none">
            <div class="card card-hover">
              <div class="card-body text-center">
                <i class="bi bi-graph-up display-4 mb-3 text-danger"></i>
                <h5 class="card-title text-dark">Reports</h5>
                <p class="text-muted">Sales & inventory analytics</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-md-4 mb-4">
          <a href="pos.php" class="text-decoration-none">
            <div class="card card-hover">
              <div class="card-body text-center">
                <i class="bi bi-cash-register display-4 mb-3 text-success"></i>
                <h5 class="card-title text-dark">POS Terminal</h5>
                <p class="text-muted">Point of Sale system</p>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="row mt-4">
        <div class="col-md-3 mb-3">
          <div class="card bg-primary text-white p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h4 class="mb-0"><?= $totalItems ?></h4>
                <small>Total Items</small>
              </div>
              <i class="bi bi-box-seam display-6"></i>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card bg-success text-white p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h4 class="mb-0">₱<?= number_format($totalSales, 2) ?></h4>
                <small>Total Sales</small>
              </div>
              <i class="bi bi-cash-stack display-6"></i>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card bg-warning text-white p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <!-- initial accurate value -->
                <h4 class="mb-0" id="lowStockCount"><?= (int)$lowStockItems ?></h4>
                <small>Low Stock Items</small>
              </div>
              <i class="bi bi-exclamation-triangle display-6"></i>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card bg-info text-white p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h4 class="mb-0"><?= $totalUsers ?></h4>
                <small>Active Users</small>
              </div>
              <i class="bi bi-people display-6"></i>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /main-content -->
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// 🔄 Auto-refresh Low Stock count using COUNT(*) endpoint (accurate)
function refreshLowStock() {
  $.getJSON('get_low_stock.php', function(resp) {
    if (resp && typeof resp.count !== 'undefined') {
      document.getElementById('lowStockCount').textContent = resp.count;
    }
  });
}
refreshLowStock();                // first call
setInterval(refreshLowStock, 5000); // every 5s
</script>
</body>
</html>
