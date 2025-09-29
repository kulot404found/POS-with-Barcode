<?php
require_once 'config.php';
require_login('admin');
// Include Composer autoloader
require_once 'vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorSVG;
$generator = new BarcodeGeneratorSVG();

// Function to generate a simple numeric barcode that's easy to scan
function generateSimpleBarcode() {
    // Generate a simple 12-digit numeric barcode (easier for cameras to read)
    $timestamp = substr(time(), -6); // Last 6 digits of timestamp
    $random = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    return $timestamp . $random;
}

// Function to generate a Code 128 barcode (most compatible)
function generateCode128Barcode() {
    // Generate a shorter, simpler barcode for better camera detection
    $prefix = 'ITM'; // Item prefix
    $timestamp = substr(time(), -4);
    $random = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . $timestamp . $random;
}

// Handle AJAX Add Item
if(isset($_POST['action']) && $_POST['action']==='add_item'){
    $name = trim($_POST['name']);
    $stock = intval($_POST['stock']);
    $price = floatval($_POST['price']);
    $expiration_date = !empty(trim($_POST['expiration_date'])) ? trim($_POST['expiration_date']) : null;
    
    // Validate expiration date (must be today or in the future)
    if ($expiration_date && strtotime($expiration_date) < strtotime(date('Y-m-d'))) {
        echo json_encode([
            'success' => false,
            'error' => 'Expiration date must be today or in the future'
        ]);
        exit;
    }
    
    // Check if barcode was provided (from scanner)
    $barcode = isset($_POST['barcode']) && !empty(trim($_POST['barcode'])) 
        ? trim($_POST['barcode']) 
        : generateSimpleBarcode(); // Generate simple numeric barcode for better scanning
    
    try {
        // Check if barcode already exists
        $checkStmt = $db->prepare("SELECT id FROM inventory WHERE barcode = ?");
        $checkStmt->execute([$barcode]);
        if ($checkStmt->fetch()) {
            echo json_encode([
                'success' => false,
                'error' => 'Barcode already exists in database'
            ]);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO inventory (name, stock, price, barcode, expiration_date) VALUES (?,?,?,?,?)");
        $stmt->execute([$name, $stock, $price, $barcode, $expiration_date]);
        $id = $db->lastInsertId();
        
        // Generate SVG barcode - use Code 128 for all barcodes (most reliable)
        try {
            $barcodeSVG = $generator->getBarcode($barcode, $generator::TYPE_CODE_128, 3, 80);
            $barcodeDisplay = 'data:image/svg+xml;base64,' . base64_encode($barcodeSVG);
        } catch (Exception $barcodeError) {
            $barcodeDisplay = null;
            error_log("Barcode generation error: " . $barcodeError->getMessage());
        }
       
        echo json_encode([
            'success' => true,
            'id' => $id,
            'name' => htmlspecialchars($name),
            'stock' => $stock,
            'price' => number_format($price, 2),
            'barcode' => $barcode,
            'barcode_img' => $barcodeDisplay,
            'barcode_error' => $barcodeDisplay === null,
            'expiration_date' => $expiration_date ? date('M d, Y', strtotime($expiration_date)) : 'N/A',
            'reload_needed' => false // No reload needed
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to add item: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle AJAX Lookup Item by Barcode
if(isset($_POST['action']) && $_POST['action']==='lookup_item'){
    $barcode = trim($_POST['barcode']);
    try {
        $stmt = $db->prepare("SELECT * FROM inventory WHERE barcode = ?");
        $stmt->execute([$barcode]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($item) {
            echo json_encode([
                'success' => true,
                'found' => true,
                'item' => [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'stock' => $item['stock'],
                    'price' => $item['price'],
                    'barcode' => $item['barcode'],
                    'expiration_date' => $item['expiration_date'] ? date('M d, Y', strtotime($item['expiration_date'])) : 'N/A'
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'found' => false,
                'barcode' => $barcode
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle AJAX Update Stock
if(isset($_POST['action']) && $_POST['action']==='update_stock'){
    $id = intval($_POST['id']);
    $stock = intval($_POST['stock']);
    try {
        $stmt = $db->prepare("UPDATE inventory SET stock = ? WHERE id = ?");
        $stmt->execute([$stock, $id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update stock: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle AJAX Generate New Barcode
if(isset($_POST['action']) && $_POST['action']==='generate_barcode'){
    $id = intval($_POST['id']);
    $newBarcode = generateSimpleBarcode();
    
    try {
        // Check if new barcode already exists
        $checkStmt = $db->prepare("SELECT id FROM inventory WHERE barcode = ? AND id != ?");
        $checkStmt->execute([$newBarcode, $id]);
        if ($checkStmt->fetch()) {
            // Try again with a different barcode
            $newBarcode = generateSimpleBarcode();
        }
        
        $stmt = $db->prepare("UPDATE inventory SET barcode = ? WHERE id = ?");
        $stmt->execute([$newBarcode, $id]);
        
        // Generate new barcode image - always use Code 128
        try {
            $barcodeSVG = $generator->getBarcode($newBarcode, $generator::TYPE_CODE_128, 3, 80);
            $barcodeDisplay = 'data:image/svg+xml;base64,' . base64_encode($barcodeSVG);
        } catch (Exception $barcodeError) {
            $barcodeDisplay = null;
            error_log("Barcode regeneration error: " . $barcodeError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'barcode' => $newBarcode,
            'barcode_img' => $barcodeDisplay
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to generate new barcode: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Delete Item
if(isset($_GET['delete'])){
    $stmt=$db->prepare("DELETE FROM inventory WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: inventory.php" . (isset($_GET['page']) ? "?page=".$_GET['page'] : ""));
    exit;
}

// Pagination setup - Changed to 4 items per page
$itemsPerPage = 4; // Changed from 10 to 4
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Fetch items with pagination
$search = isset($_GET['search']) ? "%".trim($_GET['search'])."%" : "%";

// Count total items for pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM inventory WHERE name LIKE ? OR barcode LIKE ?");
$countStmt->execute([$search, $search]);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Fetch items for current page - Fix for MariaDB LIMIT/OFFSET binding
$sql = "SELECT * FROM inventory WHERE name LIKE ? OR barcode LIKE ? ORDER BY id DESC LIMIT " . (int)$itemsPerPage . " OFFSET " . (int)$offset;
$stmt = $db->prepare($sql);
$stmt->execute([$search, $search]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
<style>
body{background:#f4f6f9;}
.card{border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);}
.sidebar{background:#4e54c8;color:white;min-height:100vh;}
.sidebar a{color:white;text-decoration:none;display:block;padding:10px;border-radius:5px;}
.sidebar a:hover{background:#3b3f9c;}
.barcode{background:white;padding:8px;max-width:120px;height:auto;border:1px solid #ddd;border-radius:4px;}
.badge-low{background-color:#dc3545;}
.alert-success{display:none;}
.loading{display:none;}

/* Fixed table container with scrolling */
.table-container {
    min-height: 400px; /* Adjusted height for 4 items */
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

.table-container .table {
    margin-bottom: 0;
}

.table-container .table thead th {
    background: #343a40;
    color: white;
    border-bottom: 2px solid #dee2e6;
}

/* Compact table styling */
.table td, .table th {
    padding: 12px 15px; /* Increased padding for better spacing with only 4 items */
    vertical-align: middle;
    font-size: 14px;
}

/* Smaller barcode containers */
.barcode-container {
    background: #f8f9fa;
    padding: 6px;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    max-width: 140px;
}

.barcode-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.barcode-text {
    font-family: 'Courier New', monospace;
    font-size: 10px;
    font-weight: bold;
    color: #495057;
    letter-spacing: 0.5px;
}

/* Barcode image clickable style */
.barcode-clickable {
    cursor: pointer;
    transition: transform 0.2s ease;
}

.barcode-clickable:hover {
    transform: scale(1.05);
}

/* Expiration date styling */
.expiration-date-expired {
    background-color: #dc3545;
    color: white;
}
.expiration-date-soon {
    background-color: #ffc107;
    color: black;
}
.expiration-date-valid {
    background-color: #28a745;
    color: white;
}

/* Flat Pagination Styling */
.flat-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-top: 25px;
}

.flat-pagination .pagination-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-right: 15px;
}

.flat-pagination .pagination-subtitle {
    font-size: 13px;
    color: #666;
    margin-bottom: 15px;
    text-align: center;
}

.flat-pagination .page-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 1px solid #e1e5e9;
    background: #fff;
    color: #495057;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
}

.flat-pagination .page-btn:hover {
    background: #f8f9fa;
    border-color: #ced4da;
    color: #495057;
    text-decoration: none;
    transform: translateY(-1px);
}

.flat-pagination .page-btn.active {
    background: #28a745;
    border-color: #28a745;
    color: white;
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
}

.flat-pagination .page-btn.disabled {
    background: #f8f9fa;
    border-color: #e9ecef;
    color: #adb5bd;
    cursor: not-allowed;
    opacity: 0.6;
}

.flat-pagination .page-btn.disabled:hover {
    transform: none;
    background: #f8f9fa;
    border-color: #e9ecef;
}

.flat-pagination .nav-btn {
    font-size: 12px;
    width: 32px;
    height: 32px;
}

.pagination-info {
    font-size: 14px;
    color: #6c757d;
    text-align: center;
    margin-bottom: 10px;
}

/* Style for price input with peso sign */
.price-input-group {
    position: relative;
}
.price-input-group input {
    padding-left: 25px;
}
.price-input-group::before {
    content: '₱';
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #495057;
    font-weight: bold;
}

/* Style for date input */
.date-input-group {
    position: relative;
}
.date-input-group input {
    padding-left: 10px;
}

/* Scanner Modal Styles */
.scanner-modal {
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(5px);
}
.scanner-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    max-width: 800px;
    width: 90%;
}
.camera-view {
    position: relative;
    width: 100%;
    height: 400px;
    background: #000;
    border-radius: 10px;
    overflow: hidden;
}
#scanner-container {
    width: 100%;
    height: 100%;
    position: relative;
}
#scanner-container video,
#scanner-container canvas {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
    position: absolute;
    top: 0;
    left: 0;
}
.scan-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    border: 2px solid #28a745;
    width: 300px;
    height: 100px;
    border-radius: 10px;
    background: rgba(40, 167, 69, 0.1);
    z-index: 10;
}
.scan-line {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background: #28a745;
    animation: scan 2s linear infinite;
}
@keyframes scan {
    0% { transform: translateY(-50px); opacity: 0; }
    50% { opacity: 1; }
    100% { transform: translateY(50px); opacity: 0; }
}
.beep-indicator {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    z-index: 1000;
    display: none;
    animation: pulse 0.5s ease-in-out;
}
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}
.scan-result-item {
    border-left: 4px solid #28a745;
    background: #f8f9fa;
    margin-bottom: 10px;
    padding: 15px;
    border-radius: 5px;
}
.scan-stats {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

/* Barcode Modal Styles */
.barcode-modal {
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(5px);
}
.barcode-modal-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    max-width: 600px;
    width: 90%;
}
.barcode-modal-image {
    max-width: 100%;
    height: auto;
    padding: 20px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
}
.barcode-modal-text {
    font-family: 'Courier New', monospace;
    font-size: 16px;
    font-weight: bold;
    color: #495057;
    text-align: center;
    margin-top: 10px;
}

.regenerate-barcode-btn {
    background: none;
    border: none;
    color: #6c757d;
    font-size: 12px;
    padding: 2px 4px;
    border-radius: 4px;
    transition: all 0.2s;
}

.regenerate-barcode-btn:hover {
    background: #e9ecef;
    color: #495057;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table-container {
        min-height: 350px;
    }
    
    .barcode-container {
        max-width: 100px;
    }
    
    .barcode-text {
        font-size: 9px;
    }
    
    .table td, .table th {
        padding: 8px 10px;
        font-size: 12px;
    }
    
    .flat-pagination .page-btn {
        width: 32px;
        height: 32px;
        font-size: 13px;
    }
    
    .flat-pagination .pagination-title {
        font-size: 14px;
        margin-right: 10px;
    }
}
</style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <?php include 'includes/sidebar.php'; ?>
    <div class="col-md-10 p-4">
        <h2 class="mb-4">
            Inventory Management
            <small class="text-muted fs-6">(<?= $totalItems ?> total items)</small>
        </h2>
        
        <!-- Success/Error Messages -->
        <div id="alertMessage" class="alert alert-success alert-dismissible fade" role="alert">
            <span id="alertText"></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        
        <!-- Beep Indicator -->
        <div id="beepIndicator" class="beep-indicator">
        </div>
        
        <!-- Add Item Form -->
        <div class="card p-3 mb-3">
            <form id="addForm" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="name" class="form-control" placeholder="Item Name" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="stock" class="form-control" placeholder="Stock" min="0" required>
                </div>
                <div class="col-md-2">
                    <div class="price-input-group">
                        <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" min="0" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="date-input-group">
                        <input type="date" name="expiration_date" class="form-control" placeholder="Expiration Date">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100" id="addButton">
                        <span class="loading spinner-border spinner-border-sm" role="status"></span>
                        <i class="bi bi-plus-circle"></i> Add Product
                    </button>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-primary w-100" id="scannerBtn">
                        <i class="bi bi-upc-scan"></i> Add Stock
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Search Controls -->
        <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap">
            <form method="get" class="d-flex flex-grow-1 me-3">
                <div class="input-group" style="max-width: 400px;">
                    <input type="text" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" class="form-control" placeholder="Search by name or barcode">
                    <button class="btn btn-primary"><i class="bi bi-search"></i></button>
                </div>
                <?php if(isset($_GET['page'])): ?>
                    <input type="hidden" name="page" value="<?= $_GET['page'] ?>">
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Inventory Table -->
        <div class="card">
            <div class="table-container">
                <table id="itemsTable" class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 8%;">ID</th>
                            <th style="width: 25%;">Name</th>
                            <th style="width: 12%;">Stock</th>
                            <th style="width: 12%;">Price</th>
                            <th style="width: 15%;">Expiration</th>
                            <th style="width: 20%;">Barcode</th>
                            <th style="width: 8%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(empty($items)): ?>
                        <tr id="noItemsRow">
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox display-4 d-block mb-3 text-muted"></i>
                                No items found
                                <?php if(isset($_GET['search']) && !empty($_GET['search'])): ?>
                                    <br><small>Try adjusting your search terms</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $today = new DateTime();
                        $warningDate = (new DateTime())->modify('+7 days');
                        ?>
                        <?php foreach($items as $item): ?>
                            <?php
                            $expClass = 'expiration-date-valid';
                            $expText = $item['expiration_date'] ? date('M d, Y', strtotime($item['expiration_date'])) : 'N/A';
                            if ($item['expiration_date']) {
                                $expDate = new DateTime($item['expiration_date']);
                                if ($expDate < $today) {
                                    $expClass = 'expiration-date-expired';
                                } elseif ($expDate <= $warningDate) {
                                    $expClass = 'expiration-date-soon';
                                }
                            }
                            ?>
                            <tr data-id="<?= $item['id'] ?>">
                                <td class="fw-bold"><?= $item['id'] ?></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= $item['stock'] <= 5 ? '<span class="badge bg-danger">'.$item['stock'].'</span>' : '<span class="badge bg-success">'.$item['stock'].'</span>' ?></td>
                                <td><strong>₱<?= number_format($item['price'], 2) ?></strong></td>
                                <td><span class="badge <?= $expClass ?>"><?= $expText ?></span></td>
                                <td>
                                    <div class="barcode-container">
                                        <div class="barcode-info">
                                            <div class="barcode-text"><?= $item['barcode'] ?></div>
                                            <?php
                                            try {
                                                // Always use Code 128 for consistency and better scanning
                                                $barcodeSVG = $generator->getBarcode($item['barcode'], $generator::TYPE_CODE_128, 2, 50);
                                                echo '<img class="barcode barcode-clickable" src="data:image/svg+xml;base64,' . base64_encode($barcodeSVG) . '" alt="barcode" onclick="showBarcodeModal(\''.htmlspecialchars($item['barcode']).'\', \''.base64_encode($barcodeSVG).'\')">';
                                            } catch (Exception $e) {
                                                echo '<span class="text-danger small">Error generating barcode</span>';
                                            }
                                            ?>
                                            <button type="button" class="regenerate-barcode-btn" onclick="regenerateBarcode(<?= $item['id'] ?>)" title="Generate new barcode">
                                                <i class="bi bi-arrow-clockwise"></i> New
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="?delete=<?= $item['id'] ?><?= isset($_GET['page']) ? '&page='.$_GET['page'] : '' ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Delete this item?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Flat Pagination -->
        <?php if($totalPages > 1): ?>
        <div class="flat-pagination-wrapper">
            <div class="pagination-info">
                Showing <?= min($offset + 1, $totalItems) ?> to <?= min($offset + $itemsPerPage, $totalItems) ?> of <?= $totalItems ?> items
                <?php if(isset($_GET['search']) && !empty($_GET['search'])): ?>
                    <span class="badge bg-info ms-2">Filtered</span>
                <?php endif; ?>
            </div>
            
            <div class="flat-pagination">
                <div class="pagination-title">Flat Pagination Round</div>
                
                <!-- Previous Button -->
                <?php if($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?><?= isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>" 
                       class="page-btn nav-btn">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="page-btn nav-btn disabled">
                        <i class="bi bi-chevron-left"></i>
                    </span>
                <?php endif; ?>
                
                <!-- Page Numbers (show max 5 pages) -->
                <?php
                $maxVisiblePages = 5;
                $startPage = max(1, $currentPage - floor($maxVisiblePages / 2));
                $endPage = min($totalPages, $startPage + $maxVisiblePages - 1);
                
                // Adjust start page if we're near the end
                if ($endPage - $startPage + 1 < $maxVisiblePages) {
                    $startPage = max(1, $endPage - $maxVisiblePages + 1);
                }
                
                for($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="?page=<?= $i ?><?= isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>" 
                       class="page-btn <?= $i == $currentPage ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <!-- Next Button -->
                <?php if($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?><?= isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>" 
                       class="page-btn nav-btn">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-btn nav-btn disabled">
                        <i class="bi bi-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
  </div>
</div>

<!-- Scanner Modal -->
<div id="scannerModal" class="modal fade scanner-modal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content scanner-container">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-upc-scan"></i> Barcode/RFID Scanner - Stock Management
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Scanner Controls -->
                <div class="d-flex justify-content-between mb-3">
                    <div class="btn-group">
                        <button id="startScanBtn" class="btn btn-success">
                            <i class="bi bi-play-fill"></i> Start Camera
                        </button>
                        <button id="stopScanBtn" class="btn btn-danger" disabled>
                            <i class="bi bi-stop-fill"></i> Stop
                        </button>
                        <button id="switchCameraBtn" class="btn btn-info" disabled>
                            <i class="bi bi-camera-reels"></i> Switch Camera
                        </button>
                    </div>
                    <div class="scan-stats">
                        <small>Scanned: <span id="scanCount">0</span> | Found: <span id="foundCount">0</span></small>
                    </div>
                </div>

                <!-- Camera View -->
                <div class="camera-view mb-3">
                    <div id="scanner-container"></div>
                    <div class="scan-overlay">
                        <div class="scan-line"></div>
                    </div>
                    <div class="position-absolute top-0 start-0 p-3">
                        <span class="badge bg-primary">Point barcode at camera</span>
                        <br>
                        <small class="badge bg-info mt-1">
                            <i class="bi bi-lightbulb"></i> Good lighting helps!
                        </small>
                    </div>
                    <div class="position-absolute top-0 end-0 p-3">
                        <span class="badge bg-warning" id="cameraType">Laptop Camera</span>
                    </div>
                </div>

                <!-- Manual Input -->
                <div class="card bg-light p-3">
                    <h6><i class="bi bi-keyboard"></i> Manual Entry</h6>
                    <div class="input-group">
                        <input type="text" id="manualBarcode" class="form-control" placeholder="Enter barcode manually" autocomplete="off">
                        <button id="manualScanBtn" class="btn btn-secondary">
                            <i class="bi bi-search"></i> Lookup
                        </button>
                    </div>
                    <small class="text-muted mt-2">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Scanner Tips:</strong> Hold barcode 6-8 inches away, ensure good lighting, try different angles if not detecting.
                    </small>
                </div>

                <!-- Scan Results -->
                <div id="scanResults" class="mt-3" style="max-height: 200px; overflow-y: auto;">
                    <!-- Results will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="clearResultsBtn" class="btn btn-warning">
                    <i class="bi bi-arrow-clockwise"></i> Clear Results
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Barcode View Modal -->
<div id="barcodeModal" class="modal fade barcode-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content barcode-modal-container">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-upc"></i> Barcode
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="barcodeModalImage" class="barcode-modal-image" src="" alt="Large Barcode">
                <div id="barcodeModalText" class="barcode-modal-text"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let scanner = null;
    let isScanning = false;
    let scanStats = { scanned: 0, found: 0 };
    let lastScanTime = 0;
    let scanResults = [];
    let currentFacingMode = 'user'; // Start with front camera for laptops
    let availableCameras = [];

    // Function to show barcode modal
    window.showBarcodeModal = function(barcode, base64Image) {
        $('#barcodeModalImage').attr('src', 'data:image/svg+xml;base64,' + base64Image);
        $('#barcodeModalText').text(barcode);
        $('#barcodeModal').modal('show');
    };

    // Enhanced Add Form - handle scanned barcode and expiration date
    $('#addForm').on('submit', function(e){
        e.preventDefault();
       
        // Client-side validation for expiration date
        const expirationDate = $('input[name="expiration_date"]').val();
        if (expirationDate && new Date(expirationDate) < new Date(new Date().setHours(0,0,0,0))) {
            showAlert('❌ Expiration date must be today or in the future', 'danger');
            return;
        }
       
        // Show loading state
        $('#addButton .loading').show();
        $('#addButton').prop('disabled', true);
       
        // Get form data and include scanned barcode if available
        var formData = $(this).serialize() + '&action=add_item';
        const scannedBarcode = $(this).data('scanned-barcode');
        if (scannedBarcode) {
            formData += '&barcode=' + encodeURIComponent(scannedBarcode);
            // Remove the temporary placeholder
            $('input[name="name"]').attr('placeholder', 'Item Name');
            $(this).removeData('scanned-barcode');
        }
       
        $.ajax({
            url: 'inventory.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    $('#addForm')[0].reset();
                    
                    if (scannedBarcode) {
                        showAlert(`✅ New item added successfully with scanned barcode: ${scannedBarcode}`, 'success');
                    } else {
                        showAlert('✅ Item added successfully with generated barcode: ' + response.barcode, 'success');
                    }
                    
                    // Dynamically add the new item to the table
                    const tableBody = $('#itemsTable tbody');
                    const noItemsRow = $('#noItemsRow');
                    
                    // Remove "No items found" row if it exists
                    if (noItemsRow.length) {
                        noItemsRow.remove();
                    }
                    
                    // Determine expiration date class
                    let expClass = 'expiration-date-valid';
                    if (response.expiration_date !== 'N/A') {
                        const expDate = new Date(response.expiration_date);
                        const today = new Date();
                        const warningDate = new Date();
                        warningDate.setDate(today.getDate() + 7);
                        if (expDate < today) {
                            expClass = 'expiration-date-expired';
                        } else if (expDate <= warningDate) {
                            expClass = 'expiration-date-soon';
                        }
                    }
                    
                    // Create new table row
                    const stockBadge = response.stock <= 5 
                        ? `<span class="badge bg-danger">${response.stock}</span>`
                        : `<span class="badge bg-success">${response.stock}</span>`;
                    
                    const barcodeHTML = response.barcode_img 
                        ? `
                            <div class="barcode-container">
                                <div class="barcode-info">
                                    <div class="barcode-text">${response.barcode}</div>
                                    <img class="barcode barcode-clickable" src="${response.barcode_img}" alt="barcode" onclick="showBarcodeModal('${response.barcode}', '${response.barcode_img.split(',')[1]}')">
                                    <button type="button" class="regenerate-barcode-btn" onclick="regenerateBarcode(${response.id})" title="Generate new barcode">
                                        <i class="bi bi-arrow-clockwise"></i> New
                                    </button>
                                </div>
                            </div>
                        `
                        : `
                            <div class="barcode-container">
                                <div class="barcode-info">
                                    <div class="barcode-text">${response.barcode}</div>
                                    <span class="text-warning small">Error generating barcode image</span>
                                    <button type="button" class="regenerate-barcode-btn" onclick="regenerateBarcode(${response.id})" title="Generate new barcode">
                                        <i class="bi bi-arrow-clockwise"></i> New
                                    </button>
                                </div>
                            </div>
                        `;
                    
                    const newRow = `
                        <tr data-id="${response.id}">
                            <td class="fw-bold">${response.id}</td>
                            <td>${response.name}</td>
                            <td>${stockBadge}</td>
                            <td><strong>₱${response.price}</strong></td>
                            <td><span class="badge ${expClass}">${response.expiration_date}</span></td>
                            <td>${barcodeHTML}</td>
                            <td>
                                <a href="?delete=${response.id}${window.location.search}" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Delete this item?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    `;
                    
                    // Prepend the new row to the table (since items are ordered by ID DESC)
                    tableBody.prepend(newRow);
                    
                    // Update total items count in the header
                    const totalItemsElement = $('.text-muted.fs-6');
                    const currentTotal = parseInt(totalItemsElement.text().match(/\d+/)[0]);
                    totalItemsElement.text(`(${currentTotal + 1} total items)`);
                    
                    // If on the first page, ensure only 4 items are shown (due to pagination)
                    if (window.location.search.includes('page=1') || !window.location.search.includes('page')) {
                        const rows = tableBody.find('tr');
                        if (rows.length > 4) {
                            rows.slice(4).remove();
                        }
                    }
                    
                } else {
                    showAlert('❌ Error: ' + (response.error || 'Failed to add item'), 'danger');
                }
            },
            error: function(xhr, status, error) {
                showAlert('❌ Error: Failed to add item. Check console for details.', 'danger');
            },
            complete: function() {
                $('#addButton .loading').hide();
                $('#addButton').prop('disabled', false);
            }
        });
    });

    // Function to regenerate barcode
    window.regenerateBarcode = function(itemId) {
        if (!confirm('Generate a new barcode for this item? The old barcode will no longer work.')) {
            return;
        }

        $.ajax({
            url: 'inventory.php',
            type: 'POST',
            data: {
                action: 'generate_barcode',
                id: itemId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update the barcode display in the table
                    const row = $(`tr[data-id="${itemId}"]`);
                    const barcodeCell = row.find('td:nth-child(6)');
                    
                    let barcodeHTML = '';
                    if (response.barcode_img) {
                        barcodeHTML = `
                            <div class="barcode-container">
                                <div class="barcode-info">
                                    <div class="barcode-text">${response.barcode}</div>
                                    <img class="barcode barcode-clickable" src="${response.barcode_img}" alt="barcode" onclick="showBarcodeModal('${response.barcode}', '${response.barcode_img.split(',')[1]}')">
                                    <button type="button" class="regenerate-barcode-btn" onclick="regenerateBarcode(${itemId})" title="Generate new barcode">
                                        <i class="bi bi-arrow-clockwise"></i> New
                                    </button>
                                </div>
                            </div>
                        `;
                    } else {
                        barcodeHTML = `
                            <div class="barcode-container">
                                <div class="barcode-info">
                                    <div class="barcode-text">${response.barcode}</div>
                                    <span class="text-warning small">Error generating barcode image</span>
                                    <button type="button" class="regenerate-barcode-btn" onclick="regenerateBarcode(${itemId})" title="Generate new barcode">
                                        <i class="bi bi-arrow-clockwise"></i> New
                                    </button>
                                </div>
                            </div>
                        `;
                    }
                    
                    barcodeCell.html(barcodeHTML);
                    showAlert('✅ New barcode generated successfully: ' + response.barcode, 'success');
                } else {
                    showAlert('❌ Failed to generate new barcode: ' + response.error, 'danger');
                }
            },
            error: function() {
                showAlert('❌ Connection error while generating new barcode', 'danger');
            }
        });
    };

    // Open Scanner Modal
    $('#scannerBtn').on('click', function() {
        $('#scannerModal').modal('show');
    });

    // Scanner Modal Events
    $('#scannerModal').on('shown.bs.modal', function() {
        // Modal is fully shown, scanner can be initialized
    });

    $('#scannerModal').on('hidden.bs.modal', function() {
        // Stop scanner when modal is closed
        if (isScanning) {
            stopBarcodeScanner();
        }
    });

    // Start Scanner
    $('#startScanBtn').on('click', function() {
        startBarcodeScanner();
    });

    // Stop Scanner
    $('#stopScanBtn').on('click', function() {
        stopBarcodeScanner();
    });

    // Switch Camera
    $('#switchCameraBtn').on('click', function() {
        if (isScanning) {
            switchCamera();
        }
    });

    // Manual Barcode Entry
    $('#manualScanBtn').on('click', function() {
        const barcode = $('#manualBarcode').val().trim();
        if (barcode) {
            processBarcode(barcode, true);
            $('#manualBarcode').val('');
        } else {
            showAlert('Please enter a barcode', 'warning');
        }
    });

    $('#manualBarcode').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            $('#manualScanBtn').click();
        }
    });

    // Clear Results
    $('#clearResultsBtn').on('click', function() {
        scanResults = [];
        scanStats = { scanned: 0, found: 0 };
        updateScanDisplay();
        updateScanStats();
    });

    function startBarcodeScanner() {
        if (isScanning) return;
        
        $('#startScanBtn').prop('disabled', true);
        $('#stopScanBtn').prop('disabled', false);
        $('#switchCameraBtn').prop('disabled', false);
        
        // Improved scanner configuration for better barcode detection
        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#scanner-container'),
                constraints: {
                    width: { min: 640, ideal: 1280, max: 1920 },
                    height: { min: 480, ideal: 720, max: 1080 },
                    facingMode: currentFacingMode,
                    focusMode: 'continuous',
                    exposureMode: 'continuous'
                }
            },
            decoder: {
                readers: [
                    "code_128_reader", // Primary reader for our barcodes
                    "ean_reader", 
                    "ean_8_reader",
                    "code_39_reader",
                    "upc_reader",
                    "upc_e_reader"
                ],
                debug: {
                    showCanvas: false,
                    showPatches: false,
                    showFoundPatches: false,
                    showSkeleton: false,
                    showLabels: false,
                    showPatchLabels: false,
                    showRemainingPatchLabels: false,
                    boxFromPatches: {
                        showTransformed: false,
                        showTransformedBox: false,
                        showBB: false
                    }
                },
                multiple: false // Only detect one barcode at a time
            },
            locate: true,
            locator: {
                patchSize: "medium",
                halfSample: false, // Full resolution for better detection
                showCanvas: false,
                showPatches: false,
                showFoundPatches: false,
                showSkeleton: false,
                showLabels: false,
                showPatchLabels: false,
                showRemainingPatchLabels: false
            },
            frequency: 5, // Reduced frequency for stability but still responsive
            area: {
                top: "25%",    // Larger scanning area
                right: "25%", 
                left: "25%",
                bottom: "25%"
            },
            singleChannel: false // Use full color processing
        }, function(err) {
            if (err) {
                console.error('Scanner error:', err);
                showAlert('Failed to start camera: ' + err.message, 'danger');
                $('#startScanBtn').prop('disabled', false);
                $('#stopScanBtn').prop('disabled', true);
                $('#switchCameraBtn').prop('disabled', true);
                return;
            }
            
            // Ensure proper video display
            setTimeout(() => {
                const video = document.querySelector('#scanner-container video');
                const canvas = document.querySelector('#scanner-container canvas');
                if (video) {
                    video.style.width = '100%';
                    video.style.height = '100%';
                    video.style.objectFit = 'cover';
                }
                if (canvas) {
                    canvas.style.width = '100%';
                    canvas.style.height = '100%';
                    canvas.style.objectFit = 'cover';
                }
            }, 500);
            
            Quagga.start();
            isScanning = true;
            showAlert('Scanner started - point camera at barcode', 'success');
        });

        // Enhanced barcode detection with better filtering
        Quagga.onDetected(function(result) {
            const code = result.codeResult.code;
            const now = Date.now();
            
            // Improved validation for better detection
            if (!isValidBarcode(code, result) || now - lastScanTime < 1500) {
                return;
            }
            
            lastScanTime = now;
            processBarcode(code, false);
            playBeep();
            showBeepIndicator();
        });
    }

    function isValidBarcode(code, result) {
        // Basic validation for barcode quality and format
        if (!code || code.length < 6) return false;
        
        // Check barcode quality based on scan result
        if (result && result.codeResult) {
            // Check if the barcode has good quality indicators
            const quality = result.codeResult.decodedCodes;
            if (quality && quality.length > 0) {
                // Ensure we have a reasonable confidence in the scan
                const hasGoodQuality = quality.some(decode => 
                    decode.error !== undefined && decode.error < 0.5
                );
                if (!hasGoodQuality) return false;
            }
        }
        
        // Validate barcode content
        // Accept numeric barcodes (our generated ones)
        const isNumeric = /^\d+$/.test(code);
        
        // Accept alphanumeric barcodes (Code 128 format)
        const isAlphaNumeric = /^[A-Z0-9]+$/i.test(code);
        
        // Reject obviously bad scans (too many repeated characters)
        const uniqueChars = new Set(code).size;
        const tooRepetitive = uniqueChars < Math.max(2, code.length / 4);
        
        if (tooRepetitive) return false;
        
        // Accept if it matches our expected formats
        return isNumeric || isAlphaNumeric;
    }

    function stopBarcodeScanner() {
        if (!isScanning) return;
        
        Quagga.stop();
        isScanning = false;
        $('#startScanBtn').prop('disabled', false);
        $('#stopScanBtn').prop('disabled', true);
        $('#switchCameraBtn').prop('disabled', true);
        showAlert('Scanner stopped', 'info');
    }

    function switchCamera() {
        if (!isScanning) return;
        
        // Toggle between front and back camera
        currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
        
        // Restart scanner with new camera
        stopBarcodeScanner();
        setTimeout(() => {
            startBarcodeScanner();
        }, 500);
        
        const cameraName = currentFacingMode === 'user' ? 'Front Camera' : 'Back Camera';
        $('#cameraType').text(cameraName);
        showAlert(`Switched to ${cameraName}`, 'info');
    }

    function processBarcode(barcode, isManual) {
        scanStats.scanned++;
        updateScanStats();
        
        showAlert('Checking barcode in database...', 'info');
        
        $.ajax({
            url: 'inventory.php',
            type: 'POST',
            data: {
                action: 'lookup_item',
                barcode: barcode
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.found) {
                        scanStats.found++;
                        handleFoundItem(response.item, barcode, isManual);
                    } else {
                        handleNotFoundItem(barcode, isManual);
                    }
                } else {
                    showAlert('Database error: ' + response.error, 'danger');
                    addToScanResults(barcode, null, isManual, false, 'Database Error');
                }
                updateScanStats();
                updateScanDisplay();
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showAlert('Connection error while checking database', 'danger');
                addToScanResults(barcode, null, isManual, false, 'Connection Error');
                updateScanStats();
                updateScanDisplay();
            },
            timeout: 10000
        });
    }

    function handleFoundItem(item, barcode, isManual) {
        const stockStatus = item.stock <= 5 ? 'LOW STOCK' : 'IN STOCK';
        const alertType = item.stock <= 5 ? 'warning' : 'success';
        const expText = item.expiration_date !== 'N/A' ? ` | Expires: ${item.expiration_date}` : '';
        
        showAlert(`✅ FOUND: ${item.name} | Stock: ${item.stock} | Price: ₱${parseFloat(item.price).toFixed(2)}${expText} | Status: ${stockStatus}`, alertType);
        
        setTimeout(() => {
            const currentStock = item.stock;
            const action = confirm(`Item Found in Database!\n\n📦 ${item.name}\n💰 Price: ₱${parseFloat(item.price).toFixed(2)}\n📊 Current Stock: ${currentStock}\n📅 Expiration: ${item.expiration_date}\n\n🔄 Would you like to update the stock quantity?`);
            
            if (action) {
                const newStock = prompt(`Enter new stock quantity for "${item.name}":`, currentStock);
                
                if (newStock !== null && !isNaN(newStock) && newStock !== currentStock.toString()) {
                    updateItemStock(item.id, parseInt(newStock), item, barcode, isManual);
                } else {
                    addToScanResults(barcode, item, isManual, true, 'Viewed Only');
                }
            } else {
                addToScanResults(barcode, item, isManual, true, 'Viewed Only');
            }
        }, 1500);
    }

    function handleNotFoundItem(barcode, isManual) {
        showAlert(`❌ NOT FOUND: Barcode "${barcode}" does not exist in database`, 'danger');
        addToScanResults(barcode, null, isManual, false, 'Not Found');
        
        setTimeout(() => {
            const shouldAdd = confirm(`Barcode Not Found!\n\n🔍 Barcode: ${barcode}\n❌ This item is not in your inventory database.\n\n➕ Would you like to add it as a new product?`);
            
            if (shouldAdd) {
                $('#scannerModal').modal('hide');
                
                setTimeout(() => {
                    const nameField = $('input[name="name"]');
                    nameField.focus();
                    nameField.attr('placeholder', `New item with barcode: ${barcode}`);
                    
                    showAlert(`Ready to add new item with scanned barcode: ${barcode}`, 'info');
                    $('#addForm').data('scanned-barcode', barcode);
                }, 500);
            } else {
                updateScanDisplay();
            }
        }, 2000);
    }

    function addToScanResults(barcode, item, isManual, found, action) {
        scanResults.unshift({
            barcode: barcode,
            item: item,
            timestamp: new Date(),
            isManual: isManual,
            found: found,
            action: action
        });
    }

    function updateItemStock(itemId, newStock, item, barcode, isManual) {
        showAlert('Updating stock in database...', 'info');
        
        $.ajax({
            url: 'inventory.php',
            type: 'POST',
            data: {
                action: 'update_stock',
                id: itemId,
                stock: newStock
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const row = $(`tr[data-id="${itemId}"]`);
                    if (row.length) {
                        const stockCell = row.find('td:nth-child(3)');
                        const stockDisplay = newStock <= 5 ? 
                            `<span class="badge bg-danger">${newStock}</span>` : 
                            `<span class="badge bg-success">${newStock}</span>`;
                        stockCell.html(stockDisplay);
                    }
                    
                    const oldStock = item.stock;
                    const difference = newStock - oldStock;
                    const changeText = difference > 0 ? `+${difference}` : `${difference}`;
                    
                    showAlert(`✅ STOCK UPDATED: ${item.name} | ${oldStock} → ${newStock} (${changeText})`, 'success');
                    addToScanResults(barcode, {...item, stock: newStock}, isManual, true, `Updated: ${oldStock}→${newStock}`);
                    updateScanDisplay();
                } else {
                    showAlert('❌ Failed to update stock: ' + response.error, 'danger');
                    addToScanResults(barcode, item, isManual, true, 'Update Failed');
                    updateScanDisplay();
                }
            },
            error: function() {
                showAlert('❌ Connection error while updating stock', 'danger');
                addToScanResults(barcode, item, isManual, true, 'Update Error');
                updateScanDisplay();
            }
        });
    }

    function updateScanDisplay() {
        const container = $('#scanResults');
        
        if (scanResults.length === 0) {
            container.html('<div class="text-center text-muted p-3">No scans yet. Start scanning to see results.</div>');
            return;
        }

        const html = scanResults.slice(0, 8).map(scan => {
            const statusColor = scan.found ? 'success' : 'danger';
            const statusIcon = scan.found ? 'check-circle-fill' : 'x-circle-fill';
            const inputIcon = scan.isManual ? 'keyboard' : 'camera';
            const inputColor = scan.isManual ? 'warning' : 'primary';
            
            return `
                <div class="scan-result-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <i class="bi bi-${statusIcon} text-${statusColor} me-2"></i>
                                <strong>${scan.found ? scan.item.name : 'Unknown Item'}</strong>
                            </div>
                            <div class="d-flex align-items-center">
                                <code class="bg-light px-2 py-1 rounded me-2">${scan.barcode}</code>
                                <i class="bi bi-${inputIcon} text-${inputColor}" title="${scan.isManual ? 'Manual Entry' : 'Camera Scan'}"></i>
                            </div>
                            ${scan.found ? `
                                <div class="mt-2">
                                    <small class="text-muted">
                                        💰 ₱${parseFloat(scan.item.price).toFixed(2)} | 
                                        📦 Stock: ${scan.item.stock} |
                                        📅 Expires: ${scan.item.expiration_date} |
                                        ${scan.item.stock <= 5 ? '<span class="text-danger">⚠️ LOW STOCK</span>' : '<span class="text-success">✅ In Stock</span>'}
                                    </small>
                                </div>
                            ` : ''}
                        </div>
                        <div class="text-end ms-3">
                            <span class="badge bg-${statusColor} mb-1">
                                ${scan.action}
                            </span>
                            <br>
                            <small class="text-muted">${scan.timestamp.toLocaleTimeString()}</small>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        container.html(html);
    }

    function updateScanStats() {
        $('#scanCount').text(scanStats.scanned);
        $('#foundCount').text(scanStats.found);
    }

    function playBeep() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'square';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.1);
        } catch (e) {
            console.log('Audio not supported');
        }
    }

    function showBeepIndicator() {
        $('#beepIndicator').show();
        setTimeout(() => {
            $('#beepIndicator').hide();
        }, 1000);
    }
   
    function showAlert(message, type) {
        $('#alertMessage').removeClass('alert-success alert-danger alert-warning alert-info').addClass('alert-' + type);
        $('#alertText').text(message);
        $('#alertMessage').addClass('show').fadeIn();
       
        setTimeout(function() {
            $('#alertMessage').removeClass('show').fadeOut();
        }, 5000);
    }

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        if ($('#scannerModal').hasClass('show')) {
            if (e.ctrlKey) {
                switch(e.key.toLowerCase()) {
                    case 's':
                        e.preventDefault();
                        if (!isScanning) startBarcodeScanner();
                        break;
                    case 'q':
                        e.preventDefault();
                        if (isScanning) stopBarcodeScanner();
                        break;
                }
            }
            if (e.key === 'Escape' && isScanning) {
                stopBarcodeScanner();
            }
        }
    });

    $('#scannerModal').on('shown.bs.modal', function() {
        $('#manualBarcode').focus();
    });
});
</script>
</body>
</html>