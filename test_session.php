<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

echo "Session set.<br>";
echo "User ID: " . $_SESSION['user_id'] . "<br>";
echo "Role: " . $_SESSION['role'] . "<br>";
