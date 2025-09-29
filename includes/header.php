<?php
if(session_status() == PHP_SESSION_NONE){ session_start(); }
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?? 'POS System' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
body{background:#f4f6f9;} .card{border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,0.1);}
.sidebar{background:#4e54c8;color:white;min-height:100vh;} .sidebar a{color:white;text-decoration:none;display:block;padding:10px;border-radius:5px;}
.sidebar a:hover{background:#3b3f9c;}
</style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
