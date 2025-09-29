<?php
session_start();
$error = "";
// Hardcoded accounts for testing
$accounts = [
    "admin" => ["password" => "admin123", "role" => "admin"],
    "cashier" => ["password" => "cashier123", "role" => "cashier"]
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    if (isset($accounts[$username]) && $accounts[$username]['password'] === $password) {
        $_SESSION['user_id'] = 1; // just for testing
        $_SESSION['username'] = $username; // Store username for display
        $_SESSION['role'] = $accounts[$username]['role'];
        if ($_SESSION['role'] === 'admin') {
            header("Location: index.php");
        } else {
            header("Location: pos.php");
        }
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            background-size: 200% 200%;
            animation: gradientShift 10s ease infinite;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Background decorative elements */
        .bg-decoration {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .bg-decoration::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(-50px) translateY(-50px); }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
            border-radius: 24px 24px 0 0;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: white;
            font-size: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .login-title {
            color: #2d3748;
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: #718096;
            font-size: 1rem;
            margin-bottom: 0;
        }

        .form-floating {
            margin-bottom: 20px;
            position: relative;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px 16px 8px 16px;
            height: 60px;
            background: rgba(248, 250, 252, 0.8);
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .form-floating label {
            color: #718096;
            font-weight: 500;
            padding: 16px;
        }

        .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            z-index: 3;
        }

        .password-toggle {
            cursor: pointer;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-weight: 600;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            background: rgba(248, 113, 113, 0.1);
            border-left: 4px solid #ef4444;
            color: #991b1b;
            font-weight: 500;
        }

        .demo-accounts {
            background: rgba(59, 130, 246, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid #3b82f6;
        }

        .demo-title {
            color: #1e40af;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .demo-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(59, 130, 246, 0.1);
        }

        .demo-account:last-child {
            border-bottom: none;
        }

        .account-info {
            display: flex;
            flex-direction: column;
        }

        .account-role {
            font-weight: 600;
            color: #1e40af;
        }

        .account-creds {
            font-size: 0.85rem;
            color: #64748b;
            font-family: 'Courier New', monospace;
        }

        .quick-login {
            background: none;
            border: 1px solid #3b82f6;
            color: #3b82f6;
            border-radius: 6px;
            padding: 4px 12px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .quick-login:hover {
            background: #3b82f6;
            color: white;
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        /* Loading animation */
        .btn-login.loading {
            pointer-events: none;
        }

        .btn-login.loading .btn-text {
            opacity: 0;
        }

        .btn-login .spinner {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .btn-login.loading .spinner {
            display: block;
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .login-container {
                padding: 15px;
            }
            
            .login-card {
                padding: 30px 25px;
            }
            
            .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-decoration"></div>
    
    <div class="login-container">
        <div class="login-card fade-in">
            <!-- Logo Section -->
            <div class="logo-section">
                <div class="logo-icon pulse">
                    <i class="fas fa-cash-register"></i>
                </div>
                <h1 class="login-title">TIPTOP INVENTORY SYSTEM</h1>
                <p class="login-subtitle"><b>WELCOME BACK</b></p>
                <p class="login-subtitle">Sign in to your POS account</p>
            </div>

            <!-- Error Alert -->
            <?php if($error): ?>
                <div class="alert fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="post" id="loginForm">
                <div class="form-floating">
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           placeholder="Username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required>
                    <label for="username">
                        <i class="fas fa-user me-2"></i>Username
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                    </div>
                </div>

                <div class="form-floating">
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="Password"
                           required>
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <div class="input-icon password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="passwordIcon"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-login w-100" id="loginBtn">
                    <span class="btn-text">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In
                    </span>
                    <div class="spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </button>
            </form>

            <!-- Demo Accounts -->
            <div class="demo-accounts">
                <div class="demo-title">
                    <i class="fas fa-info-circle me-2"></i>
                    Demo Accounts
                </div>
                
                <div class="demo-account">
                    <div class="account-info">
                        <span class="account-role">
                            <i class="fas fa-user-shield me-1"></i>Administrator
                        </span>
                        <span class="account-creds">admin / admin123</span>
                    </div>
                    <button type="button" 
                            class="quick-login" 
                            onclick="quickLogin('admin', 'admin123')">
                        Quick Login
                    </button>
                </div>

                <div class="demo-account">
                    <div class="account-info">
                        <span class="account-role">
                            <i class="fas fa-cash-register me-1"></i>Cashier
                        </span>
                        <span class="account-creds">cashier / cashier123</span>
                    </div>
                    <button type="button" 
                            class="quick-login" 
                            onclick="quickLogin('cashier', 'cashier123')">
                        Quick Login
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        // Quick login functionality
        function quickLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Add a small delay for visual feedback
            setTimeout(() => {
                document.getElementById('loginForm').submit();
            }, 200);
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.classList.add('loading');
            
            // Show loading for at least 500ms for better UX
            setTimeout(() => {
                // Form will submit naturally
            }, 500);
        });

        // Auto-focus username field
        window.addEventListener('load', function() {
            document.getElementById('username').focus();
        });

        // Enter key handling
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (form.checkValidity()) {
                    form.submit();
                }
            }
        });

        // Add floating label animation
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
            
            // Check if input has value on page load
            if (input.value) {
                input.parentElement.classList.add('focused');
            }
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.3)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.pointerEvents = 'none';
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>