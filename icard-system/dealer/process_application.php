<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    header('Location: login.php');
    exit();
}

$application_id = intval($_GET['id'] ?? 0);
$application = null;
$employee = null;
$success = '';
$error = '';

// Get application details
if ($application_id > 0) {
    try {
        $conn = getDbConnection();
        
        // Get application with employee and CO details
        $sql = "SELECT a.*, e.name, e.emp_number, e.dob, e.category, 
                d.name as department_name, co.name as co_name
                FROM applications a 
                JOIN employees e ON a.hrms_id = e.hrms_id 
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN controlling_officers co ON a.controlling_officer_id = co.id
                WHERE a.id = ?
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $application_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows === 1) {
                $application = $result->fetch_assoc();
            } else {
                $error = 'Application not found.';
            }
            $stmt->close();
        }
        
        $conn->close();
    } catch (Exception $e) {
        error_log('Process application error: ' . $e->getMessage());
        $error = 'Unable to load application details: ' . $e->getMessage();
    }
} else {
    $error = 'Invalid application ID.';
}

// Handle form submission (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $application) {
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['dealer_remarks'] ?? '');
    
    if (in_array($action, ['approve', 'reject'])) {
        if (empty($remarks)) {
            $error = 'Please provide your remarks before ' . ($action === 'approve' ? 'approving' : 'rejecting') . ' the application.';
        } else {
            try {
                $conn = getDbConnection();
                
                if ($action === 'approve') {
                    $new_status = 'awo_pending';
                    $success_message = 'Application approved and forwarded to AWO successfully!';
                } else {
                    $new_status = 'rejected';
                    $success_message = 'Application rejected successfully.';
                }
                
                // Update application
                $update_sql = "UPDATE applications SET 
                              current_status = ?, 
                              dealer_remarks = ?, 
                              dealer_reviewed_at = NOW()
                              WHERE id = ? AND current_status = 'dealer_pending'";
                
                $stmt = $conn->prepare($update_sql);
                if ($stmt) {
                    $stmt->bind_param('ssi', $new_status, $remarks, $application_id);
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $success = $success_message;
                        
                        // Log the action with dealer name
                        $dealer_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown Dealer';
                        $log_entry = date('Y-m-d H:i:s') . " - Dealer " . $dealer_name . " " . $action . "d application ID: " . $application_id . " (HRMS: " . $application['hrms_id'] . ")\n";
                        $log_dir = __DIR__ . '/../logs';
                        if (!is_dir($log_dir)) {
                            mkdir($log_dir, 0755, true);
                        }
                        file_put_contents($log_dir . '/dealer_reviews.log', $log_entry, FILE_APPEND | LOCK_EX);
                        
                        // Update application status for display
                        $application['current_status'] = $new_status;
                        $application['dealer_remarks'] = $remarks;
                        $application['dealer_reviewed_at'] = date('Y-m-d H:i:s');
                        
                    } else {
                        $error = 'Failed to update application status. The application may have already been reviewed.';
                    }
                    $stmt->close();
                } else {
                    $error = 'Database error: ' . $conn->error;
                }
                
                $conn->close();
            } catch (Exception $e) {
                error_log('Review action error: ' . $e->getMessage());
                $error = 'An error occurred while processing your request: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Invalid action specified.';
    }
}

// Helper function to get status badge
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'co_pending': return 'warning';
        case 'dealer_pending': return 'info';
        case 'awo_pending': return 'primary';
        case 'approved': return 'success';
        case 'rejected': return 'danger';
        default: return 'secondary';
    }
}

// FIXED: Proper status detection based on who actually reviewed
function getReadableStatus($status, $application_data) {
    switch ($status) {
        case 'co_pending': 
            return 'Pending CO Review';
        case 'dealer_pending': 
            // Show CO approval in dealer pending status for non-gazetted employees
            if ($application_data['category'] === 'non_gazetted' && !empty($application_data['co_reviewed_at']) && !empty($application_data['co_name'])) {
                return 'Pending Dealer Review (Approved by ' . $application_data['co_name'] . ')';
            }
            return 'Pending Dealer Review';
        case 'awo_pending': 
            return 'Forwarded to AWO';
        case 'approved': 
            return 'I-Card Generated';
        case 'rejected':
            // Check who actually rejected it
            if (!empty($application_data['dealer_reviewed_at'])) {
                return 'Rejected by Dealer';
            } elseif (!empty($application_data['co_reviewed_at'])) {
                return 'Rejected by CO (' . ($application_data['co_name'] ?? 'Unknown') . ')';
            } else {
                return 'Rejected';
            }
        case 'submitted':
            return 'Submitted';
        case 'draft':
            return 'Draft';
        default: 
            return ucfirst(str_replace('_', ' ', $status));
    }
}

// Helper function to determine if application was rejected by CO
function isRejectedByCO($application_data) {
    return $application_data['current_status'] === 'rejected' && 
           !empty($application_data['co_reviewed_at']) && 
           empty($application_data['dealer_reviewed_at']);
}

// Helper function to determine if application was rejected by Dealer
function isRejectedByDealer($application_data) {
    return $application_data['current_status'] === 'rejected' && 
           !empty($application_data['dealer_reviewed_at']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Application - Dealer Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .application-section {
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
        .document-preview {
            max-width: 200px;
            max-height: 250px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .document-image {
            max-width: 100%;
            max-height: 180px;
            border-radius: 4px;
            object-fit: contain;
        }
        .review-actions {
            position: sticky;
            top: 20px;
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .rejection-notice {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            padding: 1rem;
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

    <div class="container-fluid py-4">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($application): ?>
        <div class="row">
            <!-- Application Details -->
            <div class="col-lg-8">
                <!-- Header -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="text-info mb-2">
                                    <i class="fas fa-file-alt me-2"></i>Application Review
                                </h3>
                                <p class="text-muted mb-0">Application ID: #<?php echo $application['id']; ?></p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?php echo getStatusBadgeClass($application['current_status']); ?> fs-6">
                                    <?php echo getReadableStatus($application['current_status'], $application); ?>
                                </span>
                                <br>
                                <small class="text-muted">
                                    Applied: <?php echo date('d-m-Y H:i', strtotime($application['created_at'])); ?>
                                </small>
                                <?php if ($application['category'] === 'gazetted'): ?>
                                    <br><span class="badge bg-purple text-white mt-1">Gazetted Employee</span>
                                <?php else: ?>
                                    <br><span class="badge bg-orange text-white mt-1">Non-Gazetted Employee</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CO Rejection Notice (if rejected by CO) -->
                <?php if (isRejectedByCO($application)): ?>
                <div class="rejection-notice">
                    <h6 class="text-danger mb-2">
                        <i class="fas fa-times-circle me-2"></i>Application Rejected by CO
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Rejected by:</strong> <?php echo htmlspecialchars($application['co_name'] ?? 'Unknown CO'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Rejection Date:</strong> <?php echo date('d-m-Y H:i', strtotime($application['co_reviewed_at'])); ?>
                        </div>
                    </div>
                    <?php if (!empty($application['co_remarks'])): ?>
                    <div class="mt-2">
                        <strong>Rejection Reason:</strong><br>
                        <em><?php echo nl2br(htmlspecialchars($application['co_remarks'])); ?></em>
                    </div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            This application was rejected at the CO level and did not reach dealer review.
                        </small>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Dealer Rejection Notice (if rejected by Dealer) -->
                <?php if (isRejectedByDealer($application)): ?>
                <div class="rejection-notice">
                    <h6 class="text-danger mb-2">
                        <i class="fas fa-times-circle me-2"></i>Application Rejected by Dealer
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Rejected on:</strong> <?php echo date('d-m-Y H:i', strtotime($application['dealer_reviewed_at'])); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Current Status:</strong> Application Rejected
                        </div>
                    </div>
                    <?php if (!empty($application['dealer_remarks'])): ?>
                    <div class="mt-2">
                        <strong>Rejection Reason:</strong><br>
                        <em><?php echo nl2br(htmlspecialchars($application['dealer_remarks'])); ?></em>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Employee Information -->
                <div class="application-section">
                    <h4 class="section-title">
                        <i class="fas fa-user me-2"></i>Employee Information
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td><?php echo htmlspecialchars($application['name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>HRMS ID:</strong></td>
                                    <td><?php echo htmlspecialchars($application['hrms_id']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Employee Number:</strong></td>
                                    <td><?php echo htmlspecialchars($application['emp_number']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Category:</strong></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucwords(str_replace('_', ' ', $application['category'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Date of Birth:</strong></td>
                                    <td><?php echo date('d-m-Y', strtotime($application['dob'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Department:</strong></td>
                                    <td><?php echo htmlspecialchars($application['department_name'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Designation:</strong></td>
                                    <td><?php echo htmlspecialchars($application['designation'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Mobile:</strong></td>
                                    <td><?php echo htmlspecialchars($application['mobile_number'] ?? 'N/A'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Job Details -->
                <div class="application-section">
                    <h4 class="section-title">
                        <i class="fas fa-briefcase me-2"></i>Job Information
                    </h4>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Ticket Number:</strong><br>
                            <?php echo htmlspecialchars($application['ticket_number'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Office/Shop:</strong><br>
                            <?php echo htmlspecialchars($application['office_shop'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Blood Group:</strong><br>
                            <?php echo htmlspecialchars($application['blood_group'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <strong>Height:</strong><br>
                            <?php echo htmlspecialchars($application['height'] ?? 'N/A'); ?> cm
                        </div>
                        <div class="col-md-4">
                            <strong>Date of Appointment:</strong><br>
                            <?php echo $application['date_of_appointment'] ? date('d-m-Y', strtotime($application['date_of_appointment'])) : 'N/A'; ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Date of Joining:</strong><br>
                            <?php echo $application['date_of_joining'] ? date('d-m-Y', strtotime($application['date_of_joining'])) : 'N/A'; ?>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <strong>Date of Retirement:</strong><br>
                            <?php echo $application['date_of_retirement'] ? date('d-m-Y', strtotime($application['date_of_retirement'])) : 'N/A'; ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Identification Mark:</strong><br>
                            <?php echo htmlspecialchars($application['identification_mark'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($application['email'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="application-section">
                    <h4 class="section-title">
                        <i class="fas fa-map-marker-alt me-2"></i>Address Information
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Address Line 1:</strong><br>
                            <?php echo htmlspecialchars($application['address_line1'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Address Line 2:</strong><br>
                            <?php echo htmlspecialchars($application['address_line2'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>

                <!-- Documents -->
                <div class="application-section">
                    <h4 class="section-title">
                        <i class="fas fa-paperclip me-2"></i>Uploaded Documents
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Passport Photo</h6>
                            <?php if (!empty($application['photo_path'])): ?>
                                <div class="document-preview">
                                    <img src="../uploads/photos/<?php echo htmlspecialchars($application['photo_path']); ?>" 
                                         class="document-image" alt="Passport Photo">
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">File: <?php echo htmlspecialchars($application['photo_path']); ?></small>
                                </div>
                            <?php else: ?>
                                <div class="document-preview">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                    <br><small>No photo uploaded</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6>Employee Signature</h6>
                            <?php if (!empty($application['signature_path'])): ?>
                                <div class="document-preview">
                                    <img src="../uploads/signatures/<?php echo htmlspecialchars($application['signature_path']); ?>" 
                                         class="document-image" alt="Employee Signature">
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">File: <?php echo htmlspecialchars($application['signature_path']); ?></small>
                                </div>
                            <?php else: ?>
                                <div class="document-preview">
                                    <i class="fas fa-signature fa-3x text-muted"></i>
                                    <br><small>No signature uploaded</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- CO Review (for All Non-Gazetted) -->
                <?php if ($application['category'] === 'non_gazetted'): ?>
                <div class="application-section">
                    <h4 class="section-title">
                        <i class="fas fa-user-check me-2"></i>CO Review
                    </h4>
                    <?php if (!empty($application['co_reviewed_at'])): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Controlling Officer:</strong><br>
                                <?php echo htmlspecialchars($application['co_name'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Review Date:</strong><br>
                                <?php echo date('d-m-Y H:i', strtotime($application['co_reviewed_at'])); ?>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <strong>CO Decision:</strong><br>
                                <?php if (isRejectedByCO($application)): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($application['co_remarks'])): ?>
                        <div class="mt-3">
                            <strong>CO Remarks:</strong><br>
                            <div class="p-3 bg-light rounded">
                                <?php echo nl2br(htmlspecialchars($application['co_remarks'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>CO Review Pending:</strong> This application is still awaiting CO review.
                        </div>
                    <?php endif; ?>
                </div>
                <?php elseif ($application['category'] === 'gazetted'): ?>
                <div class="application-section">
                    <h4 class="section-title">
                        <i class="fas fa-star me-2"></i>Workflow Status
                    </h4>
                    <div class="alert alert-info">
                        <strong>Gazetted Employee:</strong> This application bypassed CO review and came directly to you for processing.
                    </div>
                </div>
                <?php endif; ?>

                <!-- Employee Remarks -->
                <?php if (!empty($application['remarks'])): ?>
                <div class="application-section">
                    <h4 class="section-title">
                        <i class="fas fa-comment me-2"></i>Employee Remarks
                    </h4>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($application['remarks'])); ?></p>
                </div>
                <?php endif; ?>

                <!-- Dealer Review History -->
                <?php if (!empty($application['dealer_remarks']) || !empty($application['dealer_reviewed_at'])): ?>
                <div class="application-section">
                    <h4 class="section-title">
                        <i class="fas fa-history me-2"></i>Dealer Review History
                    </h4>
                    <?php if (!empty($application['dealer_reviewed_at'])): ?>
                    <div class="mb-2">
                        <strong>Dealer Review Date:</strong> <?php echo date('d-m-Y H:i', strtotime($application['dealer_reviewed_at'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($application['dealer_remarks'])): ?>
                    <div>
                        <strong>Dealer Remarks:</strong><br>
                        <?php echo nl2br(htmlspecialchars($application['dealer_remarks'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Review Actions Sidebar -->
            <div class="col-lg-4">
                <div class="review-actions">
                    <?php if ($application['current_status'] === 'dealer_pending'): ?>
                    <!-- Review Form -->
                    <h5 class="text-info mb-3">
                        <i class="fas fa-gavel me-2"></i>Dealer Decision
                    </h5>
                    
                    <form method="POST" id="reviewForm">
                        <div class="mb-3">
                            <label for="dealer_remarks" class="form-label">Your Remarks <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="dealer_remarks" name="dealer_remarks" rows="4" required
                                      placeholder="Please provide your detailed comments for this application..."></textarea>
                            <div class="form-text">Your remarks will be visible to the AWO and employee.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-lg" 
                                    onclick="return confirm('Are you sure you want to APPROVE this application? It will be forwarded to the AWO.')">
                                <i class="fas fa-check me-2"></i>Approve & Forward to AWO
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg"
                                    onclick="return confirm('Are you sure you want to REJECT this application? The employee will be notified.')">
                                <i class="fas fa-times me-2"></i>Reject Application
                            </button>
                        </div>
                    </form>
                    
                    <?php else: ?>
                    <!-- Already Reviewed -->
                    <h5 class="text-info mb-3">
                        <i class="fas fa-check-circle me-2"></i>Review Completed
                    </h5>
                    <div class="alert alert-info">
                        <strong>Status:</strong> <?php echo getReadableStatus($application['current_status'], $application); ?><br>
                        <?php if (!empty($application['dealer_reviewed_at'])): ?>
                        <strong>Reviewed:</strong> <?php echo date('d-m-Y H:i', strtotime($application['dealer_reviewed_at'])); ?>
                        <?php elseif (!empty($application['co_reviewed_at']) && isRejectedByCO($application)): ?>
                        <strong>Rejected by CO:</strong> <?php echo date('d-m-Y H:i', strtotime($application['co_reviewed_at'])); ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Navigation -->
                    <hr>
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <a href="dashboard.php?filter=pending" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>All Pending Applications
                        </a>
                    </div>
                    
                    <!-- Help -->
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6><i class="fas fa-info-circle me-2"></i>Review Guidelines</h6>
                        <small class="text-muted">
                            • Verify all employee information is accurate<br>
                            • Check uploaded documents for clarity<br>
                            • Review CO approval (for non-gazetted)<br>
                            • Ensure all required fields are complete<br>
                            • Provide clear, detailed remarks for your decision
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Application Not Found -->
        <div class="text-center py-5">
            <i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i>
            <h3>Application Not Found</h3>
            <p class="text-muted">The requested application could not be found or is not available for review.</p>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('reviewForm')?.addEventListener('submit', function(e) {
            const remarks = document.getElementById('dealer_remarks').value.trim();
            if (remarks.length < 10) {
                e.preventDefault();
                alert('Please provide detailed remarks (at least 10 characters).');
                return false;
            }
        });
    </script>
    
    <style>
        .bg-purple { background-color: #6f42c1 !important; }
        .bg-orange { background-color: #fd7e14 !important; }
    </style>
</body>
</html>