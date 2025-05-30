<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'co') {
    header('Location: login.php');
    exit();
}

// Get pending applications for review
$pending_applications = [];
$total_pending = 0;
$total_reviewed = 0;
$total_approved = 0;
$total_rejected = 0;

try {
    $conn = getDbConnection();
    
    // Get the logged-in CO's email from system_users table
$logged_in_user_id = $_SESSION['user_id'];

// First, get the logged-in CO's email
$co_email_sql = "SELECT email FROM system_users WHERE id = ?";
$co_email_stmt = $conn->prepare($co_email_sql);
$co_email_stmt->bind_param('i', $logged_in_user_id);
$co_email_stmt->execute();
$co_email_result = $co_email_stmt->get_result();

if ($co_email_result && $co_email_result->num_rows > 0) {
    $co_data = $co_email_result->fetch_assoc();
    $logged_in_co_email = $co_data['email'];
} else {
    $logged_in_co_email = '';
}
$co_email_stmt->close();

// Get applications pending CO review (non-gazetted employees only) - FILTERED BY THIS CO
$pending_sql = "SELECT a.*, e.name, e.emp_number, e.category, d.name as department_name 
                FROM applications a 
                JOIN employees e ON a.hrms_id = e.hrms_id 
                LEFT JOIN departments d ON a.department_id = d.id
                JOIN controlling_officers co ON a.controlling_officer_id = co.id
                WHERE a.current_status = 'co_pending' 
                AND e.category = 'non_gazetted'
                AND co.email = ?
                ORDER BY a.created_at ASC";

$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param('s', $logged_in_co_email);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();

if ($pending_result) {
    while ($row = $pending_result->fetch_assoc()) {
        $pending_applications[] = $row;
    }
    $total_pending = count($pending_applications);
}
$pending_stmt->close();
    
    // Get statistics for this CO (filtered by their email)
$stats_sql = "SELECT 
COUNT(CASE WHEN current_status IN ('co_pending') THEN 1 END) as pending,
COUNT(CASE WHEN current_status IN ('dealer_pending', 'awo_pending', 'approved') AND co_reviewed_at IS NOT NULL THEN 1 END) as reviewed,
COUNT(CASE WHEN current_status IN ('dealer_pending', 'awo_pending', 'approved') AND co_reviewed_at IS NOT NULL THEN 1 END) as approved,
COUNT(CASE WHEN current_status = 'rejected' AND co_reviewed_at IS NOT NULL THEN 1 END) as rejected
FROM applications a
JOIN employees e ON a.hrms_id = e.hrms_id
JOIN controlling_officers co ON a.controlling_officer_id = co.id
WHERE e.category = 'non_gazetted'
AND co.email = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('s', $logged_in_co_email);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
    if ($stats_result) {
        $stats = $stats_result->fetch_assoc();
        $total_pending = $stats['pending'];
        $total_reviewed = $stats['reviewed'];
        $total_approved = $stats['approved'];
        $total_rejected = $stats['rejected'];
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log('CO Dashboard error: ' . $e->getMessage());
    $error = 'Unable to load dashboard data. Please try again later.';
}

// Helper functions
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

function getReadableStatus($status) {
    switch ($status) {
        case 'co_pending': return 'Pending Your Review';
        case 'dealer_pending': return 'With Dealer';
        case 'awo_pending': return 'With AWO';
        case 'approved': return 'Approved';
        case 'rejected': return 'Rejected';
        default: return ucfirst($status);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CO Dashboard - Eastern Railway I-Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-check me-2"></i>CO Panel
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Controlling Officer'); ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pending_applications.php">
                                <i class="fas fa-clock me-2"></i>Pending Reviews
                                <?php if ($total_pending > 0): ?>
                                <span class="badge bg-warning ms-2"><?php echo $total_pending; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reviewed_applications.php">
                                <i class="fas fa-check-circle me-2"></i>Reviewed Applications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">CO Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar"></i> Today: <?php echo date('d-m-Y'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Review</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_pending); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Approved</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_approved); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Total Rejected</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_rejected); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Reviewed</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_reviewed); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-eye fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Applications -->
                <?php if (!empty($pending_applications)): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-clock me-2"></i>Applications Pending Your Review (<?php echo count($pending_applications); ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Application ID</th>
                                        <th>Employee Details</th>
                                        <th>Designation</th>
                                        <th>Department</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_applications as $app): ?>
                                    <tr>
                                        <td><strong>#<?php echo $app['id']; ?></strong></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($app['name']); ?></strong><br>
                                                <small class="text-muted">
                                                    HRMS: <?php echo htmlspecialchars($app['hrms_id']); ?> | 
                                                    Emp: <?php echo htmlspecialchars($app['emp_number']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($app['designation'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($app['department_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('d-m-Y', strtotime($app['created_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadgeClass($app['current_status']); ?>">
                                                <?php echo getReadableStatus($app['current_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="review_application.php?id=<?php echo $app['id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>Review
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- No Pending Applications -->
                <div class="card shadow mb-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
                        <h4>All Caught Up!</h4>
                        <p class="text-muted">There are no applications pending your review at the moment.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <a href="pending_applications.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-clock me-2"></i> Review Pending Applications
                                        <?php if ($total_pending > 0): ?>
                                        <span class="badge bg-warning ms-2"><?php echo $total_pending; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <a href="reviewed_applications.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-history me-2"></i> View Review History
                                    </a>
                                    <a href="reports.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-chart-line me-2"></i> View Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Information</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Your Role:</strong> Controlling Officer</p>
                                <p><strong>Responsibility:</strong> Review non-gazetted employee I-Card applications</p>
                                <p><strong>Login Time:</strong> <?php echo $_SESSION['login_time'] ?? date('Y-m-d H:i:s'); ?></p>
                                <p><strong>System Status:</strong> <span class="text-success">Online</span></p>
                                <hr>
                                <small class="text-muted">
                                    <strong>Note:</strong> Only non-gazetted employee applications require your review. 
                                    Gazetted applications go directly to the dealer.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>