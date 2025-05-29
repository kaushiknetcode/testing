<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: login.php');
    exit();
}

// Get some basic statistics
$conn = getDbConnection();

// Count employees
$employeeCount = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
if ($result) {
    $row = $result->fetch_assoc();
    $employeeCount = $row['count'];
}

// Count applications
$applicationCount = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM applications");
if ($result) {
    $row = $result->fetch_assoc();
    $applicationCount = $row['count'];
}

// Count generated I-Cards
$icardCount = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM icards WHERE is_current = 1");
if ($result) {
    $row = $result->fetch_assoc();
    $icardCount = $row['count'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Eastern Railway I-Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-shield-alt me-2"></i>Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
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
                            <a class="nav-link" href="import_employees.php">
                                <i class="fas fa-file-import me-2"></i>Import Employees
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_employees.php">
                                <i class="fas fa-users me-2"></i>Manage Employees
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-user-cog me-2"></i>System Users
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
                    <h1 class="h2">Dashboard</h1>
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
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Employees</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($employeeCount); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                            Applications</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($applicationCount); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
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
                                            Generated I-Cards</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($icardCount); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-id-card fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            System Status</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo testDbConnection() ? 'Online' : 'Offline'; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-server fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <a href="import_employees.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-file-import me-2"></i> Import Employee Data from Excel
                                    </a>
                                    <a href="manage_employees.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-plus me-2"></i> Add New Employee
                                    </a>
                                    <a href="manage_users.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-user-plus me-2"></i> Manage System Users
                                    </a>
                                    <a href="reports.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-chart-line me-2"></i> View System Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">System Information</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Application Version:</strong> <?php echo APP_VERSION; ?></p>
                                <p><strong>Database:</strong> <?php echo DB_NAME; ?></p>
                                <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                                <p><strong>Your IP:</strong> <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
                                <p><strong>Login Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
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