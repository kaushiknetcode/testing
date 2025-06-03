<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a Dealer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header('Location: login.php');
    exit();
}

$conn = getDbConnection();

// Get filter and search parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get logged-in dealer info
$logged_in_user_id = $_SESSION['user_id'];
$dealer_info_sql = "SELECT full_name FROM system_users WHERE id = ?";
$dealer_info_stmt = $conn->prepare($dealer_info_sql);
$dealer_info_stmt->bind_param('i', $logged_in_user_id);
$dealer_info_stmt->execute();
$dealer_info_result = $dealer_info_stmt->get_result();

$dealer_full_name = '';
if ($dealer_info_result && $dealer_info_result->num_rows > 0) {
    $dealer_data = $dealer_info_result->fetch_assoc();
    $dealer_full_name = $dealer_data['full_name'];
}
$dealer_info_stmt->close();

// Get statistics for dealer
$stats_sql = "SELECT 
    COUNT(CASE WHEN a.current_status = 'dealer_pending' THEN 1 END) as pending,
    COUNT(CASE WHEN a.current_status = 'awo_pending' AND a.dealer_reviewed_at IS NOT NULL THEN 1 END) as approved,
    COUNT(CASE WHEN a.current_status = 'rejected' AND a.dealer_reviewed_at IS NOT NULL THEN 1 END) as rejected,
    COUNT(CASE WHEN a.dealer_reviewed_at IS NOT NULL THEN 1 END) as total_reviewed
    FROM applications a 
    JOIN employees e ON a.hrms_id = e.hrms_id";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc() ?? ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total_reviewed' => 0];
$stats_stmt->close();

// Build query based on filter and search
$where_conditions = ["1=1"]; // Base condition
$params = [];
$param_types = '';

// Add filter conditions
switch($filter) {
    case 'pending':
        $where_conditions[] = "a.current_status = 'dealer_pending'";
        break;
    case 'approved':
        $where_conditions[] = "a.current_status = 'awo_pending' AND a.dealer_reviewed_at IS NOT NULL";
        break;
    case 'rejected':
        $where_conditions[] = "a.current_status = 'rejected' AND a.dealer_reviewed_at IS NOT NULL";
        break;
    case 'reviewed':
        $where_conditions[] = "a.dealer_reviewed_at IS NOT NULL";
        break;
    case 'gazetted':
        $where_conditions[] = "e.category = 'gazetted'";
        break;
    case 'non_gazetted':
        $where_conditions[] = "e.category = 'non_gazetted'";
        break;
    case 'all':
    default:
        // No additional conditions for 'all'
        break;
}

// Add search conditions
if (!empty($search)) {
    $where_conditions[] = "(e.name LIKE ? OR a.hrms_id LIKE ? OR e.emp_number LIKE ? OR a.id = ? OR d.name LIKE ? OR a.designation LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search, $search_param, $search_param]);
    $param_types .= 'ssssss';
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total
              FROM applications a 
              JOIN employees e ON a.hrms_id = e.hrms_id 
              LEFT JOIN departments d ON e.department_id = d.id
              LEFT JOIN controlling_officers co ON a.controlling_officer_id = co.id
              WHERE $where_clause";

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);
$count_stmt->close();

// Get applications based on filter with pagination
$applications_sql = "SELECT a.*, e.name, e.emp_number, e.dob, e.category, 
                     d.name as department_name, co.name as co_name
                     FROM applications a 
                     JOIN employees e ON a.hrms_id = e.hrms_id 
                     LEFT JOIN departments d ON e.department_id = d.id
                     LEFT JOIN controlling_officers co ON a.controlling_officer_id = co.id
                     WHERE $where_clause
                     ORDER BY a.created_at DESC
                     LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$applications_stmt = $conn->prepare($applications_sql);
if (!empty($params)) {
    $applications_stmt->bind_param($param_types, ...$params);
}
$applications_stmt->execute();
$applications_result = $applications_stmt->get_result();

// Build pagination URLs
function buildPaginationUrl($page, $filter, $search) {
    $url = "dashboard.php?page=$page";
    if ($filter !== 'all') $url .= "&filter=$filter";
    if (!empty($search)) $url .= "&search=" . urlencode($search);
    return $url;
}

function buildFilterUrl($filter, $search) {
    $url = "dashboard.php?filter=$filter";
    if (!empty($search)) $url .= "&search=" . urlencode($search);
    return $url;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer Dashboard - Railway I-Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-cyan: #17a2b8;
            --light-cyan: #20c997;
            --dark-cyan: #138496;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-cyan), var(--light-cyan));
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
            background-color: var(--primary-cyan);
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
            background: var(--primary-cyan);
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
        .gazetted-icon { background: linear-gradient(135deg, #9C27B0, #7B1FA2); }
        .non-gazetted-icon { background: linear-gradient(135deg, #FF5722, #D84315); }
        
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
        
        .search-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: 1px solid #dee2e6;
        }
        
        .pagination .page-link:hover {
            background-color: var(--light-cyan);
            border-color: var(--primary-cyan);
            color: white;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-cyan);
            border-color: var(--primary-cyan);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="#">
                <i class="fas fa-user-cog me-2"></i>Dealer Panel
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    Welcome, <?php echo htmlspecialchars($dealer_full_name); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
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
                    <a class="nav-link <?php echo !isset($_GET['filter']) ? 'active' : ''; ?>" href="<?php echo buildFilterUrl('all', $search); ?>">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'pending') ? 'active' : ''; ?>" href="<?php echo buildFilterUrl('pending', $search); ?>">
                        <i class="fas fa-clock me-2"></i>Pending Review
                    </a>
                    <a class="nav-link <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'gazetted') ? 'active' : ''; ?>" href="<?php echo buildFilterUrl('gazetted', $search); ?>">
                        <i class="fas fa-star me-2"></i>Gazetted Applications
                    </a>
                    <a class="nav-link <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'non_gazetted') ? 'active' : ''; ?>" href="<?php echo buildFilterUrl('non_gazetted', $search); ?>">
                        <i class="fas fa-users me-2"></i>Non-Gazetted Applications
                    </a>
                    <a class="nav-link <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'reviewed') ? 'active' : ''; ?>" href="<?php echo buildFilterUrl('reviewed', $search); ?>">
                        <i class="fas fa-check-circle me-2"></i>Reviewed Applications
                    </a>
                    <hr class="my-2">
                    <a class="nav-link" href="add_employee.php">
                        <i class="fas fa-user-plus me-2"></i>Add New Employee
                    </a>
                    <a class="nav-link" href="revoke_icard.php">
                        <i class="fas fa-ban me-2"></i>Revoke I-Cards
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="fw-bold text-dark">Dealer Dashboard</h2>
                        <div class="text-muted">
                            <i class="fas fa-calendar-alt me-1"></i>
                            Today: <?php echo date('d-m-Y'); ?>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-2">
                            <a href="<?php echo buildFilterUrl('pending', $search); ?>" class="stat-card d-block p-3 <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon pending-icon me-2">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo $stats['pending']; ?></div>
                                        <div class="text-muted small">PENDING</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="<?php echo buildFilterUrl('approved', $search); ?>" class="stat-card d-block p-3 <?php echo $filter == 'approved' ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon approved-icon me-2">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo $stats['approved']; ?></div>
                                        <div class="text-muted small">APPROVED</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="<?php echo buildFilterUrl('rejected', $search); ?>" class="stat-card d-block p-3 <?php echo $filter == 'rejected' ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon rejected-icon me-2">
                                        <i class="fas fa-times"></i>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo $stats['rejected']; ?></div>
                                        <div class="text-muted small">REJECTED</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="<?php echo buildFilterUrl('reviewed', $search); ?>" class="stat-card d-block p-3 <?php echo $filter == 'reviewed' ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon reviewed-icon me-2">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo $stats['total_reviewed']; ?></div>
                                        <div class="text-muted small">REVIEWED</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="<?php echo buildFilterUrl('gazetted', $search); ?>" class="stat-card d-block p-3 <?php echo $filter == 'gazetted' ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon gazetted-icon me-2">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold">GAZ</div>
                                        <div class="text-muted small">GAZETTED</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="<?php echo buildFilterUrl('non_gazetted', $search); ?>" class="stat-card d-block p-3 <?php echo $filter == 'non_gazetted' ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon non-gazetted-icon me-2">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold">NG</div>
                                        <div class="text-muted small">NON-GAZ</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Search Section -->
                    <div class="search-container">
                        <form method="GET" action="dashboard.php" class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label for="search" class="form-label fw-bold">
                                    <i class="fas fa-search me-1"></i>Search Applications
                                </label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Search by name, HRMS ID, employee number, application ID, department, or designation...">
                                <?php if ($filter !== 'all'): ?>
                                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                            </div>
                            <div class="col-md-2">
                                <?php if (!empty($search) || $filter !== 'all'): ?>
                                    <a href="dashboard.php" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <?php if (!empty($search)): ?>
                        <div class="mt-3">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-search me-1"></i>
                                Search results for: "<strong><?php echo htmlspecialchars($search); ?></strong>"
                                (<?php echo $total_records; ?> found)
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Applications Table -->
                    <div class="table-container">
                        <div class="p-4 border-bottom bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                <?php 
                                switch($filter) {
                                    case 'pending': echo 'Applications Pending Review'; break;
                                    case 'approved': echo 'Approved Applications'; break;
                                    case 'rejected': echo 'Rejected Applications'; break;
                                    case 'reviewed': echo 'All Reviewed Applications'; break;
                                    case 'gazetted': echo 'Gazetted Employee Applications'; break;
                                    case 'non_gazetted': echo 'Non-Gazetted Employee Applications'; break;
                                    default: echo 'All Applications'; break;
                                }
                                ?>
                            </h5>
                            <?php if ($total_records > 0): ?>
                            <div class="pagination-info">
                                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_records); ?> 
                                of <?php echo $total_records; ?> applications
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($applications_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Application ID</th>
                                        <th>Employee Details</th>
                                        <th>Category</th>
                                        <th>Department</th>
                                        <th>CO Review</th>
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
                                        <td>
                                            <?php if ($app['category'] === 'gazetted'): ?>
                                                <span class="badge bg-purple text-white">Gazetted</span>
                                            <?php else: ?>
                                                <span class="badge bg-orange text-white">Non-Gazetted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($app['department_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($app['category'] === 'gazetted'): ?>
                                                <small class="text-muted">Direct to Dealer</small>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($app['co_name'] ?? 'N/A'); ?>
                                                <?php if ($app['co_reviewed_at']): ?>
                                                    <br><small class="text-success">Reviewed ✓</small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch($app['current_status']) {
                                                case 'dealer_pending':
                                                    $status_class = 'status-pending';
                                                    $status_text = 'Pending Review';
                                                    break;
                                                case 'awo_pending':
                                                    $status_class = 'status-approved';
                                                    $status_text = 'Forwarded to AWO';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'status-rejected';
                                                    $status_text = 'Rejected';
                                                    break;
                                                case 'approved':
                                                    $status_class = 'status-approved';
                                                    $status_text = 'I-Card Generated';
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
                                            <?php if ($app['current_status'] == 'dealer_pending'): ?>
                                                <a href="process_application.php?id=<?php echo $app['id']; ?>" 
                                                   class="btn btn-primary btn-action btn-sm">
                                                    <i class="fas fa-eye me-1"></i>Review
                                                </a>
                                            <?php else: ?>
                                                <a href="process_application.php?id=<?php echo $app['id']; ?>" 
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
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="p-4 border-top bg-light">
                            <nav aria-label="Applications pagination">
                                <ul class="pagination justify-content-center mb-0">
                                    <!-- Previous Page -->
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo buildPaginationUrl($page - 1, $filter, $search); ?>">
                                            <i class="fas fa-chevron-left me-1"></i>Previous
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <!-- Page Numbers -->
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo buildPaginationUrl(1, $filter, $search); ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                    <?php endif;
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo buildPaginationUrl($i, $filter, $search); ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor;
                                    
                                    if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo buildPaginationUrl($total_pages, $filter, $search); ?>"><?php echo $total_pages; ?></a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Next Page -->
                                    <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo buildPaginationUrl($page + 1, $filter, $search); ?>">
                                            Next<i class="fas fa-chevron-right ms-1"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <div class="text-center p-5">
                            <div class="text-info mb-3">
                                <i class="fas fa-search fa-3x"></i>
                            </div>
                            <h4><?php echo !empty($search) ? 'No Results Found' : 'All Caught Up!'; ?></h4>
                            <p class="text-muted">
                                <?php 
                                if (!empty($search)) {
                                    echo "No applications found matching your search criteria.";
                                } else {
                                    switch($filter) {
                                        case 'pending': echo 'There are no applications pending your review at the moment.'; break;
                                        case 'approved': echo 'No approved applications found.'; break;
                                        case 'rejected': echo 'No rejected applications found.'; break;
                                        case 'reviewed': echo 'No reviewed applications found.'; break;
                                        case 'gazetted': echo 'No gazetted applications found.'; break;
                                        case 'non_gazetted': echo 'No non-gazetted applications found.'; break;
                                        default: echo 'No applications have been submitted yet.'; break;
                                    }
                                }
                                ?>
                            </p>
                            <?php if (!empty($search)): ?>
                            <a href="dashboard.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-times me-1"></i>Clear Search
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus search input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            if (searchInput && !searchInput.value) {
                // Only focus if search is empty to avoid disrupting existing searches
                searchInput.focus();
            }
        });
        
        // Enhanced search functionality
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.form.submit();
            }
        });
    </script>
    
    <style>
        .bg-purple { background-color: #6f42c1 !important; }
        .bg-orange { background-color: #fd7e14 !important; }
    </style>
</body>
</html>

<?php
$applications_stmt->close();
$conn->close();
?>