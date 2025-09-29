<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$host = "localhost";
$dbname = "tiptop_inventory";
$user = "root";
$pass = "";

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    die("Database connection failed: " . $e->getMessage());
}

// Escape HTML helper
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Require login function
function require_login($role = null){
    if(!isset($_SESSION['user_id'])){
        header("Location: login.php");
        exit;
    }
    if($role && $_SESSION['role'] !== $role){
        die("❌ Access denied.");
    }
}
?>
