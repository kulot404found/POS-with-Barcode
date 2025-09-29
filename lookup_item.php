<?php
require_once 'config.php';
$barcode = $_GET['barcode'] ?? '';
$stmt = $db->prepare("SELECT * FROM inventory WHERE barcode = ?");
$stmt->execute([$barcode]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($item ?: null);
?>