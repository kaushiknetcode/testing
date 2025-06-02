<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Simple redirect check without circular dependencies
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'employee') {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hrms_id = trim($_POST['hrms_id'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    
    // Basic validation
    if (empty($hrms_id) || empty($dob)) {
        $error = 'Please enter both HRMS ID and Date of Birth.';
    } else {
        try {
            $conn = getDbConnection();
            
            // Check if employee exists with HRMS ID and DOB
            $sql = "SELECT hrms_id, name, emp_number, dob, category, department_id, designation, mobile_no, status 
                    FROM employees 
                    WHERE hrms_id = ? AND dob = ? AND status = 'active' 
                    LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param('ss', $hrms_id, $dob);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows === 1) {
                $employee = $result->fetch_assoc();
                
                // Set session variables for employee
                $_SESSION['user_id'] = $employee['hrms_id'];
                $_SESSION['user_name'] = $employee['name'];
                $_SESSION['user_type'] = 'employee';
                $_SESSION['user_role'] = 'employee';
                $_SESSION['employee_data'] = $employee;
                $_SESSION['login_time'] = date('Y-m-d H:i:s');
                
                // Update last login time
                $update_sql = "UPDATE employees SET last_login = NOW() WHERE hrms_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt) {
                    $update_stmt->bind_param('s', $employee['hrms_id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                
                // Log successful login
                $log_entry = date('Y-m-d H:i:s') . " - Employee login: " . $employee['hrms_id'] . " (" . $employee['name'] . ")\n";
                $log_dir = __DIR__ . '/../logs';
                if (!is_dir($log_dir)) {
                    mkdir($log_dir, 0755, true);
                }
                file_put_contents($log_dir . '/employee.log', $log_entry, FILE_APPEND | LOCK_EX);
                
                $stmt->close();
                $conn->close();
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
                
            } else {
                $error = 'Invalid HRMS ID or Date of Birth. Please check your credentials and try again.';
            }
            
            if ($stmt) $stmt->close();
            $conn->close();
            
        } catch (Exception $e) {
            $error = 'Login system error. Please try again later.';
            error_log('Employee login error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login - Eastern Railway I-Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #0d6efd, #0dcaf0);
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
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-container">
                    <div class="login-header">
                        <i class="fas fa-user-tie fa-3x mb-3"></i>
                        <h4 class="mb-0">Employee Portal</h4>
                        <p class="mb-0 opacity-75">Sign in to access your I-Card</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="hrms_id" class="form-label">
                                    <i class="fas fa-id-badge me-2"></i>HRMS ID
                                </label>
                                <input type="text" class="form-control" id="hrms_id" name="hrms_id" 
                                       value="<?php echo htmlspecialchars($_POST['hrms_id'] ?? ''); ?>" 
                                       required autocomplete="username" placeholder="Enter your HRMS ID">
                            </div>
                            
                            <div class="mb-3">
                                <label for="dob" class="form-label">
                                    <i class="fas fa-calendar me-2"></i>Date of Birth
                                </label>
                                <input type="date" class="form-control" id="dob" name="dob" 
                                       value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>"
                                       required autocomplete="bday">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">
                                    Remember my HRMS ID
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="../index.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Back to Home
                            </a>
                            <span class="mx-2">|</span>
                            <a href="#" class="text-decoration-none">
                                <i class="fas fa-question-circle me-1"></i>Need Help?
                            </a>
                        </div>
                        
                        <div class="mt-3 p-3 bg-light rounded">
                            <small class="text-muted">
                                <strong>Note:</strong> Use your HRMS ID and Date of Birth to login. 
                                If you're having trouble accessing your account, please contact your system administrator.
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
</body>
</html>