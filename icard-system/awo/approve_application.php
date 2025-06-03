<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'awo') {
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
        
        // Get application with employee and related details
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
        error_log('AWO approve application error: ' . $e->getMessage());
        $error = 'Unable to load application details: ' . $e->getMessage();
    }
} else {
    $error = 'Invalid application ID.';
}

// Handle form submission (Final Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $application) {
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['awo_remarks'] ?? '');
    
    if (in_array($action, ['approve', 'reject'])) {
        if (empty($remarks)) {
            $error = 'Please provide your remarks before ' . ($action === 'approve' ? 'approving' : 'rejecting') . ' the application.';
        } else {
            try {
                $conn = getDbConnection();
                
                if ($action === 'approve') {
                    // Begin transaction for approval and I-Card generation
                    $conn->begin_transaction();
                    
                    try {
                        // Generate I-Card number using the sequence
                        $seq_sql = "SELECT sequence FROM icard_sequence WHERE prefix = 'ERKPAW' FOR UPDATE";
                        $seq_result = $conn->query($seq_sql);
                        $seq_row = $seq_result->fetch_assoc();
                        $next_sequence = $seq_row['sequence'];
                        $icard_number = 'ERKPAW/' . str_pad($next_sequence, 5, '0', STR_PAD_LEFT);
                        
                        // Update sequence
                        $update_seq_sql = "UPDATE icard_sequence SET sequence = sequence + 1 WHERE prefix = 'ERKPAW'";
                        $conn->query($update_seq_sql);
                        
                        // Update application status
                        $update_sql = "UPDATE applications SET 
                                      current_status = 'approved', 
                                      awo_remarks = ?, 
                                      awo_reviewed_at = NOW()
                                      WHERE id = ? AND current_status = 'awo_pending'";
                        
                        $stmt = $conn->prepare($update_sql);
                        $stmt->bind_param('si', $remarks, $application_id);
                        
                        if (!$stmt->execute() || $stmt->affected_rows === 0) {
                            throw new Exception('Failed to update application status');
                        }
                        $stmt->close();
                        
                        // Create I-Card record
                        $icard_sql = "INSERT INTO icards (
                                     hrms_id, application_id, icard_number, pdf_path, 
                                     status, generated_at, is_current
                                     ) VALUES (?, ?, ?, ?, 'active', NOW(), 1)";
                        
                        // Generate PDF filename (we'll implement PDF generation later)
                        $pdf_filename = $icard_number . '_' . $application['hrms_id'] . '.pdf';
                        
                        $icard_stmt = $conn->prepare($icard_sql);
                        $icard_stmt->bind_param('siss', 
                            $application['hrms_id'], 
                            $application_id, 
                            $icard_number, 
                            $pdf_filename
                        );
                        
                        if (!$icard_stmt->execute()) {
                            throw new Exception('Failed to create I-Card record');
                        }
                        $icard_stmt->close();
                        
                        // Commit transaction
                        $conn->commit();
                        
                        $success = "Application approved successfully! I-Card Number: $icard_number has been generated.";
                        
                        // Log the action
                        $awo_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown AWO';
                        $log_entry = date('Y-m-d H:i:s') . " - AWO " . $awo_name . " approved application ID: " . 
                                    $application_id . " (HRMS: " . $application['hrms_id'] . ") - I-Card: $icard_number\n";
                        $log_dir = __DIR__ . '/../logs';
                        if (!is_dir($log_dir)) {
                            mkdir($log_dir, 0755, true);
                        }
                        file_put_contents($log_dir . '/awo_approvals.log', $log_entry, FILE_APPEND | LOCK_EX);
                        
                        // Update application data for display
                        $application['current_status'] = 'approved';
                        $application['awo_remarks'] = $remarks;
                        $application['awo_reviewed_at'] = date('Y-m-d H:i:s');
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    
                } else {
                    // Reject application
                    $update_sql = "UPDATE applications SET 
                                  current_status = 'rejected', 
                                  awo_remarks = ?, 
                                  awo_reviewed_at = NOW()
                                  WHERE id = ? AND current_status = 'awo_pending'";
                    
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param('si', $remarks, $application_id);
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $success = 'Application rejected successfully.';
                        
                        // Log the action
                        $awo_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown AWO';
                        $log_entry = date('Y-m-d H:i:s') . " - AWO " . $awo_name . " rejected application ID: " . 
                                    $application_id . " (HRMS: " . $application['hrms_id'] . ")\n";
                        $log_dir = __DIR__ . '/../logs';
                        if (!is_dir($log_dir)) {
                            mkdir($log_dir, 0755, true);
                        }
                        file_put_contents($log_dir . '/awo_rejections.log', $log_entry, FILE_APPEND | LOCK_EX);
                        
                        // Update application data for display
                        $application['current_status'] = 'rejected';
                        $application['awo_remarks'] = $remarks;
                        $application['awo_reviewed_at'] = date('Y-m-d H:i:s');
                        
                    } else {
                        $error = 'Failed to reject application. The application may have already been reviewed.';
                    }
                    $stmt->close();
                }
                
                $conn->close();
            } catch (Exception $e) {
                error_log('AWO review action error: ' . $e->getMessage());
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

function getReadableStatus($status, $application_data = null) {
    switch ($status) {
        case 'co_pending': return 'Pending CO Review';
        case 'dealer_pending': return 'Pending Dealer Review';
        case 'awo_pending': return 'Pending Final Approval';
        case 'approved': return 'Approved - I-Card Generated';
        case 'rejected':
            // Check who rejected it
            if ($application_data && !empty($application_data['awo_reviewed_at'])) {
                return 'Rejected by AWO';
            } elseif ($application_data && !empty($application_data['dealer_reviewed_at'])) {
                return 'Rejected by Dealer';
            } elseif ($application_data && !empty($application_data['co_reviewed_at'])) {
                return 'Rejected by CO';
            } else {
                return 'Rejected';
            }
        default: return ucfirst(str_replace('_', ' ', $status));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Approval - AWO Panel</title>
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
            color: #6f42c1;
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
        .approval-notice {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .rejection-notice {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .workflow-timeline {
            position: relative;
            padding-left: 2rem;
        }
        .workflow-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .workflow-step {
            position: relative;
            margin-bottom: 1.5rem;
            padding-left: 1rem;
        }
        .workflow-step::before {
            content: '';
            position: absolute;
            left: -16px;
            top: 6px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
        }
        .workflow-step.completed::before {
            background: #28a745;
        }
        .workflow-step.current::before {
            background: #6f42c1;
            box-shadow: 0 0 0 4px rgba(111, 66, 193, 0.2);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #6f42c1, #8f5fe8);">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-check me-2"></i>AWO Panel
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'AWO'); ?>
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
                                <h3 class="text-purple mb-2">
                                    <i class="fas fa-clipboard-check me-2"></i>Final Application Review
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

                <!-- AWO Approval/Rejection Notice -->
                <?php if ($application['current_status'] === 'approved' && !empty($application['awo_reviewed_at'])): ?>
                <div class="approval-notice">
                    <h6 class="text-success mb-2">
                        <i class="fas fa-check-circle me-2"></i>Application Approved by AWO
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Approved on:</strong> <?php echo date('d-m-Y H:i', strtotime($application['awo_reviewed_at'])); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> I-Card Generated
                        </div>
                    </div>
                    <?php if (!empty($application['awo_remarks'])): ?>
                    <div class="mt-2">
                        <strong>AWO Remarks:</strong><br>
                        <em><?php echo nl2br(htmlspecialchars($application['awo_remarks'])); ?></em>
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif ($application['current_status'] === 'rejected' && !empty($application['awo_reviewed_at'])): ?>
                <div class="rejection-notice">
                    <h6 class="text-danger mb-2">
                        <i class="fas fa-times-circle me-2"></i>Application Rejected by AWO
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Rejected on:</strong> <?php echo date('d-m-Y H:i', strtotime($application['awo_reviewed_at'])); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Final Status:</strong> Application Rejected
                        </div>
                    </div>
                    <?php if (!empty($application['awo_remarks'])): ?>
                    <div class="mt-2">
                        <strong>Rejection Reason:</strong><br>
                        <em><?php echo nl2br(htmlspecialchars($application['awo_remarks'])); ?></em>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Workflow Timeline -->
                <div class="application-section">
                    <h4 class="section-title">
                        <i class="fas fa-route me-2"></i>Application Workflow
                    </h4>
                    <div class="workflow-timeline">
                        <div class="workflow-step completed">
                            <div><strong>Employee Submission</strong></div>
                            <small class="text-muted"><?php echo date('d-m-Y H:i', strtotime($application['created_at'])); ?></small>
                        </div>
                        
                        <?php if ($application['category'] === 'non_gazetted'): ?>
                        <div class="workflow-step <?php echo (!empty($application['co_reviewed_at'])) ? 'completed' : 'current'; ?>">
                            <div><strong>CO Review</strong></div>
                            <?php if (!empty($application['co_reviewed_at'])): ?>
                                <small class="text-muted">
                                    Reviewed: <?php echo date('d-m-Y H:i', strtotime($application['co_reviewed_at'])); ?>
                                    <?php if (!empty($application['co_name'])): ?>
                                        by <?php echo htmlspecialchars($application['co_name']); ?>
                                    <?php endif; ?>
                                </small>
                            <?php else: ?>
                                <small class="text-warning">Pending CO Review</small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="workflow-step <?php echo (!empty($application['dealer_reviewed_at'])) ? 'completed' : 'current'; ?>">
                            <div><strong>Dealer Processing</strong></div>
                            <?php if (!empty($application['dealer_reviewed_at'])): ?>
                                <small class="text-muted">Processed: <?php echo date('d-m-Y H:i', strtotime($application['dealer_reviewed_at'])); ?></small>
                            <?php else: ?>
                                <small class="text-warning">Pending Dealer Review</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="workflow-step <?php echo (!empty($application['awo_reviewed_at'])) ? 'completed' : 'current'; ?>">
                            <div><strong>AWO Final Approval</strong></div>
                            <?php if (!empty($application['awo_reviewed_at'])): ?>
                                <small class="text-muted">
                                    <?php echo ($application['current_status'] === 'approved') ? 'Approved' : 'Rejected'; ?>: 
                                    <?php echo date('d-m-Y H:i', strtotime($application['awo_reviewed_at'])); ?>
                                </small>
                            <?php else: ?>
                                <small class="text-primary">Awaiting Your Decision</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

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
                                    <td><?php echo htmlspecialchars($application['mobile_no'] ?? 'N/A'); ?></td>
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
                            <strong>Blood Group:</strong><br>
                            <?php echo htmlspecialchars($application['blood_group'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Height:</strong><br>
                            <?php echo htmlspecialchars($application['height'] ?? 'N/A'); ?> cm
                        </div>
                        <div class="col-md-4">
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($application['email'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <strong>Date of Appointment:</strong><br>
                            <?php echo $application['date_of_appointment'] ? date('d-m-Y', strtotime($application['date_of_appointment'])) : 'N/A'; ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Date of Joining:</strong><br>
                            <?php echo $application['date_of_joining'] ? date('d-m-Y', strtotime($application['date_of_joining'])) : 'N/A'; ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Date of Retirement:</strong><br>
                            <?php echo $application['date_of_retirement'] ? date('d-m-Y', strtotime($application['date_of_retirement'])) : 'N/A'; ?>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <strong>Identification Mark:</strong><br>
                            <?php echo htmlspecialchars($application['identification_mark'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Address:</strong><br>
                            <?php echo htmlspecialchars($application['address_line1'] ?? 'N/A'); ?>
                            <?php if (!empty($application['address_line2'])): ?>
                                <br><?php echo htmlspecialchars($application['address_line2']); ?>
                            <?php endif; ?>
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

                <!-- Previous Review Comments -->
                <?php if (!empty($application['co_remarks']) || !empty($application['dealer_remarks'])): ?>
                <div class="application-section">
                    <h4 class="section-title">
                        <i class="fas fa-comments me-2"></i>Previous Review Comments
                    </h4>
                    
                    <?php if (!empty($application['co_remarks'])): ?>
                    <div class="mb-3">
                        <h6 class="text-success">CO Remarks:</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($application['co_remarks'])); ?>
                        </div>
                        <small class="text-muted">
                            By: <?php echo htmlspecialchars($application['co_name'] ?? 'Unknown CO'); ?> | 
                            Date: <?php echo date('d-m-Y H:i', strtotime($application['co_reviewed_at'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($application['dealer_remarks'])): ?>
                    <div class="mb-3">
                        <h6 class="text-info">Dealer Remarks:</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($application['dealer_remarks'])); ?>
                        </div>
                        <small class="text-muted">
                            Date: <?php echo date('d-m-Y H:i', strtotime($application['dealer_reviewed_at'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
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
            </div>

            <!-- Review Actions Sidebar -->
            <div class="col-lg-4">
                <div class="review-actions">
                    <?php if ($application['current_status'] === 'awo_pending'): ?>
                    <!-- Review Form -->
                    <h5 class="text-purple mb-3">
                        <i class="fas fa-gavel me-2"></i>Final Decision
                    </h5>
                    
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Important:</strong>
                        This is the final approval step. Approving will generate the I-Card immediately.
                    </div>
                    
                    <form method="POST" id="reviewForm">
                        <div class="mb-3">
                            <label for="awo_remarks" class="form-label">Your Decision Remarks <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="awo_remarks" name="awo_remarks" rows="4" required
                                      placeholder="Please provide your detailed comments for this final decision..."></textarea>
                            <div class="form-text">Your remarks will be permanently recorded and visible to all stakeholders.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-lg" 
                                    onclick="return confirm('Are you sure you want to APPROVE this application? This will generate the I-Card immediately and cannot be undone.')">
                                <i class="fas fa-check me-2"></i>Final Approval & Generate I-Card
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg"
                                    onclick="return confirm('Are you sure you want to REJECT this application? This is the final rejection and the employee will need to reapply.')">
                                <i class="fas fa-times me-2"></i>Final Rejection
                            </button>
                        </div>
                    </form>
                    
                    <?php else: ?>
                    <!-- Already Reviewed -->
                    <h5 class="text-purple mb-3">
                        <i class="fas fa-check-circle me-2"></i>Review Completed
                    </h5>
                    <div class="alert alert-info">
                        <strong>Status:</strong> <?php echo getReadableStatus($application['current_status'], $application); ?><br>
                        <strong>Reviewed:</strong> <?php echo date('d-m-Y H:i', strtotime($application['awo_reviewed_at'])); ?>
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
                        <h6><i class="fas fa-info-circle me-2"></i>AWO Guidelines</h6>
                        <small class="text-muted">
                            • Verify all information is accurate and complete<br>
                            • Check all previous review comments<br>
                            • Ensure documents are clear and valid<br>
                            • Approval generates I-Card immediately<br>
                            • Provide clear, detailed remarks for decision
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
            const remarks = document.getElementById('awo_remarks').value.trim();
            if (remarks.length < 15) {
                e.preventDefault();
                alert('Please provide detailed remarks (at least 15 characters).');
                return false;
            }
        });
    </script>
    
    <style>
        .text-purple { color: #6f42c1 !important; }
        .bg-purple { background-color: #6f42c1 !important; }
        .bg-orange { background-color: #fd7e14 !important; }
    </style>
</body>
</html>