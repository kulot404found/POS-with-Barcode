<?php
require_once 'config.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role']!=='admin'){
    header("Location: login.php"); exit;
}

// Add / Delete User
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['username'])){
    $stmt=$db->prepare("INSERT INTO users (username,password,role) VALUES (?,?,?)");
    $stmt->execute([$_POST['username'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['role']]);
    header("Location: users.php"); exit;
}
if(isset($_GET['delete'])){
    $stmt=$db->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: users.php"); exit;
}

$users=$db->query("SELECT * FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Users - POS System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { background: #f4f6f9; }
    .sidebar {
        background: #4e54c8;
        color: white;
        min-height: 100vh;
    }
    .sidebar a {
        color: white;
        text-decoration: none;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 5px;
        display: block;
        transition: 0.3s;
    }
    .sidebar a:hover {
        background-color: #3b3f9c;
    }
    .card-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    }
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->

            <?php include 'includes/sidebar.php'; ?>


        <!-- Main Content -->
        <div class="col-md-10 p-4">
            <h2 class="mb-4 text-primary"><i class="bi bi-people"></i> User Accounts</h2>

            <!-- Add User Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">Add User</div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="username" class="form-control" placeholder="Username" required>
                        </div>
                        <div class="col-md-4">
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                        </div>
                        <div class="col-md-3">
                            <select name="role" class="form-select" required>
                                <option value="admin">Admin</option>
                                <option value="cashier">Cashier</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">Users List</div>
                <div class="card-body table-responsive">
                    <table class="table table-striped table-sm align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($u['role']) ?></span></td>
                                <td>
                                    <a href="users.php?delete=<?= $u['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
