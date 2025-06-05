<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hrmsId = sanitize($_POST['hrms_id'] ?? '');
    $dob = sanitize($_POST['dob'] ?? '');
    
    // Basic validation
    if (empty($hrmsId) || empty($dob)) {
        $error = 'Please enter both HRMS ID and Date of Birth.';
    } else {
        $employee = authenticateEmployee($hrmsId, $dob);
        
        if ($employee) {
            // Set session variables
            $_SESSION['user_id'] = $employee['hrms_id'];
            $_SESSION['user_name'] = $employee['name'];
            $_SESSION['user_type'] = 'employee';
            $_SESSION['user_role'] = 'employee';
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid HRMS ID or Date of Birth. Please try again.';
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
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4>Employee Login</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="hrms_id" class="form-label">HRMS ID</label>
                                <input type="text" class="form-control" id="hrms_id" name="hrms_id" required 
                                       value="<?php echo htmlspecialchars($_POST['hrms_id'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob" required
                                       value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="../index.php">Back to Home</a> | 
                            <a href="forgot-password.php">Forgot HRMS ID?</a>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3 text-muted">
                    <small>Eastern Railway I-Card System &copy; <?php echo date('Y'); ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
