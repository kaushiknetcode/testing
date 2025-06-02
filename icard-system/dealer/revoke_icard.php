<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';
$search_results = [];
$search_term = '';

// Handle search for I-Cards
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    
    try {
        $conn = getDbConnection();
        
        // Search for active I-Cards
        $search_sql = "SELECT i.*, e.name, e.emp_number, e.category, d.name as department_name
                       FROM icards i
                       JOIN employees e ON i.hrms_id = e.hrms_id
                       LEFT JOIN departments d ON e.department_id = d.id
                       WHERE i.is_current = 1 AND i.status = 'active'
                       AND (e.name LIKE ? OR i.hrms_id LIKE ? OR e.emp_number LIKE ? OR i.icard_number LIKE ?)
                       ORDER BY i.generated_at DESC
                       LIMIT 20";
        
        $search_param = "%$search_term%";
        $search_stmt = $conn->prepare($search_sql);
        $search_stmt->bind_param('ssss', $search_param, $search_param, $search_param, $search_param);
        $search_stmt->execute();
        $search_result = $search_stmt->get_result();
        
        while ($row = $search_result->fetch_assoc()) {
            $search_results[] = $row;
        }
        
        $search_stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log('Search I-Cards error: ' . $e->getMessage());
        $error = 'Error searching for I-Cards. Please try again.';
    }
}

// Handle revocation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke') {
    $icard_id = intval($_POST['icard_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $detailed_reason = trim($_POST['detailed_reason'] ?? '');
    
    if (empty($reason)) {
        $error = 'Please select a reason for revocation.';
    } elseif (empty($detailed_reason)) {
        $error = 'Please provide detailed explanation for revocation.';
    } elseif ($icard_id <= 0) {
        $error = 'Invalid I-Card selected.';
    } else {
        try {
            $conn = getDbConnection();
            
            // Get I-Card details
            $icard_sql = "SELECT i.*, e.name FROM icards i JOIN employees e ON i.hrms_id = e.hrms_id WHERE i.id = ? AND i.is_current = 1 AND i.status = 'active'";
            $icard_stmt = $conn->prepare($icard_sql);
            $icard_stmt->bind_param('i', $icard_id);
            $icard_stmt->execute();
            $icard_result = $icard_stmt->get_result();
            
            if ($icard_result->num_rows === 1) {
                $icard_data = $icard_result->fetch_assoc();
                
                // Create revocation request
                $request_sql = "INSERT INTO icard_requests (hrms_id, original_icard_id, request_type, reason, status, created_at) 
                               VALUES (?, ?, 'revoke', ?, 'pending', NOW())";
                $request_stmt = $conn->prepare($request_sql);
                $full_reason = "Reason: $reason\n\nDetails: $detailed_reason";
                $request_stmt->bind_param('sis', $icard_data['hrms_id'], $icard_id, $full_reason);
                
                if ($request_stmt->execute()) {
                    $success = "Revocation request submitted successfully for I-Card: " . $icard_data['icard_number'] . 
                              " (Employee: " . $icard_data['name'] . "). Request forwarded to AWO for approval.";
                    
                    // Log the action
                    $dealer_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown Dealer';
                    $log_entry = date('Y-m-d H:i:s') . " - Dealer " . $dealer_name . " requested revocation for I-Card: " . 
                                $icard_data['icard_number'] . " (HRMS: " . $icard_data['hrms_id'] . ") - Reason: $reason\n";
                    $log_dir = __DIR__ . '/../logs';
                    if (!is_dir($log_dir)) {
                        mkdir($log_dir, 0755, true);
                    }
                    file_put_contents($log_dir . '/dealer_revocations.log', $log_entry, FILE_APPEND | LOCK_EX);
                    
                    // Clear search results after successful submission
                    $search_results = [];
                    $search_term = '';
                } else {
                    $error = 'Failed to submit revocation request. Please try again.';
                }
                $request_stmt->close();
            } else {
                $error = 'I-Card not found or already inactive.';
            }
            $icard_stmt->close();
            $conn->close();
        } catch (Exception $e) {
            error_log('Revoke I-Card error: ' . $e->getMessage());
            $error = 'Error processing revocation request: ' . $e->getMessage();
        }
    }
}

// Get recent revocation requests for display
$recent_requests = [];
try {
    $conn = getDbConnection();
    $recent_sql = "SELECT ir.*, e.name, i.icard_number 
                   FROM icard_requests ir
                   JOIN employees e ON ir.hrms_id = e.hrms_id
                   JOIN icards i ON ir.original_icard_id = i.id
                   WHERE ir.request_type = 'revoke'
                   ORDER BY ir.created_at DESC
                   LIMIT 10";
    $recent_result = $conn->query($recent_sql);
    if ($recent_result) {
        while ($row = $recent_result->fetch_assoc()) {
            $recent_requests[] = $row;
        }
    }
    $conn->close();
} catch (Exception $e) {
    error_log('Recent requests error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revoke I-Cards - Dealer Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .search-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }
        .results-section {
            background: white;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }
        .icard-item {
            border-bottom: 1px solid #e9ecef;
            padding: 1rem;
            transition: background-color 0.2s;
        }
        .icard-item:hover {
            background-color: #f8f9fa;
        }
        .icard-item:last-child {
            border-bottom: none;
        }
        .revoke-form {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
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

    <div class="container py-4">
        <!-- Header -->
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center">
                <h2 class="text-danger mb-2">
                    <i class="fas fa-ban me-2"></i>Revoke I-Cards
                </h2>
                <p class="text-muted">
                    Search for active I-Cards and submit revocation requests
                </p>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Search Section -->
            <div class="col-lg-8">
                <div class="search-section">
                    <h4 class="text-info mb-3">
                        <i class="fas fa-search me-2"></i>Search Active I-Cards
                    </h4>
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search_term); ?>"
                                       placeholder="Search by name, HRMS ID, employee number, or I-Card number..."
                                       required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Search I-Cards
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Search Results -->
                <?php if (!empty($search_term)): ?>
                <div class="results-section">
                    <div class="p-3 bg-light border-bottom">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Search Results 
                            <span class="badge bg-info"><?php echo count($search_results); ?> found</span>
                        </h5>
                    </div>
                    
                    <?php if (!empty($search_results)): ?>
                        <?php foreach ($search_results as $icard): ?>
                        <div class="icard-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <strong><?php echo htmlspecialchars($icard['name']); ?></strong>
                                        <span class="badge bg-<?php echo $icard['category'] === 'gazetted' ? 'purple' : 'orange'; ?> text-white ms-2">
                                            <?php echo ucwords(str_replace('_', ' ', $icard['category'])); ?>
                                        </span>
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <small class="text-muted">
                                                <strong>HRMS ID:</strong> <?php echo htmlspecialchars($icard['hrms_id']); ?><br>
                                                <strong>Emp Number:</strong> <?php echo htmlspecialchars($icard['emp_number']); ?><br>
                                                <strong>Department:</strong> <?php echo htmlspecialchars($icard['department_name'] ?? 'N/A'); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">
                                                <strong>I-Card Number:</strong> <?php echo htmlspecialchars($icard['icard_number']); ?><br>
                                                <strong>Generated:</strong> <?php echo date('d-m-Y', strtotime($icard['generated_at'])); ?><br>
                                                <strong>Status:</strong> <span class="text-success">Active</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="ms-3">
                                    <button type="button" class="btn btn-danger btn-sm" 
                                            onclick="showRevokeForm(<?php echo $icard['id']; ?>, '<?php echo htmlspecialchars($icard['name']); ?>', '<?php echo htmlspecialchars($icard['icard_number']); ?>')">
                                        <i class="fas fa-ban me-1"></i>Revoke
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Revoke Form (Hidden by default) -->
                            <div id="revokeForm<?php echo $icard['id']; ?>" class="revoke-form" style="display: none;">
                                <form method="POST" onsubmit="return confirmRevocation()">
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="icard_id" value="<?php echo $icard['id']; ?>">
                                    
                                    <h6 class="text-danger mb-3">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Revoke I-Card: <?php echo htmlspecialchars($icard['icard_number']); ?>
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Reason for Revocation <span class="text-danger">*</span></label>
                                                <select class="form-select" name="reason" required>
                                                    <option value="">Select Reason</option>
                                                    <option value="Retired">Employee Retired</option>
                                                    <option value="Transferred">Employee Transferred</option>
                                                    <option value="Death">Employee Death</option>
                                                    <option value="Lost">I-Card Lost/Stolen</option>
                                                    <option value="Disciplinary">Disciplinary Action</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Detailed Explanation <span class="text-danger">*</span></label>
                                                <textarea class="form-control" name="detailed_reason" rows="3" 
                                                          placeholder="Provide detailed explanation for revocation..." required></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-ban me-2"></i>Submit Revocation Request
                                        </button>
                                        <button type="button" class="btn btn-secondary" 
                                                onclick="hideRevokeForm(<?php echo $icard['id']; ?>)">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-search fa-3x mb-3"></i>
                            <h5>No Active I-Cards Found</h5>
                            <p>No active I-Cards found matching your search criteria: "<?php echo htmlspecialchars($search_term); ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Requests Sidebar -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-history me-2"></i>Recent Revocation Requests
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($recent_requests)): ?>
                            <?php foreach (array_slice($recent_requests, 0, 5) as $request): ?>
                            <div class="p-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="small"><?php echo htmlspecialchars($request['name']); ?></strong><br>
                                        <small class="text-muted">
                                            I-Card: <?php echo htmlspecialchars($request['icard_number']); ?><br>
                                            Date: <?php echo date('d-m-Y', strtotime($request['created_at'])); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?php echo $request['status'] === 'pending' ? 'warning' : ($request['status'] === 'approved' ? 'success' : 'danger'); ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($recent_requests) > 5): ?>
                            <div class="p-3 text-center">
                                <small class="text-muted">
                                    Showing 5 of <?php echo count($recent_requests); ?> recent requests
                                </small>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p class="small mb-0">No recent revocation requests</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Revocation Guidelines
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="small mb-0">
                            <li>Search for active I-Cards using employee details</li>
                            <li>Select appropriate reason for revocation</li>
                            <li>Provide detailed explanation for the request</li>
                            <li>Revocation requests require AWO approval</li>
                            <li>Only active I-Cards can be revoked</li>
                        </ul>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="d-grid gap-2 mt-4">
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <a href="add_employee.php" class="btn btn-outline-success">
                        <i class="fas fa-user-plus me-2"></i>Add New Employee
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showRevokeForm(icardId, employeeName, icardNumber) {
            // Hide all other revoke forms
            document.querySelectorAll('.revoke-form').forEach(form => {
                form.style.display = 'none';
            });
            
            // Show the selected revoke form
            document.getElementById('revokeForm' + icardId).style.display = 'block';
        }
        
        function hideRevokeForm(icardId) {
            document.getElementById('revokeForm' + icardId).style.display = 'none';
        }
        
        function confirmRevocation() {
            return confirm('Are you sure you want to submit this revocation request? This action will be forwarded to AWO for approval.');
        }
    </script>
    
    <style>
        .bg-purple { background-color: #6f42c1 !important; }
        .bg-orange { background-color: #fd7e14 !important; }
    </style>
</body>
</html>