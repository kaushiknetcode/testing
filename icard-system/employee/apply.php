<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// REPLACE the session check section in apply.php with this:

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee') {
    header('Location: login.php');
    exit();
}

$hrms_id = $_SESSION['user_id']; // This contains the HRMS ID
$employee = null;
$errors = [];
$success = '';

// Get employee details
try {
    $conn = getDbConnection();
    $sql = "SELECT e.*, d.name as department_name FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.id 
            WHERE e.hrms_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $hrms_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $employee = $result->fetch_assoc();
        } else {
            $errors[] = 'Employee record not found.';
        }
        $stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    error_log('Employee fetch error: ' . $e->getMessage());
    $errors[] = 'Unable to load employee data.';
}

if (!$employee) {
    header('Location: dashboard.php');
    exit();
}

// Check if employee already has an application or I-Card
try {
    $conn = getDbConnection();
    
    // Check for existing I-Card
    $icard_sql = "SELECT * FROM icards WHERE hrms_id = ? AND is_current = 1 AND status = 'active' LIMIT 1";
    $icard_stmt = $conn->prepare($icard_sql);
    if ($icard_stmt) {
        $icard_stmt->bind_param('s', $hrms_id);
        $icard_stmt->execute();
        $icard_result = $icard_stmt->get_result();
        
        if ($icard_result && $icard_result->num_rows > 0) {
            header('Location: dashboard.php');
            exit();
        }
        $icard_stmt->close();
    }
    
    // Check for pending applications
    $app_sql = "SELECT * FROM applications WHERE hrms_id = ? AND current_status NOT IN ('rejected') LIMIT 1";
    $app_stmt = $conn->prepare($app_sql);
    if ($app_stmt) {
        $app_stmt->bind_param('s', $hrms_id);
        $app_stmt->execute();
        $app_result = $app_stmt->get_result();
        
        if ($app_result && $app_result->num_rows > 0) {
            header('Location: dashboard.php');
            exit();
        }
        $app_stmt->close();
    }
    
    $conn->close();
} catch (Exception $e) {
    error_log('Application check error: ' . $e->getMessage());
}

// Get controlling officers for non-gazetted employees
$controlling_officers = [];
if ($employee['category'] === 'non_gazetted') {
    try {
        $conn = getDbConnection();
        $co_sql = "SELECT id, name FROM controlling_officers WHERE status = 'active' ORDER BY name";
        $co_result = $conn->query($co_sql);
        if ($co_result) {
            while ($row = $co_result->fetch_assoc()) {
                $controlling_officers[] = $row;
            }
        }
        $conn->close();
    } catch (Exception $e) {
        error_log('CO fetch error: ' . $e->getMessage());
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form fields
    $designation = trim($_POST['designation'] ?? '');
    $ticket_number = trim($_POST['ticket_number'] ?? '');
    $office_shop = trim($_POST['office_shop'] ?? '');
    $controlling_officer_id = ($_POST['controlling_officer_id'] ?? '');
    $department_id = ($_POST['department_id'] ?? '');
    $date_of_appointment = $_POST['date_of_appointment'] ?? '';
    $date_of_joining = $_POST['date_of_joining'] ?? '';
    $date_of_retirement = $_POST['date_of_retirement'] ?? '';
    $blood_group = trim($_POST['blood_group'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $identification_mark = trim($_POST['identification_mark'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    
    // Validate agreement checkbox
    $agreement = isset($_POST['agreement']) ? $_POST['agreement'] : '';
    if (empty($agreement)) {
        $errors[] = 'You must agree to the declaration before submitting your application.';
    }
    
    // Basic validations
    if (empty($designation)) $errors[] = 'Designation is required.';
    if (empty($ticket_number)) $errors[] = 'Ticket number is required.';
    if (empty($office_shop)) $errors[] = 'Office/Shop is required.';
    if ($employee['category'] === 'non_gazetted' && empty($controlling_officer_id)) {
        $errors[] = 'Controlling Officer is required for non-gazetted employees.';
    }
    if (empty($date_of_appointment)) $errors[] = 'Date of appointment is required.';
    if (empty($date_of_joining)) $errors[] = 'Date of joining is required.';
    if (empty($date_of_retirement)) $errors[] = 'Date of retirement is required.';
    if (empty($blood_group)) $errors[] = 'Blood group is required.';
    if (empty($height) || !is_numeric($height)) $errors[] = 'Valid height is required.';
    if (empty($mobile_number) || !preg_match('/^[0-9]{10}$/', $mobile_number)) {
        $errors[] = 'Valid 10-digit mobile number is required.';
    }
    if (empty($identification_mark)) $errors[] = 'Identification mark is required.';
    if (empty($address_line1)) $errors[] = 'Address line 1 is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required.';
    }
    
    // File validations
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Passport photo is required.';
    } else {
        $photo = $_FILES['photo'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($photo['type'], $allowed_types)) {
            $errors[] = 'Photo must be in JPEG or PNG format.';
        }
        if ($photo['size'] > 2 * 1024 * 1024) { // 2MB limit
            $errors[] = 'Photo size must be less than 2MB.';
        }
    }
    
    if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Signature is required.';
    } else {
        $signature = $_FILES['signature'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($signature['type'], $allowed_types)) {
            $errors[] = 'Signature must be in JPEG or PNG format.';
        }
        if ($signature['size'] > 1 * 1024 * 1024) { // 1MB limit
            $errors[] = 'Signature size must be less than 1MB.';
        }
    }
    
    // If no errors, process the application
    if (empty($errors)) {
        try {
            $conn = getDbConnection();
            
            // Create uploads directory if not exists
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $photo_dir = $upload_dir . 'photos/';
            if (!is_dir($photo_dir)) mkdir($photo_dir, 0755, true);
            
            $sig_dir = $upload_dir . 'signatures/';
            if (!is_dir($sig_dir)) mkdir($sig_dir, 0755, true);
            
            // Generate unique filenames
            $photo_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $sig_ext = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
            $photo_filename = $hrms_id . '_' . time() . '_photo.' . $photo_ext;
            $sig_filename = $hrms_id . '_' . time() . '_signature.' . $sig_ext;
            
            // Move uploaded files
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $photo_dir . $photo_filename) &&
                move_uploaded_file($_FILES['signature']['tmp_name'], $sig_dir . $sig_filename)) {
                
                // Determine status based on employee category
                if ($employee['category'] === 'gazetted') {
                    $initial_status = 'dealer_pending';
                    $success_message = "Your I-Card application has been submitted and forwarded to the dealer!";
                } else {
                    $initial_status = 'co_pending';
                    $success_message = "Your I-Card application has been submitted and sent to your Controlling Officer for review!";
                }

                // Insert application
                $sql = "INSERT INTO applications (
                    hrms_id, designation, ticket_number, office_shop, controlling_officer_id,
                    department_id, date_of_appointment, date_of_joining, date_of_retirement,
                    blood_group, height, mobile_number, identification_mark, address_line1,
                    address_line2, email, photo_path, signature_path, remarks, current_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('ssssississssssssssss',
                        $hrms_id, $designation, $ticket_number, $office_shop, $controlling_officer_id,
                        $department_id, $date_of_appointment, $date_of_joining, $date_of_retirement,
                        $blood_group, $height, $mobile_number, $identification_mark, $address_line1,
                        $address_line2, $email, $photo_filename, $sig_filename, $remarks, $initial_status
                    );
                    
                    if ($stmt->execute()) {
                        // FIXED: Redirect to dashboard instead of staying on form
                        $_SESSION['success_message'] = $success_message;
                        header('Location: dashboard.php');
                        exit();
                    } else {
                        $errors[] = "Failed to submit application. Please try again.";
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Database prepare failed: " . $conn->error;
                }
            } else {
                $errors[] = "Failed to upload files. Please try again.";
            }
            
            $conn->close();
        } catch (Exception $e) {
            error_log('Application submission error: ' . $e->getMessage());
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Get departments
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for I-Card - Railway I-Card Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-id-card me-2"></i>I-Card Portal
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo htmlspecialchars($employee['name']); ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Please correct the following errors:</h6>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Application Form -->
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>I-Card Application Form
                </h4>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <!-- Employee Information (Read-only) -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary border-bottom pb-2">Employee Information</h5>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['name']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">HRMS ID</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['hrms_id']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="form-label">Employee Number</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['emp_number']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" value="<?php echo ucwords(str_replace('_', ' ', $employee['category'])); ?>" readonly>
                        </div>
                    </div>

                    <!-- Job Details -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary border-bottom pb-2">Job Information</h5>
                        </div>
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Designation <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="designation" name="designation" 
                                   value="<?php echo htmlspecialchars($_POST['designation'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="ticket_number" class="form-label">Ticket Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ticket_number" name="ticket_number" 
                                   value="<?php echo htmlspecialchars($_POST['ticket_number'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label for="office_shop" class="form-label">Office/Shop <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="office_shop" name="office_shop" 
                                   value="<?php echo htmlspecialchars($_POST['office_shop'] ?? ''); ?>" required>
                        </div>
                        
                        <?php if ($employee['category'] === 'non_gazetted'): ?>
                        <div class="col-md-6 mt-3">
                            <label for="controlling_officer_id" class="form-label">Controlling Officer <span class="text-danger">*</span></label>
                            <select class="form-select" id="controlling_officer_id" name="controlling_officer_id" required>
                                <option value="">Select Controlling Officer</option>
                                <?php foreach ($controlling_officers as $co): ?>
                                    <option value="<?php echo $co['id']; ?>" 
                                            <?php echo (($_POST['controlling_officer_id'] ?? '') == $co['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($co['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6 mt-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Service Dates -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary border-bottom pb-2">Service Information</h5>
                        </div>
                        <div class="col-md-4">
                            <label for="date_of_appointment" class="form-label">Date of Appointment <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_of_appointment" name="date_of_appointment" 
                                   value="<?php echo htmlspecialchars($_POST['date_of_appointment'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="date_of_joining" class="form-label">Date of Joining <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_of_joining" name="date_of_joining" 
                                   value="<?php echo htmlspecialchars($_POST['date_of_joining'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="date_of_retirement" class="form-label">Date of Retirement <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_of_retirement" name="date_of_retirement" 
                                   value="<?php echo htmlspecialchars($_POST['date_of_retirement'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Personal Details -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary border-bottom pb-2">Personal Information</h5>
                        </div>
                        <div class="col-md-6">
                            <label for="blood_group" class="form-label">Blood Group <span class="text-danger">*</span></label>
                            <select class="form-select" id="blood_group" name="blood_group" required>
                                <option value="">Select Blood Group</option>
                                <?php 
                                $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                foreach ($blood_groups as $bg): 
                                ?>
                                    <option value="<?php echo $bg; ?>" 
                                            <?php echo (($_POST['blood_group'] ?? '') == $bg) ? 'selected' : ''; ?>>
                                        <?php echo $bg; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="height" class="form-label">Height (in cm) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="height" name="height" min="100" max="250"
                                   value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label for="mobile_number" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="mobile_number" name="mobile_number" 
                                   pattern="[0-9]{10}" value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label for="identification_mark" class="form-label">Identification Mark <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="identification_mark" name="identification_mark" 
                                   value="<?php echo htmlspecialchars($_POST['identification_mark'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary border-bottom pb-2">Address Information</h5>
                        </div>
                        <div class="col-md-6">
                            <label for="address_line1" class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="address_line1" name="address_line1" 
                                   value="<?php echo htmlspecialchars($_POST['address_line1'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="address_line2" class="form-label">Address Line 2</label>
                            <input type="text" class="form-control" id="address_line2" name="address_line2" 
                                   value="<?php echo htmlspecialchars($_POST['address_line2'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mt-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Document Upload -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary border-bottom pb-2">Document Upload</h5>
                        </div>
                        <div class="col-md-6">
                            <label for="photo" class="form-label">Passport Photo <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*" required>
                            <div class="form-text">Upload a recent passport-size photo (JPEG/PNG, max 2MB)</div>
                        </div>
                        <div class="col-md-6">
                            <label for="signature" class="form-label">Signature <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="signature" name="signature" accept="image/*" required>
                            <div class="form-text">Upload your signature (JPEG/PNG, max 1MB)</div>
                        </div>
                    </div>

                    <!-- Remarks Section - UPDATED -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="remarks" class="form-label">Additional Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                      placeholder="Any additional information you would like to provide (optional)"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                            <div class="form-text">
                                Please provide any additional information that may be relevant to your application.
                            </div>
                        </div>
                    </div>

                    <!-- Declaration Section - NEW -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-danger shadow-sm">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Important Declaration
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-warning border-warning">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="agreement" name="agreement" required>
                                            <label class="form-check-label" for="agreement">
                                                <strong>I hereby declare that the information furnished by me in this online application for a Railway Identity Card is true, complete, and correct to the best of my knowledge and belief. I understand that the Railway Identity Card is an official document and its misuse is prohibited. I am aware that providing false information or suppressing material facts may render me liable for disciplinary action as per the extant Railway Servants (Discipline & Appeal) Rules, 1968. By ticking this box, I confirm that I have read, understood, and agree to these terms.</strong>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button - UPDATED -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-success btn-lg" id="submitBtn" disabled>
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Enable/disable submit button based on agreement checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const agreementCheckbox = document.getElementById('agreement');
        const submitBtn = document.getElementById('submitBtn');
        
        if (agreementCheckbox && submitBtn) {
            agreementCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                
                submitBtn.disabled = !isChecked;
                
                if (isChecked) {
                    submitBtn.classList.remove('btn-secondary');
                    submitBtn.classList.add('btn-success');
                } else {
                    submitBtn.classList.remove('btn-success');
                    submitBtn.classList.add('btn-secondary');
                }
            });
        }
        
        // Additional form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const agreement = document.getElementById('agreement');
                
                if (!agreement || !agreement.checked) {
                    e.preventDefault();
                    alert('Please agree to the declaration before submitting your application.');
                    return false;
                }
            });
        }
    });
    </script>

    <style>
    .form-check-input:checked {
        background-color: #28a745;
        border-color: #28a745;
    }

    .form-check-label {
        cursor: pointer;
        line-height: 1.6;
    }

    #submitBtn:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .card-border-danger {
        border-color: #dc3545 !important;
    }
    </style>
</body>
</html>