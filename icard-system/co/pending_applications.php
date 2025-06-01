<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'co') {
    header('Location: login.php');
    exit();
}

// Get all pending applications
$pending_applications = [];
$total_count = 0;

try {
    $conn = getDbConnection();
    
    // Get applications pending CO review (non-gazetted employees only)
    $sql = "SELECT a.*, e.name, e.emp_number, e.category, d.name as department_name 
            FROM applications a 
            JOIN employees e ON a.hrms_id = e.hrms_id 
            LEFT JOIN departments d ON a.department_id = d.id
            WHERE a.current_status = 'co_pending' 
            AND e.category = 'non_gazetted'
            ORDER BY a.created_at ASC";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pending_applications[] = $row;
        }
        $total_count = count($pending_applications);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log('Pending applications error: ' . $e->getMessage());
    $error = 'Unable to load pending applications.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Applications - CO Panel</title>
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
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="pending_applications.php">
                                <i class="fas fa-clock me-2"></i>Pending Reviews
                                <?php if ($total_count > 0): ?>
                                <span class="badge bg-warning ms-2"><?php echo $total_count; ?></span>
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
                    <h1 class="h2">
                        <i class="fas fa-clock me-2"></i>Pending Applications
                        <span class="badge bg-warning ms-2"><?php echo $total_count; ?></span>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <?php if (!empty($pending_applications)): ?>
                <!-- Applications Table -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Applications Awaiting Your Review (<?php echo $total_count; ?> total)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>App ID</th>
                                        <th>Employee Details</th>
                                        <th>Designation</th>
                                        <th>Department</th>
                                        <th>Contact</th>
                                        <th>Applied Date</th>
                                        <th>Priority</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_applications as $index => $app): ?>
                                    <?php 
                                    // Calculate days pending
                                    $days_pending = floor((time() - strtotime($app['created_at'])) / (60 * 60 * 24));
                                    $priority_class = $days_pending > 7 ? 'danger' : ($days_pending > 3 ? 'warning' : 'info');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo $app['id']; ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($app['name']); ?></strong><br>
                                                <small class="text-muted">
                                                    <i class="fas fa-id-badge me-1"></i><?php echo htmlspecialchars($app['hrms_id']); ?><br>
                                                    <i class="fas fa-hashtag me-1"></i><?php echo htmlspecialchars($app['emp_number']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($app['designation'] ?? 'N/A'); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($app['department_name'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($app['mobile_number'])): ?>
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($app['mobile_number']); ?><br>
                                            <?php endif; ?>
                                            <?php if (!empty($app['email'])): ?>
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($app['email']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <?php echo date('d-m-Y', strtotime($app['created_at'])); ?><br>
                                                <small class="text-muted">
                                                    <?php echo date('H:i', strtotime($app['created_at'])); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $priority_class; ?>">
                                                <?php if ($days_pending > 7): ?>
                                                    High (<?php echo $days_pending; ?> days)
                                                <?php elseif ($days_pending > 3): ?>
                                                    Medium (<?php echo $days_pending; ?> days)
                                                <?php else: ?>
                                                    Normal (<?php echo $days_pending; ?> days)
                                                <?php endif; ?>
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
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
                        <h4>All Applications Reviewed!</h4>
                        <p class="text-muted">
                            There are no applications pending your review at the moment.<br>
                            New applications will appear here automatically.
                        </p>
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-info-circle me-2"></i>Review Guidelines
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <strong>Your Responsibilities:</strong><br>
                                            • Review non-gazetted employee applications only<br>
                                            • Verify employee information and documents<br>
                                            • Approve or reject with clear remarks<br>
                                            • Applications are processed in chronological order
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <strong>Priority Levels:</strong><br>
                                            • <span class="badge bg-danger">High</span> - Pending more than 7 days<br>
                                            • <span class="badge bg-warning">Medium</span> - Pending 4-7 days<br>
                                            • <span class="badge bg-info">Normal</span> - Pending 0-3 days<br>
                                            • Focus on high priority applications first
                                        </small>
                                    </div>
                                </div>
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