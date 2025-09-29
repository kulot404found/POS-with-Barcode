<?php
require_once 'config.php';
$id = $_POST['id'] ?? '';
$stock = $_POST['stock'] ?? '';
$stmt = $db->prepare("UPDATE inventory SET stock = ? WHERE id = ?");
$stmt->execute([$stock, $id]);
echo json_encode(['success' => true]);
?>