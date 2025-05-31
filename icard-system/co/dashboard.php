<?php
session_start();
require_once '../config/database.php';
$conn = getDbConnection();

// Check if user is logged in and is a CO
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'co') {
    header('Location: ../auth/login.php');
    exit();
}

// Get database connection
$conn = getDbConnection();

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get logged-in CO's email from system_users table
$logged_in_user_id = $_SESSION['user_id'];
$co_email_sql = "SELECT email, full_name FROM system_users WHERE id = ?";
$co_email_stmt = $conn->prepare($co_email_sql);
$co_email_stmt->bind_param('i', $logged_in_user_id);
$co_email_stmt->execute();
$co_email_result = $co_email_stmt->get_result();

$logged_in_co_email = '';
$co_full_name = '';
if ($co_email_result && $co_email_result->num_rows > 0) {
    $co_data = $co_email_result->fetch_assoc();
    $logged_in_co_email = $co_data['email'];
    $co_full_name = $co_data['full_name'];
}
$co_email_stmt->close();

// Get statistics for this specific CO
$stats_sql = "SELECT 
    COUNT(CASE WHEN a.current_status = 'co_pending' THEN 1 END) as pending,
    COUNT(CASE WHEN a.current_status = 'dealer_pending' AND a.co_reviewed_at IS NOT NULL THEN 1 END) as approved,
    COUNT(CASE WHEN a.current_status = 'rejected' AND a.co_reviewed_at IS NOT NULL THEN 1 END) as rejected,
    COUNT(CASE WHEN a.co_reviewed_at IS NOT NULL THEN 1 END) as total_reviewed
    FROM applications a 
    JOIN employees e ON a.hrms_id = e.hrms_id 
    LEFT JOIN controlling_officers co ON a.controlling_officer_id = co.id
    WHERE e.category = 'non_gazetted' AND co.email = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('s', $logged_in_co_email);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Build query based on filter
$where_conditions = ["e.category = 'non_gazetted'", "co.email = ?"];
$params = [$logged_in_co_email];
$param_types = 's';

switch($filter) {
    case 'pending':
        $where_conditions[] = "a.current_status = 'co_pending'";
        break;
    case 'approved':
        $where_conditions[] = "a.current_status = 'dealer_pending' AND a.co_reviewed_at IS NOT NULL";
        break;
    case 'rejected':
        $where_conditions[] = "a.current_status = 'rejected' AND a.co_reviewed_at IS NOT NULL";
        break;
    case 'reviewed':
        $where_conditions[] = "a.co_reviewed_at IS NOT NULL";
        break;
    case 'all':
    default:
        // No additional conditions for 'all'
        break;
}

$where_clause = implode(' AND ', $where_conditions);

// Get applications based on filter
$applications_sql = "SELECT a.*, e.name, e.emp_number, e.dob, e.category, d.name as department_name, co.name as co_name
                     FROM applications a 
                     JOIN employees e ON a.hrms_id = e.hrms_id 
                     LEFT JOIN departments d ON e.department_id = d.id
                     LEFT JOIN controlling_officers co ON a.controlling_officer_id = co.id
                     WHERE $where_clause
                     ORDER BY a.created_at DESC";

$applications_stmt = $conn->prepare($applications_sql);
$applications_stmt->bind_param($param_types, ...$params);
$applications_stmt->execute();
$applications_result = $applications_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CO Dashboard - Railway I-Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #2E7D32;
            --light-green: #4CAF50;
            --dark-green: #1B5E20;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-green), var(--light-green));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            background: #f8f9fa;
            min-height: calc(100vh - 76px);
            border-right: 2px solid #e9ecef;
            padding: 0;
        }
        
        .sidebar .nav-link {
            color: #495057;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: var(--primary-green);
            color: white;
            transform: translateX(5px);
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
        }
        
        .stat-card.active {
            background: var(--primary-green);
            color: white;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .pending-icon { background: linear-gradient(135deg, #FF9800, #F57C00); }
        .approved-icon { background: linear-gradient(135deg, #4CAF50, #2E7D32); }
        .rejected-icon { background: linear-gradient(135deg, #F44336, #C62828); }
        .reviewed-icon { background: linear-gradient(135deg, #2196F3, #1565C0); }
        
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .status-pending { background: #FFF3E0; color: #F57C00; }
        .status-approved { background: #E8F5E8; color: #2E7D32; }
        .status-rejected { background: #FFEBEE; color: #C62828; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="#">
                <i class="fas fa-id-card me-2"></i>CO Panel
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    Welcome, <?php echo htmlspecialchars($co_full_name); ?>
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <nav class="nav flex-column pt-3">
                    <a class="nav-link <?php echo !isset($_GET['view']) ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link <?php echo isset($_GET['view']) && $_GET['view'] == 'pending' ? 'active' : ''; ?>" href="dashboard.php?filter=pending">
                        <i class="fas fa-clock me-2"></i>Pending Reviews
                    </a>
                    <a class="nav-link <?php echo isset($_GET['view']) && $_GET['view'] == 'reviewed' ? 'active' : ''; ?>" href="dashboard.php?filter=reviewed">
                        <i class="fas fa-check-circle me-2"></i>Reviewed Applications
                    </a>
                    <a class="nav-link <?php echo isset($_GET['view']) && $_GET['view'] == 'all' ? 'active' : ''; ?>" href="dashboard.php?filter=all">
                        <i class="fas fa-list me-2"></i>All Applications
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="fw-bold text-dark">CO Dashboard</h2>
                        <div class="text-muted">
                            <i class="fas fa-calendar-alt me-1"></i>
                            Today: <?php echo date('d-m-Y'); ?>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <a href="dashboard.php?filter=pending" class="stat-card d-block p-4 <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon pending-icon me-3">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <div class="fs-3 fw-bold"><?php echo $stats['pending']; ?></div>
                                        <div class="text-muted small">PENDING REVIEW</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="dashboard.php?filter=approved" class="stat-card d-block p-4 <?php echo $filter == 'approved' ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon approved-icon me-3">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div>
                                        <div class="fs-3 fw-bold"><?php echo $stats['approved']; ?></div>
                                        <div class="text-muted small">TOTAL APPROVED</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="dashboard.php?filter=rejected" class="stat-card d-block p-4 <?php echo $filter == 'rejected' ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon rejected-icon me-3">
                                        <i class="fas fa-times"></i>
                                    </div>
                                    <div>
                                        <div class="fs-3 fw-bold"><?php echo $stats['rejected']; ?></div>
                                        <div class="text-muted small">TOTAL REJECTED</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="dashboard.php?filter=reviewed" class="stat-card d-block p-4 <?php echo $filter == 'reviewed' ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon reviewed-icon me-3">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <div>
                                        <div class="fs-3 fw-bold"><?php echo $stats['total_reviewed']; ?></div>
                                        <div class="text-muted small">TOTAL REVIEWED</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Applications Table -->
                    <div class="table-container">
                        <div class="p-4 border-bottom bg-light">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                <?php 
                                switch($filter) {
                                    case 'pending': echo 'Applications Pending Review'; break;
                                    case 'approved': echo 'Approved Applications'; break;
                                    case 'rejected': echo 'Rejected Applications'; break;
                                    case 'reviewed': echo 'All Reviewed Applications'; break;
                                    default: echo 'All Applications Received'; break;
                                }
                                ?>
                            </h5>
                        </div>
                        
                        <?php if ($applications_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Application ID</th>
                                        <th>Employee Details</th>
                                        <th>Department</th>
                                        <th>Designation</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($app = $applications_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary">#<?php echo $app['id']; ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($app['name']); ?></strong><br>
                                                <small class="text-muted">
                                                    HRMS: <?php echo htmlspecialchars($app['hrms_id']); ?> | 
                                                    Emp: <?php echo htmlspecialchars($app['emp_number']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($app['department_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['designation']); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch($app['current_status']) {
                                                case 'co_pending':
                                                    $status_class = 'status-pending';
                                                    $status_text = 'Pending Review';
                                                    break;
                                                case 'dealer_pending':
                                                    $status_class = 'status-approved';
                                                    $status_text = 'Approved';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'status-rejected';
                                                    $status_text = 'Rejected';
                                                    break;
                                                default:
                                                    $status_text = ucfirst($app['current_status']);
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y H:i', strtotime($app['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($app['current_status'] == 'co_pending'): ?>
                                                <a href="review_application.php?id=<?php echo $app['id']; ?>" 
                                                   class="btn btn-primary btn-action btn-sm">
                                                    <i class="fas fa-eye me-1"></i>Review
                                                </a>
                                            <?php else: ?>
                                                <a href="review_application.php?id=<?php echo $app['id']; ?>" 
                                                   class="btn btn-outline-secondary btn-action btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center p-5">
                            <div class="text-success mb-3">
                                <i class="fas fa-check-circle fa-3x"></i>
                            </div>
                            <h4>All Caught Up!</h4>
                            <p class="text-muted">
                                <?php 
                                switch($filter) {
                                    case 'pending': echo 'There are no applications pending your review at the moment.'; break;
                                    case 'approved': echo 'No approved applications found.'; break;
                                    case 'rejected': echo 'No rejected applications found.'; break;
                                    case 'reviewed': echo 'No reviewed applications found.'; break;
                                    default: echo 'No applications have been assigned to you yet.'; break;
                                }
                                ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$applications_stmt->close();
$conn->close();
?>