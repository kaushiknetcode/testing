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

// Get the most recent application
$application = null;
$error = '';

try {
    $conn = getDbConnection();
    
    // Get the most recent application with related data
    $sql = "SELECT a.*, 
                   d.name as department_name,
                   co.name as controlling_officer_name
            FROM applications a 
            LEFT JOIN departments d ON a.department_id = d.id
            LEFT JOIN controlling_officers co ON a.controlling_officer_id = co.id
            WHERE a.hrms_id = ? 
            ORDER BY a.created_at DESC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $hrms_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $application = $result->fetch_assoc();
        } else {
            $error = 'No application found. Please submit an application first.';
        }
        $stmt->close();
    }
    
    $conn->close();
} catch (Exception $e) {
    error_log('View application error: ' . $e->getMessage());
    $error = 'Unable to load application data. Please try again later.';
}

// If no application found, redirect to dashboard
if (!$application) {
    $_SESSION['error_message'] = $error;
    header('Location: dashboard.php');
    exit();
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'draft': return 'secondary';
        case 'submitted': return 'info';
        case 'co_pending': return 'warning';
        case 'dealer_pending': return 'warning';
        case 'awo_pending': return 'warning';
        case 'approved': return 'success';
        case 'rejected': return 'danger';
        default: return 'secondary';
    }
}

// Function to get readable status text
function getReadableStatus($status, $co_name = null) {
    switch ($status) {
        case 'draft': return 'Draft';
        case 'submitted': return 'Submitted';
        case 'co_pending': 
            return $co_name ? 'Pending Review by ' . $co_name : 'Pending CO Review';
        case 'dealer_pending': return 'Pending Dealer Review';
        case 'awo_pending': return 'Pending Final Approval';
        case 'approved': return 'Approved';
        case 'rejected': 
            return $co_name ? 'Rejected by ' . $co_name : 'Rejected';
        default: return ucfirst($status);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - Eastern Railway I-Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Screen Styles */
        body {
            background: #f8f9fa;
            font-size: 14px;
        }
        
        .application-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .app-header {
            background: white !important;
            background-color: white !important;
            color: #212529 !important;
            padding: 2rem;
            text-align: center;
            border: 2px solid #dee2e6;
            border-bottom: 3px solid #0d6efd;
        }
        
        .app-body {
            padding: 2rem;
        }
        
        .section {
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #495057;
        }
        
        .section-body {
            padding: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .info-item {
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #212529;
            font-size: 1rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .image-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            text-align: center;
        }
        
        .image-container {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            background: #f8f9fa;
        }
        
        .uploaded-image {
            max-width: 100%;
            max-height: 150px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .declaration-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1.5rem;
        }
        
        .floating-buttons {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
        }
        
        /* Print Styles */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            @page {
                size: A4;
                margin: 15mm;
            }
            
            body {
                background: white !important;
                font-size: 12px;
                line-height: 1.4;
            }
            
            .no-print {
                display: none !important;
            }
            
            .application-container {
                box-shadow: none;
                border-radius: 0;
                max-width: none;
                margin: 0;
            }
            
            .app-header {
                background: #2c3e50 !important;
                color: white !important;
                padding: 1rem;
                page-break-inside: avoid;
            }
            
            .app-body {
                padding: 1rem;
            }
            
            .section {
                margin-bottom: 1rem;
                page-break-inside: avoid;
                border: 1px solid #ddd;
            }
            
            .section-header {
                background: #f0f0f0 !important;
                padding: 0.5rem 1rem;
                font-size: 14px;
                font-weight: bold;
            }
            
            .section-body {
                padding: 1rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem;
            }
            
            .info-item {
                margin-bottom: 0.5rem;
            }
            
            .info-label {
                font-size: 11px;
                margin-bottom: 0.1rem;
            }
            
            .info-value {
                font-size: 12px;
            }
            
            .image-section {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
                page-break-inside: avoid;
            }
            
            .uploaded-image {
                max-height: 120px;
            }
            
            .declaration-section {
                background: #f9f9f9 !important;
                border: 1px solid #ccc;
                font-size: 11px;
                page-break-inside: avoid;
            }
            
            .status-badge {
                border: 1px solid #333;
                padding: 0.25rem 0.5rem;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Buttons -->
    <div class="floating-buttons no-print">
        <a href="dashboard.php" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print me-1"></i>Print
        </button>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
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

    <div class="container-fluid py-4 no-print"></div>

    <!-- Application Container -->
    <div class="application-container">
        <!-- Header -->
        <div class="app-header">
            <div style="background: white !important; color: #212529 !important; padding: 1rem; border: 2px solid #333; text-align: center; font-weight: bold; font-size: 1.5rem;">
                <i class="fas fa-id-card me-2"></i>Eastern Railway Kanchrapara Workshop I-Card Application
                <br>
                <small style="font-size: 1rem; opacity: 0.8;">Employee Application Details</small>
            </div>
        </div>

        <!-- Body -->
        <div class="app-body">
            <!-- Application Status -->
            <div class="section">
                <div class="section-header">
                    <i class="fas fa-info-circle me-2"></i>Application Status
                </div>
                <div class="section-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Application ID</div>
                            <div class="info-value">#<?php echo $application['id']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Current Status</div>
                            <div class="info-value">
                                <span class="badge bg-<?php echo getStatusBadgeClass($application['current_status']); ?> status-badge">
                                    <?php echo getReadableStatus($application['current_status'], $application['controlling_officer_name']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Submitted Date</div>
                            <div class="info-value"><?php echo date('d-m-Y H:i', strtotime($application['created_at'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Application Type</div>
                            <div class="info-value">New I-Card Application</div>
                        </div>
                    </div>

                    <?php if ($application['current_status'] === 'rejected' && !empty($application['co_remarks'])): ?>
                    <div class="alert alert-danger mt-3">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Rejection Reason:</h6>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($application['co_remarks'])); ?></p>
                        <small class="text-muted">
                            Rejected on: <?php echo date('d-m-Y H:i', strtotime($application['co_reviewed_at'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Employee Information -->
            <div class="section">
                <div class="section-header">
                    <i class="fas fa-user me-2"></i>Employee Information
                </div>
                <div class="section-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">HRMS ID</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['hrms_id']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Employee Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($employee['emp_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Employee Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($employee['name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Category</div>
                            <div class="info-value"><?php echo ucwords(str_replace('_', ' ', $employee['category'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job Information -->
            <div class="section">
                <div class="section-header">
                    <i class="fas fa-briefcase me-2"></i>Job Information
                </div>
                <div class="section-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Designation</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['designation']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Department</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['department_name']); ?></div>
                        </div>
                        <?php if (!empty($application['ticket_number'])): ?>
                        <div class="info-item">
                            <div class="info-label">Ticket Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['ticket_number']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($application['office_shop'])): ?>
                        <div class="info-item">
                            <div class="info-label">Office/Shop</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['office_shop']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($application['controlling_officer_name'])): ?>
                        <div class="info-item">
                            <div class="info-label">Controlling Officer</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['controlling_officer_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <div class="info-label">Date of Appointment</div>
                            <div class="info-value"><?php echo date('d-m-Y', strtotime($application['date_of_appointment'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date of Joining</div>
                            <div class="info-value"><?php echo date('d-m-Y', strtotime($application['date_of_joining'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date of Retirement</div>
                            <div class="info-value"><?php echo date('d-m-Y', strtotime($application['date_of_retirement'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="section">
                <div class="section-header">
                    <i class="fas fa-user-circle me-2"></i>Personal Information
                </div>
                <div class="section-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Blood Group</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['blood_group']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Height</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['height']); ?> cm</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Mobile Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['mobile_no'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email ID</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Identification Mark</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['identification_mark']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Address</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($application['address_line1']); ?>
                                <?php if (!empty($application['address_line2'])): ?>
                                    <br><?php echo htmlspecialchars($application['address_line2']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Uploaded Documents -->
            <div class="section">
                <div class="section-header">
                    <i class="fas fa-upload me-2"></i>Uploaded Documents
                </div>
                <div class="section-body">
                    <div class="image-section">
                        <div>
                            <h6 class="mb-3">Passport Photo</h6>
                            <div class="image-container">
                                <?php if (!empty($application['photo_path'])): ?>
                                    <img src="../uploads/photos/<?php echo htmlspecialchars($application['photo_path']); ?>" 
                                         class="uploaded-image" alt="Passport Photo">
                                <?php else: ?>
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                    <p class="text-muted mt-2">No photo uploaded</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <h6 class="mb-3">Employee Signature</h6>
                            <div class="image-container">
                                <?php if (!empty($application['signature_path'])): ?>
                                    <img src="../uploads/signatures/<?php echo htmlspecialchars($application['signature_path']); ?>" 
                                         class="uploaded-image" alt="Employee Signature">
                                <?php else: ?>
                                    <i class="fas fa-signature fa-3x text-muted"></i>
                                    <p class="text-muted mt-2">No signature uploaded</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <?php if (!empty($application['remarks'])): ?>
            <div class="section">
                <div class="section-header">
                    <i class="fas fa-comment me-2"></i>Additional Information
                </div>
                <div class="section-body">
                    <div class="info-value">
                        <?php echo nl2br(htmlspecialchars($application['remarks'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Declaration -->
            <div class="section">
                <div class="section-header">
                    <i class="fas fa-check-circle me-2"></i>Employee Declaration
                </div>
                <div class="section-body">
                    <div class="declaration-section">
                        <p class="mb-3">
                            <i class="fas fa-check-square text-success me-2"></i>
                            <strong>The employee has agreed to the following declaration:</strong>
                        </p>
                        <p class="mb-2" style="text-align: justify;">
                            "I hereby declare that the information furnished by me in this online application for a Railway Identity Card is true, complete, and correct to the best of my knowledge and belief. I understand that the Railway Identity Card is an official document and its misuse is prohibited. I am aware that providing false information or suppressing material facts may render me liable for disciplinary action as per the extant Railway Servants (Discipline & Appeal) Rules, 1968."
                        </p>
                        <p class="mb-0 text-muted">
                            <small>
                                <i class="fas fa-calendar me-1"></i>
                                Agreed on: <?php echo date('d-m-Y H:i', strtotime($application['created_at'])); ?>
                            </small>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Print Footer -->
            <div class="text-center mt-4" style="display: none;" id="print-footer">
                <hr>
                <p class="text-muted">
                    <small>
                        Eastern Railway Kanchrapara Workshop I-Card System - Application printed on <?php echo date('d-m-Y H:i'); ?>
                        <br>This is a computer-generated document and does not require a signature.
                    </small>
                </p>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4 no-print"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Print functionality
        window.addEventListener('beforeprint', function() {
            document.getElementById('print-footer').style.display = 'block';
        });
        
        window.addEventListener('afterprint', function() {
            document.getElementById('print-footer').style.display = 'none';
        });
        
        // Keyboard shortcut for print
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>