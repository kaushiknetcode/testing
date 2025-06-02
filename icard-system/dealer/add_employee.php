<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    header('Location: login.php');
    exit();
}

$success = '';
$errors = [];

// Get departments for dropdown
$departments = [];
try {
    $conn = getDbConnection();
    $dept_sql = "SELECT id, name FROM departments WHERE status = 'active' ORDER BY name";
    $dept_result = $conn->query($dept_sql);
    if ($dept_result) {
        while ($row = $dept_result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    $conn->close();
} catch (Exception $e) {
    error_log('Department fetch error: ' . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $hrms_id = trim($_POST['hrms_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $emp_number = trim($_POST['emp_number'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $department_id = trim($_POST['department_id'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $mobile_no = trim($_POST['mobile_no'] ?? '');
    
    // Validation
    if (empty($hrms_id)) $errors[] = 'HRMS ID is required';
    if (empty($name)) $errors[] = 'Employee name is required';
    if (empty($emp_number)) $errors[] = 'Employee number is required';
    if (empty($dob)) $errors[] = 'Date of birth is required';
    if (empty($category)) $errors[] = 'Category is required';
    
    // Validate HRMS ID format (basic validation)
    if (!empty($hrms_id) && !preg_match('/^[A-Z0-9]+$/', $hrms_id)) {
        $errors[] = 'HRMS ID should contain only letters and numbers';
    }
    
    // Validate date of birth
    if (!empty($dob)) {
        $dob_date = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dob_date || $dob_date->format('Y-m-d') !== $dob) {
            $errors[] = 'Invalid date of birth format';
        } else {
            // Check if date is reasonable (not in future, not too old)
            $today = new DateTime();
            $age = $today->diff($dob_date)->y;
            if ($age < 18 || $age > 100) {
                $errors[] = 'Date of birth seems unrealistic (age should be between 18-100)';
            }
        }
    }
    
    // Validate mobile number
    if (!empty($mobile_no) && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $errors[] = 'Mobile number should be exactly 10 digits';
    }
    
    // If no validation errors, check for duplicates and insert
    if (empty($errors)) {
        try {
            $conn = getDbConnection();
            
            // Check for duplicate HRMS ID
            $check_sql = "SELECT hrms_id FROM employees WHERE hrms_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param('s', $hrms_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $errors[] = 'An employee with this HRMS ID already exists';
                $check_stmt->close();
            } else {
                $check_stmt->close();
                
                // Check for duplicate employee number
                $check_emp_sql = "SELECT emp_number FROM employees WHERE emp_number = ?";
                $check_emp_stmt = $conn->prepare($check_emp_sql);
                $check_emp_stmt->bind_param('s', $emp_number);
                $check_emp_stmt->execute();
                $check_emp_result = $check_emp_stmt->get_result();
                
                if ($check_emp_result->num_rows > 0) {
                    $errors[] = 'An employee with this employee number already exists';
                    $check_emp_stmt->close();
                } else {
                    $check_emp_stmt->close();
                    
                    // Insert new employee
                    $insert_sql = "INSERT INTO employees (hrms_id, name, emp_number, dob, category, department_id, designation, mobile_no, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                    $insert_stmt = $conn->prepare($insert_sql);
                    
                    // Handle null department_id
                    $dept_id = empty($department_id) ? null : $department_id;
                    
                    $insert_stmt->bind_param('ssssssss', 
                        $hrms_id, $name, $emp_number, $dob, $category, $dept_id, $designation, $mobile_no
                    );
                    
                    if ($insert_stmt->execute()) {
                        $success = "Employee added successfully! HRMS ID: $hrms_id";
                        
                        // Log the action
                        $dealer_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown Dealer';
                        $log_entry = date('Y-m-d H:i:s') . " - Dealer " . $dealer_name . " added new employee: " . $hrms_id . " (" . $name . ")\n";
                        $log_dir = __DIR__ . '/../logs';
                        if (!is_dir($log_dir)) {
                            mkdir($log_dir, 0755, true);
                        }
                        file_put_contents($log_dir . '/dealer_actions.log', $log_entry, FILE_APPEND | LOCK_EX);
                        
                        // Clear form data on success
                        $_POST = [];
                        
                    } else {
                        $errors[] = 'Failed to add employee. Please try again.';
                    }
                    $insert_stmt->close();
                }
            }
            
            $conn->close();
        } catch (Exception $e) {
            error_log('Add employee error: ' . $e->getMessage());
            $errors[] = 'Database error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Employee - Dealer Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }
        .section-title {
            color: #17a2b8;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-info">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-cog me-2"></i>Dealer Panel
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Dealer'); ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Header -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center">
                        <h2 class="text-info mb-2">
                            <i class="fas fa-user-plus me-2"></i>Add New Employee
                        </h2>
                        <p class="text-muted">
                            Add a new employee to the system manually
                        </p>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Employee Form -->
                <form method="POST" id="addEmployeeForm">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-user me-2"></i>Basic Information
                        </h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hrms_id" class="form-label">HRMS ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="hrms_id" name="hrms_id" 
                                           value="<?php echo htmlspecialchars($_POST['hrms_id'] ?? ''); ?>" 
                                           required placeholder="e.g., HR001, EMP123">
                                    <div class="form-text">Unique identifier for the employee</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emp_number" class="form-label">Employee Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="emp_number" name="emp_number" 
                                           value="<?php echo htmlspecialchars($_POST['emp_number'] ?? ''); ?>" 
                                           required placeholder="e.g., E12345">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                           required placeholder="Enter employee's full name">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="dob" name="dob" 
                                           value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Job Information -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-briefcase me-2"></i>Job Information
                        </h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="gazetted" <?php echo (($_POST['category'] ?? '') === 'gazetted') ? 'selected' : ''; ?>>
                                            Gazetted
                                        </option>
                                        <option value="non_gazetted" <?php echo (($_POST['category'] ?? '') === 'non_gazetted') ? 'selected' : ''; ?>>
                                            Non-Gazetted
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department_id" class="form-label">Department</label>
                                    <select class="form-select" id="department_id" name="department_id">
                                        <option value="">Select Department (Optional)</option>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" 
                                                <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="designation" class="form-label">Designation</label>
                                    <input type="text" class="form-control" id="designation" name="designation" 
                                           value="<?php echo htmlspecialchars($_POST['designation'] ?? ''); ?>" 
                                           placeholder="e.g., Assistant Engineer">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mobile_no" class="form-label">Mobile Number</label>
                                    <input type="tel" class="form-control" id="mobile_no" name="mobile_no" 
                                           value="<?php echo htmlspecialchars($_POST['mobile_no'] ?? ''); ?>" 
                                           pattern="[0-9]{10}" maxlength="10" placeholder="10 digit mobile number">
                                    <div class="form-text">Optional - 10 digit mobile number</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Section -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-info btn-lg me-3">
                            <i class="fas fa-user-plus me-2"></i>Add Employee
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </form>
                
                <!-- Instructions -->
                <div class="mt-4 p-4 bg-light rounded">
                    <h6><i class="fas fa-info-circle me-2"></i>Instructions</h6>
                    <ul class="mb-0 small">
                        <li><strong>HRMS ID:</strong> Must be unique. Use letters and numbers only.</li>
                        <li><strong>Employee Number:</strong> Must be unique. Usually starts with 'E' followed by numbers.</li>
                        <li><strong>Category:</strong> Choose 'Gazetted' for officers, 'Non-Gazetted' for staff.</li>
                        <li><strong>Department:</strong> Optional. Employee can be assigned to department later.</li>
                        <li><strong>Login Credentials:</strong> Employee will login using HRMS ID + Date of Birth.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('addEmployeeForm').addEventListener('submit', function(e) {
            const hrmsId = document.getElementById('hrms_id').value.trim();
            const empNumber = document.getElementById('emp_number').value.trim();
            const name = document.getElementById('name').value.trim();
            const dob = document.getElementById('dob').value;
            const category = document.getElementById('category').value;
            
            // Basic validation
            if (!hrmsId || !empNumber || !name || !dob || !category) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // HRMS ID validation
            if (!/^[A-Z0-9]+$/.test(hrmsId)) {
                e.preventDefault();
                alert('HRMS ID should contain only letters and numbers.');
                return false;
            }
            
            // Mobile number validation
            const mobile = document.getElementById('mobile_no').value.trim();
            if (mobile && !/^[0-9]{10}$/.test(mobile)) {
                e.preventDefault();
                alert('Mobile number should be exactly 10 digits.');
                return false;
            }
            
            // Age validation
            const dobDate = new Date(dob);
            const today = new Date();
            const age = Math.floor((today - dobDate) / (365.25 * 24 * 60 * 60 * 1000));
            
            if (age < 18 || age > 100) {
                e.preventDefault();
                alert('Employee age should be between 18 and 100 years.');
                return false;
            }
        });
        
        // Auto-uppercase HRMS ID
        document.getElementById('hrms_id').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>