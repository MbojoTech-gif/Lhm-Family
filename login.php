<?php
// login.php
require_once 'db.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Check user credentials
        $sql = "SELECT id, username, full_name, password, role, profile_pic FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $username, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // Verify password
            if (password_verify($password, $row['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['profile_pic'] = $row['profile_pic'];
                
                // Update last login
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, 'i', $row['id']);
                mysqli_stmt_execute($update_stmt);
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Include favicon -->
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lighthouse Ministers Family Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        html, body {
            height: 100%;
        }
        
        body {
            background: url('assets/images/group.jpg') center/cover no-repeat fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 15px;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            z-index: 1;
        }
        
        .login-container {
            display: flex;
            width: 100%;
            max-width: 900px;
            height: 500px;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
            position: relative;
            z-index: 2;
        }
        
        .brand-section {
            flex: 1;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.9) 0%, rgba(26, 26, 26, 0.9) 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .brand-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/group.png') center/cover no-repeat;
            opacity: 0.15;
            z-index: 0;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }
        
        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 25px rgba(255, 255, 255, 1);
            overflow: hidden;
            border: 2px solid white;
            position: relative;
            z-index: 1;
        }
        
        .logo img {
            width: 120%;
            height: 120%;
            object-fit: contain;
            
        }
        
        .brand-title {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-align: center;
            margin-bottom: 8px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
        }
        
        .brand-subtitle {
            font-size: 0.9rem;
            color: #f0f0f0;
            text-align: center;
            font-weight: 300;
            margin-bottom: 30px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
        }
        
        .brand-quote {
            text-align: center;
            font-style: italic;
            color: #ddd;
            font-size: 0.8rem;
            max-width: 280px;
            line-height: 1.4;
            position: relative;
            z-index: 1;
        }
        
        .login-section {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
        }
        
        .login-header {
            margin-bottom: 30px;
        }
        
        .login-title {
            font-size: 1.6rem;
            color: #222;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .login-subtitle {
            color: #666;
            font-size: 0.9rem;
        }
        
        .login-form {
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: #f9f9f9;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #000;
            background: white;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            border-left: 3px solid #c62828;
            display: <?php echo $error ? 'block' : 'none'; ?>;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #000;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 1px;
        }
        
        .btn-login:hover {
            background: #333;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.85rem;
        }
        
        .forgot-password a {
            color: #000;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .forgot-password a:hover {
            color: #333;
            text-decoration: underline;
        }
        
        .copyright {
            margin-top: 25px;
            text-align: center;
            color: #888;
            font-size: 0.75rem;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 768px) {
            .login-container {
                max-width: 400px;
                flex-direction: column;
                height: auto;
            }
            
            .brand-section {
                padding: 25px 20px;
            }
            
            .login-section {
                padding: 30px 25px;
            }
            
            .logo {
                width: 80px;
                height: 80px;
                margin-bottom: 12px;
            }
            
            .brand-title {
                font-size: 1.4rem;
            }
            
            .brand-subtitle {
                font-size: 0.8rem;
                margin-bottom: 20px;
            }
            
            .login-title {
                font-size: 1.4rem;
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                border-radius: 8px;
            }
            
            .brand-section, .login-section {
                padding: 20px 15px;
            }
            
            .brand-title {
                font-size: 1.2rem;
            }
            
            .login-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Brand Section -->
        <div class="brand-section">
            <div class="logo-container">
                <div class="logo">
                    <img src="assets/images/logo1.png" alt="Lighthouse Ministers Logo">
                </div>
                <h1 class="brand-title">LIGHTHOUSE MINISTERS</h1>
                <p class="brand-subtitle">Family Portal</p>
            </div>
            <p class="brand-quote">"Let your light so shine before men, that they may see your good works and glorify your Father in heaven." - Matthew 5:16</p>
        </div>
        
        <!-- Right Login Form Section -->
        <div class="login-section">
            <div class="login-header">
                <h2 class="login-title">Welcome Back</h2>
                <p class="login-subtitle">Sign in to access the family portal</p>
            </div>
            
            <?php if ($error): ?>
            <div class="error-message" id="errorMessage">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form class="login-form" method="POST" action="">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter username or email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
                
                <button type="submit" class="btn-login">SIGN IN</button>
                
                <div class="forgot-password">
                    <a href="#">Forgot your password?</a>
                </div>
            </form>
            
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> Lighthouse Ministers. All rights reserved.
            </div>
        </div>
    </div>

    <script>
        // Show error message with animation
        const errorMessage = document.getElementById('errorMessage');
        if (errorMessage) {
            errorMessage.style.display = 'block';
            setTimeout(() => {
                errorMessage.style.opacity = '1';
            }, 100);
        }
        
        // Add focus effects
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
    </script>
</body>
</html>