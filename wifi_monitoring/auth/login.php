<?php
/**
 * Halaman Login
 */

require_once dirname(__FILE__) . '/../config/session.php';
require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../middleware/csrf.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    if ($_SESSION['role'] === 'superadmin') {
        header('Location: ' . getBaseUrl() . '/superadmin/dashboard.php');
    } else {
        header('Location: ' . getBaseUrl() . '/admin/dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    // Debug logging
    error_log("Login attempt: username=" . $username);
    
    // Verifikasi CSRF
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid CSRF token. Please try again.';
        error_log("CSRF token invalid");
    } else if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        // Query user dari database
        $stmt = $conn->prepare('SELECT id_user, username, password, nama, role FROM users WHERE username = ?');
        if (!$stmt) {
            $error = 'Database error: ' . $conn->error;
            error_log("Prepare statement failed: " . $conn->error);
        } else {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            error_log("User found: " . ($user ? "yes" : "no"));
            
            if ($user) {
                error_log("Verifying password for user: " . $user['username']);
                $password_valid = password_verify($password, $user['password']);
                error_log("Password verification: " . ($password_valid ? "valid" : "invalid"));
                
                if ($password_valid) {
                    // Login berhasil
                    $_SESSION['id_user'] = $user['id_user'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama'] = $user['nama'];
                    $_SESSION['role'] = $user['role'];
                    
                    error_log("Login successful for user: " . $user['username'] . " (role: " . $user['role'] . ")");
                    
                    // Redirect sesuai role
                    $redirect_url = ($user['role'] === 'superadmin') 
                        ? getBaseUrl() . '/superadmin/dashboard.php'
                        : getBaseUrl() . '/admin/dashboard.php';
                    
                    error_log("Redirecting to: " . $redirect_url);
                    header('Location: ' . $redirect_url);
                    exit();
                } else {
                    $error = 'Username atau password salah!';
                }
            } else {
                $error = 'Username tidak ditemukan!';
            }
        }
    }
}

// Generate CSRF Token
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Monitoring Access Point</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: bold;
            font-size: 24px;
        }
        
        .login-header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .login-form {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            padding: 10px 15px;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 5px;
            padding: 10px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .alert {
            border-radius: 5px;
            border: none;
        }
        
        .demo-info {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            font-size: 13px;
        }
        
        .demo-info h6 {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .demo-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .demo-info code {
            background: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>📡 Monitoring AP</h2>
            <p>Sistem Monitoring Access Point</p>
        </div>
        
        <div class="login-form">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Masukkan username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Masukkan password" required>
                </div>
                
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <button type="submit" class="btn btn-login">
                    Masuk <i class="bi bi-box-arrow-in-right ms-2"></i>
                </button>
            </form>
            
            <div class="demo-info">
                <h6>📋 Akun Demo (Testing)</h6>
                <p><strong>Super Admin:</strong> <code>superadmin</code> / <code>superadmin123</code></p>
                <p><strong>Admin:</strong> <code>admin</code> / <code>admin123</code></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
