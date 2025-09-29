<style>
/* Sidebar Styles */
.sidebar {
    width: 250px;
    min-height: 100vh;
    background: linear-gradient(180deg, #0d6efd, #0b5ed7);
    color: #fff;
    display: flex;
    flex-direction: column;
}

.sidebar h3 {
    color: #fff;
    font-weight: bold;
}

.sidebar-link {
    display: block;
    padding: 10px 15px;
    margin: 5px 0;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    transition: background 0.3s, padding-left 0.3s;
}

.sidebar-link:hover {
    background: rgba(255, 255, 255, 0.2);
    padding-left: 20px;
}

.sidebar-link.active {
    background: rgba(255, 255, 255, 0.3);
    font-weight: bold;
}
</style>

<div class="sidebar d-flex flex-column">
    <div class="p-3 text-center">
        <h3 class="mb-0">POS Admin</h3>
    </div>
    <div class="flex-grow-1 px-3">
        <a href="index.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="inventory.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : '' ?>">
            <i class="bi bi-box-seam me-2"></i> Inventory
        </a>
        <a href="suppliers.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : '' ?>">
            <i class="bi bi-truck me-2"></i> Suppliers
        </a>
        <a href="users.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
            <i class="bi bi-people me-2"></i> Users
        </a>
        <a href="purchase_orders.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'purchase_orders.php' ? 'active' : '' ?>">
            <i class="bi bi-card-list me-2"></i> Purchase Orders
        </a>
        <a href="reports.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
            <i class="bi bi-graph-up me-2"></i> Reports
        </a>
    </div>
    <div class="p-3">
        <a href="logout.php" class="btn btn-danger w-100 text-white rounded">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </div>
</div> 
