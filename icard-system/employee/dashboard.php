<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Simple authentication check without circular dependencies
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee') {
    header('Location: login.php');
    exit();
}

$employee = $_SESSION['employee_data'];
$hrms_id = $employee['hrms_id'];

// Initialize variables
$has_icard = false;
$current_icard = null;
$pending_application = null;
$icard_requests = [];
$show_apply_button = false;
$show_icard_options = false;

try {
    $conn = getDbConnection();
    
    // Check if employee has a current active I-Card
    $icard_sql = "SELECT * FROM icards WHERE hrms_id = ? AND is_current = 1 AND status = 'active' LIMIT 1";
    $icard_stmt = $conn->prepare($icard_sql);
    if ($icard_stmt) {
        $icard_stmt->bind_param('s', $hrms_id);
        $icard_stmt->execute();
        $icard_result = $icard_stmt->get_result();
        
        if ($icard_result && $icard_result->num_rows > 0) {
            $current_icard = $icard_result->fetch_assoc();
            $has_icard = true;
            $show_icard_options = true;
        }
        $icard_stmt->close();
    }
    
    // If no I-Card, check for pending applications
    if (!$has_icard) {
        // Get ALL applications for history (including rejected ones)
        $app_sql = "SELECT a.*, co.name as co_name 
                   FROM applications a 
                   LEFT JOIN controlling_officers co ON a.controlling_officer_id = co.id 
                   WHERE a.hrms_id = ? 
                   ORDER BY a.created_at DESC";
        $app_stmt = $conn->prepare($app_sql);
        if ($app_stmt) {
            $app_stmt->bind_param('s', $hrms_id);
            $app_stmt->execute();
            $app_result = $app_stmt->get_result();
            
            if ($app_result && $app_result->num_rows > 0) {
                $all_applications = [];
                while ($row = $app_result->fetch_assoc()) {
                    $all_applications[] = $row;
                }
                
                // Current application is the most recent one
                $current_application = $all_applications[0];
                
                // History includes all applications
                $application_history = $all_applications;
                
                // Check if we should show apply button or current application
                if ($current_application['current_status'] === 'rejected') {
                    $show_apply_button = true;
                    $pending_application = $current_application; // Show rejection details
                } else {
                    $pending_application = $current_application;
                    
                    // If latest application is approved, check for I-Card
                    if ($current_application['current_status'] === 'approved') {
                        $recheck_stmt = $conn->prepare($icard_sql);
                        if ($recheck_stmt) {
                            $recheck_stmt->bind_param('s', $hrms_id);
                            $recheck_stmt->execute();
                            $recheck_result = $recheck_stmt->get_result();
                            if ($recheck_result && $recheck_result->num_rows > 0) {
                                $current_icard = $recheck_result->fetch_assoc();
                                $has_icard = true;
                                $show_icard_options = true;
                            }
                            $recheck_stmt->close();
                        }
                    }
                }
            } else {
                // No applications - show apply button
                $show_apply_button = true;
            }
            if ($app_stmt) $app_stmt->close();
        }
    }
    
    // Get any pending I-Card requests (Update/Lost/Revoke)
    if ($has_icard) {
        $req_sql = "SELECT * FROM icard_requests WHERE hrms_id = ? AND status = 'pending' ORDER BY created_at DESC";
        $req_stmt = $conn->prepare($req_sql);
        if ($req_stmt) {
            $req_stmt->bind_param('s', $hrms_id);
            $req_stmt->execute();
            $req_result = $req_stmt->get_result();
            while ($row = $req_result->fetch_assoc()) {
                $icard_requests[] = $row;
            }
            $req_stmt->close();
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $error = 'Unable to load dashboard data. Please try again later.';
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

// Function to get readable status text with CO name
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
    <title>Employee Dashboard - Eastern Railway I-Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        .action-btn {
            border-radius: 8px;
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .welcome-section {
            background: linear-gradient(135deg, #0d6efd, #0dcaf0);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
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

    <div class="container-fluid py-4">
        <!-- Success Message from Apply Form -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">Welcome, <?php echo htmlspecialchars($employee['name']); ?>!</h2>
                    <p class="mb-2 opacity-75">
                        <strong>HRMS ID:</strong> <?php echo htmlspecialchars($employee['hrms_id']); ?> | 
                        <strong>Employee No:</strong> <?php echo htmlspecialchars($employee['emp_number']); ?> |
                        <strong>Category:</strong> <?php echo ucwords(str_replace('_', ' ', $employee['category'])); ?>
                    </p>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-clock me-1"></i>Last Login: <?php echo $_SESSION['login_time']; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <i class="fas fa-id-card fa-4x opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <?php if ($has_icard): ?>
                    <!-- I-Card Information -->
                    <div class="dashboard-card card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-id-card me-2"></i>Your Current I-Card
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="text-success">I-Card Number</h6>
                                    <h4 class="mb-3"><?php echo htmlspecialchars($current_icard['icard_number']); ?></h4>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-calendar me-1"></i>
                                        Generated on: <?php echo date('d-m-Y', strtotime($current_icard['generated_at'])); ?>
                                    </p>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <a href="download_icard.php" class="btn btn-primary action-btn me-2">
                                        <i class="fas fa-download me-2"></i>Download I-Card
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- I-Card Management Options -->
                    <div class="dashboard-card card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-cogs me-2"></i>I-Card Management
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <a href="request_update.php" class="btn btn-outline-primary w-100 action-btn">
                                        <i class="fas fa-edit me-2"></i>Update I-Card
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="report_lost.php" class="btn btn-outline-warning w-100 action-btn">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Report Lost
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="request_revoke.php" class="btn btn-outline-danger w-100 action-btn">
                                        <i class="fas fa-ban me-2"></i>Request Revocation
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($pending_application): ?>
                    <!-- Pending Application Status -->
                    <div class="dashboard-card card mb-4">
                        <div class="card-header bg-<?php echo ($pending_application['current_status'] === 'rejected') ? 'danger' : 'warning'; ?> text-<?php echo ($pending_application['current_status'] === 'rejected') ? 'white' : 'dark'; ?>">
                            <h5 class="mb-0">
                                <i class="fas fa-clock me-2"></i>Application Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6>Current Status</h6>
                                    <h4>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($pending_application['current_status']); ?> status-badge">
                                            <?php echo getReadableStatus($pending_application['current_status'], $pending_application['co_name']); ?>
                                        </span>
                                    </h4>
                                    
                                    <?php if ($pending_application['current_status'] === 'rejected'): ?>
                                        <div class="alert alert-danger mt-3 mb-3">
                                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Rejection Reason:</h6>
                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($pending_application['co_remarks'])); ?></p>
                                            <small class="text-muted">
                                                Rejected on: <?php echo date('d-m-Y H:i', strtotime($pending_application['co_reviewed_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="text-muted">
                                            You can apply again after addressing the issues mentioned above.
                                        </p>
                                    <?php else: ?>
                                        <p class="text-muted mt-2 mb-0">
                                            <i class="fas fa-calendar me-1"></i>
                                            Applied on: <?php echo date('d-m-Y H:i', strtotime($pending_application['created_at'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <?php if ($pending_application['current_status'] === 'rejected'): ?>
                                        <a href="apply.php" class="btn btn-primary action-btn">
                                            <i class="fas fa-redo me-2"></i>Apply Again
                                        </a>
                                    <?php elseif ($pending_application['current_status'] === 'draft'): ?>
                                        <a href="apply.php?edit=<?php echo $pending_application['id']; ?>" class="btn btn-primary action-btn">
                                            <i class="fas fa-edit me-2"></i>Complete Application
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- NEW: View Application Button -->
                                    <a href="view_application.php" class="btn btn-outline-primary action-btn mt-2">
                                        <i class="fas fa-eye me-2"></i>View Application
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Application Progress (for non-rejected) -->
                            <?php if ($pending_application['current_status'] !== 'rejected'): ?>
                            <div class="mt-4">
                                <h6>Application Progress</h6>
                                <div class="progress" style="height: 8px;">
                                    <?php
                                    $progress = 0;
                                    switch ($pending_application['current_status']) {
                                        case 'draft': $progress = 10; break;
                                        case 'submitted': $progress = 25; break;
                                        case 'co_pending': $progress = 40; break;
                                        case 'dealer_pending': $progress = 60; break;
                                        case 'awo_pending': $progress = 80; break;
                                        case 'approved': $progress = 100; break;
                                    }
                                    ?>
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <small class="text-muted">Application is <?php echo $progress; ?>% complete</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Application History for Users with Rejected Applications -->
                    <?php if (isset($application_history) && count($application_history) > 1): ?>
                    <div class="dashboard-card card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Previous Applications
                                <button class="btn btn-sm btn-outline-secondary float-end" type="button" data-bs-toggle="collapse" data-bs-target="#previousApplications">
                                    <i class="fas fa-eye me-1"></i>View History (<?php echo count($application_history) - 1; ?> previous)
                                </button>
                            </h5>
                        </div>
                        <div class="collapse" id="previousApplications">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>App #</th>
                                                <th>Applied Date</th>
                                                <th>Status</th>
                                                <th>Reviewed By</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Skip the first one (current application) if showing rejected, or show all if not
                                            $start_index = ($pending_application && $pending_application['current_status'] === 'rejected') ? 1 : 0;
                                            for ($i = $start_index; $i < count($application_history); $i++): 
                                                $app = $application_history[$i];
                                            ?>
                                            <tr>
                                                <td>#<?php echo $app['id']; ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($app['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($app['current_status']); ?>">
                                                        <?php echo getReadableStatus($app['current_status'], $app['co_name']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($app['co_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if (!empty($app['co_remarks'])): ?>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars(substr($app['co_remarks'], 0, 50)); ?>
                                                            <?php if (strlen($app['co_remarks']) > 50): ?>...<?php endif; ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="text-muted">-</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif ($show_apply_button): ?>
                    <!-- Apply for I-Card -->
                    <div class="dashboard-card card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-plus-circle me-2"></i>Apply for I-Card
                            </h5>
                        </div>
                        <div class="card-body text-center py-5">
                            <i class="fas fa-id-card fa-4x text-primary mb-4"></i>
                            <h4>Ready to Get Your I-Card?</h4>
                            <p class="text-muted mb-4">
                                Start your I-Card application process. The system will guide you through each step.
                            </p>
                            <a href="apply.php" class="btn btn-primary btn-lg action-btn">
                                <i class="fas fa-file-alt me-2"></i>Start Application
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Pending Requests -->
                <?php if (!empty($icard_requests)): ?>
                    <div class="dashboard-card card mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-hourglass-half me-2"></i>Pending Requests
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($icard_requests as $request): ?>
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <div>
                                        <strong><?php echo ucwords($request['request_type']); ?> Request</strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('d-m-Y H:i', strtotime($request['created_at'])); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-warning">Pending</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Info -->
                <div class="dashboard-card card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Quick Information
                        </h6>
                    </div>
                    <div class="card-body">
                        
                        <?php if (!empty($employee['designation'])): ?>
                        <div class="mb-3">
                            <strong>Designation:</strong>
                            <br>
                            <span class="text-muted"><?php echo htmlspecialchars($employee['designation']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($employee['mobile_no'])): ?>
                        <div class="mb-3">
                            <strong>Mobile:</strong>
                            <br>
                            <span class="text-muted"><?php echo htmlspecialchars($employee['mobile_no']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-0">
                            <strong>Date of Birth:</strong>
                            <br>
                            <span class="text-muted"><?php echo date('d-m-Y', strtotime($employee['dob'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Help & Support -->
                <div class="dashboard-card card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-question-circle me-2"></i>Need Help?
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">
                            If you need assistance with your I-Card application or have any questions, 
                            please contact the system administrator or your department head.
                        </p>
                        <div class="d-grid gap-2">
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-phone me-1"></i>Contact Support
                            </a>
                            <a href="#" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-book me-1"></i>User Guide
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to cards
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>