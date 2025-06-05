<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'awo') {
    header('Location: login.php');
    exit();
}

$request_id = intval($_GET['id'] ?? 0);
$revocation_request = null;
$success = '';
$error = '';

// Get revocation request details
if ($request_id > 0) {
    try {
        $conn = getDbConnection();
        
        // Get revocation request with employee and I-Card details
        $sql = "SELECT ir.*, e.name, e.emp_number, e.category, 
                i.icard_number, i.generated_at, i.status as icard_status,
                d.name as department_name
                FROM icard_requests ir
                JOIN employees e ON ir.hrms_id = e.hrms_id
                JOIN icards i ON ir.original_icard_id = i.id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE ir.id = ? AND ir.request_type = 'revoke'
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows === 1) {
                $revocation_request = $result->fetch_assoc();
            } else {
                $error = 'Revocation request not found.';
            }
            $stmt->close();
        }
        
        $conn->close();
    } catch (Exception $e) {
        error_log('AWO revocation request error: ' . $e->getMessage());
        $error = 'Unable to load revocation request details: ' . $e->getMessage();
    }
} else {
    $error = 'Invalid request ID.';
}

// Handle form submission (Approve/Reject Revocation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $revocation_request) {
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['awo_remarks'] ?? '');
    
    if (in_array($action, ['approve', 'reject'])) {
        if (empty($remarks)) {
            $error = 'Please provide your remarks before ' . ($action === 'approve' ? 'approving' : 'rejecting') . ' the revocation request.';
        } else {
            try {
                $conn = getDbConnection();
                
                if ($action === 'approve') {
                    // Begin transaction for revocation approval
                    $conn->begin_transaction();
                    
                    try {
                        // Update the revocation request status
                        $update_request_sql = "UPDATE icard_requests SET 
                                              status = 'approved', 
                                              awo_remarks = ?, 
                                              awo_reviewed_at = NOW()
                                              WHERE id = ? AND status = 'pending'";
                        
                        $stmt = $conn->prepare($update_request_sql);
                        $stmt->bind_param('si', $remarks, $request_id);
                        
                        if (!$stmt->execute() || $stmt->affected_rows === 0) {
                            throw new Exception('Failed to update revocation request status');
                        }
                        $stmt->close();
                        
                        // Update the I-Card status to revoked
                        $update_icard_sql = "UPDATE icards SET 
                                            status = 'revoked',
                                            is_current = 0,
                                            revoked_at = NOW(),
                                            revocation_reason = ?
                                            WHERE id = ? AND status = 'active'";
                        
                        $revocation_reason = "Revoked by AWO: " . $remarks;
                        $icard_stmt = $conn->prepare($update_icard_sql);
                        $icard_stmt->bind_param('si', $revocation_reason, $revocation_request['original_icard_id']);
                        
                        if (!$icard_stmt->execute()) {
                            throw new Exception('Failed to revoke I-Card');
                        }
                        $icard_stmt->close();
                        
                        // Commit transaction
                        $conn->commit();
                        
                        $success = "Revocation request approved successfully! I-Card " . $revocation_request['icard_number'] . " has been revoked.";
                        
                        // Log the action
                        $awo_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown AWO';
                        $log_entry = date('Y-m-d H:i:s') . " - AWO " . $awo_name . " approved revocation request ID: " . 
                                    $request_id . " - I-Card: " . $revocation_request['icard_number'] . 
                                    " (HRMS: " . $revocation_request['hrms_id'] . ")\n";
                        $log_dir = __DIR__ . '/../logs';
                        if (!is_dir($log_dir)) {
                            mkdir($log_dir, 0755, true);
                        }
                        file_put_contents($log_dir . '/awo_revocations.log', $log_entry, FILE_APPEND | LOCK_EX);
                        
                        // Update request data for display
                        $revocation_request['status'] = 'approved';
                        $revocation_request['awo_remarks'] = $remarks;
                        $revocation_request['awo_reviewed_at'] = date('Y-m-d H:i:s');
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    
                } else {
                    // Reject revocation request
                    $update_sql = "UPDATE icard_requests SET 
                                  status = 'rejected', 
                                  awo_remarks = ?, 
                                  awo_reviewed_at = NOW()
                                  WHERE id = ? AND status = 'pending'";
                    
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param('si', $remarks, $request_id);
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $success = 'Revocation request rejected successfully.';
                        
                        // Log the action
                        $awo_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown AWO';
                        $log_entry = date('Y-m-d H:i:s') . " - AWO " . $awo_name . " rejected revocation request ID: " . 
                                    $request_id . " - I-Card: " . $revocation_request['icard_number'] . 
                                    " (HRMS: " . $revocation_request['hrms_id'] . ")\n";
                        $log_dir = __DIR__ . '/../logs';
                        if (!is_dir($log_dir)) {
                            mkdir($log_dir, 0755, true);
                        }
                        file_put_contents($log_dir . '/awo_revocation_rejections.log', $log_entry, FILE_APPEND | LOCK_EX);
                        
                        // Update request data for display
                        $revocation_request['status'] = 'rejected';
                        $revocation_request['awo_remarks'] = $remarks;
                        $revocation_request['awo_reviewed_at'] = date('Y-m-d H:i:s');
                        
                    } else {
                        $error = 'Failed to reject revocation request. The request may have already been processed.';
                    }
                    $stmt->close();
                }
                
                $conn->close();
            } catch (Exception $e) {
                error_log('AWO revocation action error: ' . $e->getMessage());
                $error = 'An error occurred while processing your request: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Invalid action specified.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Revocation - AWO Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .request-section {
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
        .revocation-actions {
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
        .icard-details {
            background: #fff;
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 1.5rem;
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

        <?php if ($revocation_request): ?>
        <div class="row">
            <!-- Request Details -->
            <div class="col-lg-8">
                <!-- Header -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="text-danger mb-2">
                                    <i class="fas fa-ban me-2"></i>I-Card Revocation Request
                                </h3>
                                <p class="text-muted mb-0">Request ID: #REV-<?php echo $revocation_request['id']; ?></p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?php echo $revocation_request['status'] === 'pending' ? 'warning' : ($revocation_request['status'] === 'approved' ? 'success' : 'danger'); ?> fs-6">
                                    <?php echo ucfirst($revocation_request['status']); ?>
                                </span>
                                <br>
                                <small class="text-muted">
                                    Requested: <?php echo date('d-m-Y H:i', strtotime($revocation_request['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AWO Decision Notice -->
                <?php if ($revocation_request['status'] === 'approved' && !empty($revocation_request['awo_reviewed_at'])): ?>
                <div class="approval-notice">
                    <h6 class="text-success mb-2">
                        <i class="fas fa-check-circle me-2"></i>Revocation Approved by AWO
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Approved on:</strong> <?php echo date('d-m-Y H:i', strtotime($revocation_request['awo_reviewed_at'])); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> I-Card Revoked
                        </div>
                    </div>
                    <?php if (!empty($revocation_request['awo_remarks'])): ?>
                    <div class="mt-2">
                        <strong>AWO Decision Remarks:</strong><br>
                        <em><?php echo nl2br(htmlspecialchars($revocation_request['awo_remarks'])); ?></em>
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif ($revocation_request['status'] === 'rejected' && !empty($revocation_request['awo_reviewed_at'])): ?>
                <div class="rejection-notice">
                    <h6 class="text-danger mb-2">
                        <i class="fas fa-times-circle me-2"></i>Revocation Rejected by AWO
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Rejected on:</strong> <?php echo date('d-m-Y H:i', strtotime($revocation_request['awo_reviewed_at'])); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> Request Rejected
                        </div>
                    </div>
                    <?php if (!empty($revocation_request['awo_remarks'])): ?>
                    <div class="mt-2">
                        <strong>Rejection Reason:</strong><br>
                        <em><?php echo nl2br(htmlspecialchars($revocation_request['awo_remarks'])); ?></em>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- I-Card Information -->
                <div class="icard-details">
                    <h4 class="text-danger mb-3">
                        <i class="fas fa-id-card me-2"></i>I-Card to be Revoked
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>I-Card Number:</strong></td>
                                    <td class="text-danger fw-bold"><?php echo htmlspecialchars($revocation_request['icard_number']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Generated Date:</strong></td>
                                    <td><?php echo date('d-m-Y', strtotime($revocation_request['generated_at'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Current Status:</strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $revocation_request['icard_status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($revocation_request['icard_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Employee Name:</strong></td>
                                    <td><?php echo htmlspecialchars($revocation_request['name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>HRMS ID:</strong></td>
                                    <td><?php echo htmlspecialchars($revocation_request['hrms_id']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Employee Number:</strong></td>
                                    <td><?php echo htmlspecialchars($revocation_request['emp_number']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Employee Information -->
                <div class="request-section">
                    <h4 class="section-title">
                        <i class="fas fa-user me-2"></i>Employee Information
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td><?php echo htmlspecialchars($revocation_request['name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>HRMS ID:</strong></td>
                                    <td><?php echo htmlspecialchars($revocation_request['hrms_id']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Employee Number:</strong></td>
                                    <td><?php echo htmlspecialchars($revocation_request['emp_number']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Category:</strong></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucwords(str_replace('_', ' ', $revocation_request['category'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Department:</strong></td>
                                    <td><?php echo htmlspecialchars($revocation_request['department_name'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Request Date:</strong></td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($revocation_request['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Revocation Request Details -->
                <div class="request-section">
                    <h4 class="section-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Revocation Request Details
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Request Type:</strong><br>
                            <span class="badge bg-danger fs-6"><?php echo ucfirst($revocation_request['request_type']); ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Request Status:</strong><br>
                            <span class="badge bg-<?php echo $revocation_request['status'] === 'pending' ? 'warning' : ($revocation_request['status'] === 'approved' ? 'success' : 'danger'); ?> fs-6">
                                <?php echo ucfirst($revocation_request['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <strong>Revocation Reason:</strong><br>
                        <div class="p-3 bg-light rounded mt-2">
                            <?php echo nl2br(htmlspecialchars($revocation_request['reason'])); ?>
                        </div>
                    </div>
                </div>

                <!-- Dealer Review Comments -->
                <?php if (!empty($revocation_request['dealer_remarks'])): ?>
                <div class="request-section">
                    <h4 class="section-title">
                        <i class="fas fa-comment me-2"></i>Dealer Review Comments
                    </h4>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br(htmlspecialchars($revocation_request['dealer_remarks'])); ?>
                    </div>
                    <?php if (!empty($revocation_request['dealer_reviewed_at'])): ?>
                    <small class="text-muted">
                        Reviewed on: <?php echo date('d-m-Y H:i', strtotime($revocation_request['dealer_reviewed_at'])); ?>
                    </small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- AWO Review History -->
                <?php if (!empty($revocation_request['awo_remarks']) || !empty($revocation_request['awo_reviewed_at'])): ?>
                <div class="request-section">
                    <h4 class="section-title">
                        <i class="fas fa-history me-2"></i>AWO Review History
                    </h4>
                    <?php if (!empty($revocation_request['awo_reviewed_at'])): ?>
                    <div class="mb-2">
                        <strong>AWO Review Date:</strong> <?php echo date('d-m-Y H:i', strtotime($revocation_request['awo_reviewed_at'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($revocation_request['awo_remarks'])): ?>
                    <div>
                        <strong>AWO Decision Remarks:</strong><br>
                        <div class="p-3 bg-light rounded mt-2">
                            <?php echo nl2br(htmlspecialchars($revocation_request['awo_remarks'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Review Actions Sidebar -->
            <div class="col-lg-4">
                <div class="revocation-actions">
                    <?php if ($revocation_request['status'] === 'pending'): ?>
                    <!-- Review Form -->
                    <h5 class="text-danger mb-3">
                        <i class="fas fa-gavel me-2"></i>Revocation Decision
                    </h5>
                    
                    <div class="danger-zone mb-4">
                        <h6 class="text-danger mb-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>Critical Decision
                        </h6>
                        <p class="small mb-0">
                            Approving this request will permanently revoke I-Card <strong><?php echo htmlspecialchars($revocation_request['icard_number']); ?></strong> 
                            and mark it as inactive. This action cannot be undone.
                        </p>
                    </div>
                    
                    <form method="POST" id="revocationForm">
                        <div class="mb-3">
                            <label for="awo_remarks" class="form-label">Your Decision Remarks <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="awo_remarks" name="awo_remarks" rows="4" required
                                      placeholder="Please provide detailed comments for your revocation decision..."></textarea>
                            <div class="form-text">Your remarks will be permanently recorded and visible to all stakeholders.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-danger btn-lg" 
                                    onclick="return confirm('Are you sure you want to APPROVE this revocation? I-Card <?php echo htmlspecialchars($revocation_request['icard_number']); ?> will be permanently revoked and cannot be restored.')">
                                <i class="fas fa-ban me-2"></i>Approve Revocation
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-success btn-lg"
                                    onclick="return confirm('Are you sure you want to REJECT this revocation request? The I-Card will remain active.')">
                                <i class="fas fa-times me-2"></i>Reject Revocation
                            </button>
                        </div>
                    </form>
                    
                    <?php else: ?>
                    <!-- Already Reviewed -->
                    <h5 class="text-purple mb-3">
                        <i class="fas fa-check-circle me-2"></i>Review Completed
                    </h5>
                    <div class="alert alert-info">
                        <strong>Decision:</strong> <?php echo ucfirst($revocation_request['status']); ?><br>
                        <strong>Reviewed:</strong> <?php echo date('d-m-Y H:i', strtotime($revocation_request['awo_reviewed_at'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Navigation -->
                    <hr>
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <a href="dashboard.php?filter=revocation_requests" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>All Revocation Requests
                        </a>
                    </div>
                    
                    <!-- Help -->
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6><i class="fas fa-info-circle me-2"></i>Revocation Guidelines</h6>
                        <small class="text-muted">
                            • Verify the revocation reason is valid<br>
                            • Check dealer review comments<br>
                            • Ensure employee information is correct<br>
                            • Approval permanently revokes the I-Card<br>
                            • Rejection keeps the I-Card active<br>
                            • Provide clear, detailed decision remarks
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Request Not Found -->
        <div class="text-center py-5">
            <i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i>
            <h3>Revocation Request Not Found</h3>
            <p class="text-muted">The requested revocation request could not be found or is not available for review.</p>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('revocationForm')?.addEventListener('submit', function(e) {
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
    </style>
</body>
</html>