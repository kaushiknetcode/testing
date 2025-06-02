<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Authentication check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee') {
    header('Location: login.php');
    exit();
}

$employee = $_SESSION['employee_data'];
$hrms_id = $employee['hrms_id'];
$is_gazetted = ($employee['category'] === 'gazetted');

// Get dropdown data
$departments = [];
$controlling_officers = [];

try {
    $conn = getDbConnection();
    
    // Get departments
    $dept_sql = "SELECT id, name FROM departments WHERE status = 'active' ORDER BY name";
    $dept_result = $conn->query($dept_sql);
    if ($dept_result) {
        while ($row = $dept_result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    
    // Get controlling officers (only for non-gazetted)
    if (!$is_gazetted) {
        $co_sql = "SELECT id, name FROM controlling_officers WHERE status = 'active' ORDER BY name";
        $co_result = $conn->query($co_sql);
        if ($co_result) {
            while ($row = $co_result->fetch_assoc()) {
                $controlling_officers[] = $row;
            }
        }
    }
    
    $conn->close();
} catch (Exception $e) {
    error_log('Form data error: ' . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = false;
    
    // Validation
    $designation = trim($_POST['designation'] ?? '');
    $ticket_number = trim($_POST['ticket_number'] ?? '');
    $office_shop = trim($_POST['office_shop'] ?? '');
    $controlling_officer_id = $_POST['controlling_officer_id'] ?? '';
    $department_id = $_POST['department_id'] ?? '';
    $date_of_appointment = !empty($_POST['date_of_appointment']) ? $_POST['date_of_appointment'] : null;
    $date_of_joining = !empty($_POST['date_of_joining']) ? $_POST['date_of_joining'] : null;
    $date_of_retirement = !empty($_POST['date_of_retirement']) ? $_POST['date_of_retirement'] : null;
    $blood_group = $_POST['blood_group'] ?? '';
    $height = $_POST['height'] ?? '';
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
    
    // Required field validation
    if (empty($designation)) $errors[] = 'Designation is required';
    if (strlen($designation) > 30) $errors[] = 'Designation must be 30 characters or less';
    
    if (!$is_gazetted) {
        if (strlen($ticket_number) > 20) $errors[] = 'Ticket Number must be 20 characters or less';
        if (empty($office_shop)) $errors[] = 'Office/Shop is required for non-gazetted employees';
        if (empty($controlling_officer_id)) $errors[] = 'Controlling Officer is required for non-gazetted employees';
    }
    
    if (empty($department_id)) $errors[] = 'Department is required';
    if (empty($date_of_appointment)) $errors[] = 'Date of Appointment is required';
    if (empty($date_of_joining)) $errors[] = 'Date of Joining is required';
    if (empty($date_of_retirement)) $errors[] = 'Date of Retirement is required';
    if (empty($blood_group)) $errors[] = 'Blood Group is required';
    if (empty($height)) $errors[] = 'Height is required';
    if (empty($mobile_number)) $errors[] = 'Mobile Number is required';
    if (!preg_match('/^[0-9]{10}$/', $mobile_number)) $errors[] = 'Mobile Number must be 10 digits';
    if (empty($identification_mark)) $errors[] = 'Identification Mark is required';
    if (strlen($identification_mark) > 40) $errors[] = 'Identification Mark must be 40 characters or less';
    if (empty($address_line1)) $errors[] = 'Address Line 1 is required';
    if (strlen($address_line1) > 38) $errors[] = 'Address Line 1 must be 38 characters or less';
    if (!empty($address_line2) && strlen($address_line2) > 35) $errors[] = 'Address Line 2 must be 35 characters or less';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    
    // File validation
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Passport photo is required';
    } else {
        $allowed_photo = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($_FILES['photo']['type'], $allowed_photo)) {
            $errors[] = 'Photo must be JPG, JPEG, or PNG format';
        }
        if ($_FILES['photo']['size'] > 2 * 1024 * 1024) { // 2MB limit
            $errors[] = 'Photo size must be less than 2MB';
        }
    }
    
    if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Employee signature is required';
    } else {
        $allowed_sig = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($_FILES['signature']['type'], $allowed_sig)) {
            $errors[] = 'Signature must be JPG, JPEG, or PNG format';
        }
        if ($_FILES['signature']['size'] > 1 * 1024 * 1024) { // 1MB limit
            $errors[] = 'Signature size must be less than 1MB';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for I-Card - Eastern Railway I-Card System</title>
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
            color: #0d6efd;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        .preview-container {
            max-width: 200px;
            max-height: 200px;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            background: #fff;
        }
        .preview-image {
            max-width: 100%;
            max-height: 100%;
            border-radius: 4px;
        }
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        .file-input-label {
            background: #0d6efd;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            display: inline-block;
            cursor: pointer;
            transition: background 0.3s;
            width: 100%;
            text-align: center;
        }
        .file-input-label:hover {
            background: #0b5ed7;
        }
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
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-tie me-2"></i>Employee Portal
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
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center">
                        <h2 class="text-primary mb-2">
                            <i class="fas fa-id-card me-2"></i>I-Card Application Form
                        </h2>
                        <p class="text-muted">
                            Fill in all required information to apply for your Eastern Railway I-Card
                        </p>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($success) && $success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
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

                <!-- Application Form -->
                <form method="POST" enctype="multipart/form-data" id="applicationForm">
                    <!-- Employee Information Section -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-user me-2"></i>Employee Information
                        </h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">HRMS ID</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['hrms_id']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['name']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Number</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['emp_number']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <input type="text" class="form-control" value="<?php echo ucwords(str_replace('_', ' ', $employee['category'])); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Job Information Section -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-briefcase me-2"></i>Job Information
                        </h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Designation <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="designation" maxlength="30" required 
                                           value="<?php echo htmlspecialchars($_POST['designation'] ?? ''); ?>">
                                    <div class="form-text">Maximum 30 characters</div>
                                </div>
                            </div>
                            
                            <?php if (!$is_gazetted): ?>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ticket Number</label>
                                    <input type="text" class="form-control" name="ticket_number" maxlength="20"
                                           value="<?php echo htmlspecialchars($_POST['ticket_number'] ?? ''); ?>">
                                    <div class="form-text">Optional for employees</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Office/Shop <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="office_shop" required
                                           value="<?php echo htmlspecialchars($_POST['office_shop'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Controlling Officer <span class="text-danger">*</span></label>
                                    <select class="form-control" name="controlling_officer_id" required>
                                        <option value="">Select Controlling Officer</option>
                                        <?php foreach ($controlling_officers as $co): ?>
                                        <option value="<?php echo $co['id']; ?>" 
                                                <?php echo (($_POST['controlling_officer_id'] ?? '') == $co['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($co['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department <span class="text-danger">*</span></label>
                                    <select class="form-control" name="department_id" required>
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
                        </div>
                        
                        <!-- Date Fields -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Date of Appointment <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="date_of_appointment" required
                                           value="<?php echo htmlspecialchars($_POST['date_of_appointment'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Date of Joining at Kanchrapara <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="date_of_joining" required
                                           value="<?php echo htmlspecialchars($_POST['date_of_joining'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Date of Retirement <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="date_of_retirement" required
                                           value="<?php echo htmlspecialchars($_POST['date_of_retirement'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-user-circle me-2"></i>Personal Information
                        </h4>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Blood Group <span class="text-danger">*</span></label>
                                    <select class="form-control" name="blood_group" required>
                                        <option value="">Select Blood Group</option>
                                        <?php 
                                        $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                        foreach ($blood_groups as $bg): ?>
                                        <option value="<?php echo $bg; ?>" 
                                                <?php echo (($_POST['blood_group'] ?? '') == $bg) ? 'selected' : ''; ?>>
                                            <?php echo $bg; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Height (CM) <span class="text-danger">*</span></label>
                                    <select class="form-control" name="height" required>
                                        <option value="">Select Height</option>
                                        <?php for ($h = 140; $h <= 200; $h++): ?>
                                        <option value="<?php echo $h; ?>" 
                                                <?php echo (($_POST['height'] ?? '') == $h) ? 'selected' : ''; ?>>
                                            <?php echo $h; ?> cm
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="mobile_number" pattern="[0-9]{10}" maxlength="10" required
                                           value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                                    <div class="form-text">10 digit mobile number</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Identification Mark <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="identification_mark" maxlength="40" required
                                           value="<?php echo htmlspecialchars($_POST['identification_mark'] ?? ''); ?>">
                                    <div class="form-text">Maximum 40 characters</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email ID <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" required
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Residential Address Line 1 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="address_line1" maxlength="38" required
                                           value="<?php echo htmlspecialchars($_POST['address_line1'] ?? ''); ?>">
                                    <div class="form-text">Maximum 38 characters</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Residential Address Line 2</label>
                                    <input type="text" class="form-control" name="address_line2" maxlength="35"
                                           value="<?php echo htmlspecialchars($_POST['address_line2'] ?? ''); ?>">
                                    <div class="form-text">Maximum 35 characters</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Section -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-upload me-2"></i>Upload Documents
                        </h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Passport Photo <span class="text-danger">*</span></label>
                                    <div class="file-input-wrapper">
                                        <input type="file" id="photo" name="photo" accept="image/*" required>
                                        <label for="photo" class="file-input-label">
                                            <i class="fas fa-camera me-2"></i>Choose Photo
                                        </label>
                                    </div>
                                    <div class="form-text">JPG, JPEG, PNG - Max 2MB</div>
                                    <div class="preview-container mt-2" id="photoPreview" style="display: none;">
                                        <img id="photoImage" class="preview-image" alt="Photo Preview">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Signature <span class="text-danger">*</span></label>
                                    <div class="file-input-wrapper">
                                        <input type="file" id="signature" name="signature" accept="image/*" required>
                                        <label for="signature" class="file-input-label">
                                            <i class="fas fa-signature me-2"></i>Choose Signature
                                        </label>
                                    </div>
                                    <div class="form-text">JPG, JPEG, PNG - Max 1MB</div>
                                    <div class="preview-container mt-2" id="signaturePreview" style="display: none;">
                                        <img id="signatureImage" class="preview-image" alt="Signature Preview">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks Section -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-comment me-2"></i>Additional Information
                        </h4>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3" 
                            placeholder="Any additional information (Optional)"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                            <div class="form-text">Please provide any additional information that may be relevant to your application.</div>
                        </div>
                    </div>

                    <!-- Declaration Section - NEW -->
                    <div class="form-section">
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

                    <!-- Submit Section -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-lg me-3" id="submitBtn" disabled>
                            <i class="fas fa-paper-plane me-2"></i>Submit Application
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
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
        });

        // Image preview functionality
        function setupImagePreview(inputId, previewId, imageId) {
            document.getElementById(inputId).addEventListener('change', function(e) {
                const file = e.target.files[0];
                const preview = document.getElementById(previewId);
                const image = document.getElementById(imageId);
                
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        image.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                }
            });
        }

        // Setup previews
        setupImagePreview('photo', 'photoPreview', 'photoImage');
        setupImagePreview('signature', 'signaturePreview', 'signatureImage');

        // Form validation
        document.getElementById('applicationForm').addEventListener('submit', function(e) {
            const agreement = document.getElementById('agreement');
            
            if (!agreement || !agreement.checked) {
                e.preventDefault();
                alert('Please agree to the declaration before submitting your application.');
                return false;
            }
            
            const mobileNumber = document.getElementsByName('mobile_number')[0].value;
            if (!/^[0-9]{10}$/.test(mobileNumber)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit mobile number');
                return false;
            }
        });

        // Update file input labels when files are selected
        document.getElementById('photo').addEventListener('change', function() {
            const label = document.querySelector('label[for="photo"]');
            if (this.files.length > 0) {
                label.innerHTML = '<i class="fas fa-check me-2"></i>Photo Selected';
                label.style.background = '#198754';
            }
        });
        
        document.getElementById('signature').addEventListener('change', function() {
            const label = document.querySelector('label[for="signature"]');
            if (this.files.length > 0) {
                label.innerHTML = '<i class="fas fa-check me-2"></i>Signature Selected';
                label.style.background = '#198754';
            }
        });
    </script>
</body>
</html>