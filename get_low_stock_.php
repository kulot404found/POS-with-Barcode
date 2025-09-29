<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // EXACTLY the same rule as Reports: stock < 10
    $count = (int) $db->query("SELECT COUNT(*) FROM inventory WHERE stock < 10")->fetchColumn();
    echo json_encode(['count' => $count]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'message' => $e->getMessage()]);
}
