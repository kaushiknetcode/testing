<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'co') {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $conn = getDbConnection();
            
            // Check system_users table for CO
            $sql = "SELECT id, username, password_hash, role, full_name, is_active FROM system_users WHERE username = ? AND role = 'co' AND is_active = 1";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password_hash'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['login_time'] = date('Y-m-d H:i:s');
                    
                    // Update last login
                    $update_sql = "UPDATE system_users SET last_login = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    if ($update_stmt) {
                        $update_stmt->bind_param('i', $user['id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    
                    // Log the login
                    $log_entry = date('Y-m-d H:i:s') . " - CO login: " . $user['username'] . " (ID: " . $user['id'] . ")\n";
                    $log_dir = __DIR__ . '/../logs';
                    if (!is_dir($log_dir)) {
                        mkdir($log_dir, 0755, true);
                    }
                    file_put_contents($log_dir . '/co.log', $log_entry, FILE_APPEND | LOCK_EX);
                    
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid username or password. Please try again.';
                }
            } else {
                $error = 'Invalid username or password. Please try again.';
            }
            
            $stmt->close();
            $conn->close();
            
        } catch (Exception $e) {
            $error = 'Login system error. Please try again later.';
            // Log the error
            error_log('CO login error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CO Login - Eastern Railway I-Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: #28a745;
            color: white;
            text-align: center;
            padding: 2rem;
        }
        .login-body {
            padding: 2rem;
        }
        .footer-text {
            text-align: center;
            margin-top: 2rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-container">
                    <div class="login-header">
                        <i class="fas fa-user-check fa-3x mb-3"></i>
                        <h4 class="mb-0">Controlling Officer</h4>
                        <p class="mb-0 opacity-75">Review I-Card Applications</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                       required autocomplete="username">
                                <div class="form-text">Use your assigned CO username</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       required autocomplete="current-password">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="../index.php" class="text-decoration-none">Back to Home</a>
                            <span class="mx-2">|</span>
                            <a href="#" class="text-decoration-none">Need Help?</a>
                        </div>
                        
                        <div class="mt-3 p-3 bg-light rounded">
                            <small class="text-muted">
                                <strong>For COs:</strong> You can review non-gazetted employee applications. 
                                Contact admin if you need password reset.
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="footer-text">
                    Eastern Railway I-Card System © <?php echo date('Y'); ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></script>
</body>
</html>