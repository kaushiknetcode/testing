<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files - AVOID functions.php and auth.php to prevent conflicts
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Simple authentication check without including auth.php to avoid conflicts
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';
$preview_data = [];
$import_stats = [];

// Handle file upload and processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'upload') {
            // Handle file upload
            if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['excel_file'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                // Validate file type
                if (!in_array($file_extension, ['xlsx', 'xls', 'csv'])) {
                    $error = 'Please upload a valid Excel file (.xlsx, .xls) or CSV file.';
                } else {
                    // Process the file
                    try {
                        $preview_data = processExcelFile($file['tmp_name'], $file_extension);
                        if (empty($preview_data)) {
                            $error = 'No valid data found in the file. Please check the format.';
                        } else {
                            $success = 'File uploaded successfully! Preview below and click "Import Data" to proceed.';
                            // Store preview data in session for import
                            $_SESSION['import_preview'] = $preview_data;
                        }
                    } catch (Exception $e) {
                        $error = 'Error processing file: ' . $e->getMessage();
                    }
                }
            } else {
                $error = 'Please select a file to upload.';
            }
        } elseif ($action === 'import') {
            // Handle actual import
            if (isset($_SESSION['import_preview'])) {
                try {
                    $import_stats = importEmployeeData($_SESSION['import_preview']);
                    unset($_SESSION['import_preview']);
                    $success = 'Import completed successfully! ' . $import_stats['inserted'] . ' employees imported, ' . 
                              $import_stats['skipped'] . ' skipped (duplicates).';
                } catch (Exception $e) {
                    $error = 'Error during import: ' . $e->getMessage();
                }
            } else {
                $error = 'No preview data found. Please upload a file first.';
            }
        }
    }
}

// Get preview data if available
if (isset($_SESSION['import_preview'])) {
    $preview_data = $_SESSION['import_preview'];
}

/**
 * Process Excel/CSV file and return preview data
 */
function processExcelFile($file_path, $extension) {
    $data = [];
    
    if ($extension === 'csv') {
        // Process CSV file
        $handle = fopen($file_path, 'r');
        if ($handle) {
            $header = fgetcsv($handle); // Read header row to understand structure
            $row_count = 0;
            while (($row = fgetcsv($handle)) !== FALSE) { // Process ALL rows
                if (count($row) >= 4) { // Ensure minimum columns (HRMS_ID, Name, Emp_Number, DOB)
                    // Handle different date formats
                    $dob = '';
                    if (!empty($row[3])) {
                        $dob_raw = trim($row[3]);
                        // Try to parse different date formats
                        if (preg_match('/(\d{2})-(\d{2})-(\d{4})/', $dob_raw, $matches)) {
                            // DD-MM-YYYY format
                            $dob = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                        } elseif (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $dob_raw)) {
                            // Already in YYYY-MM-DD format
                            $dob = $dob_raw;
                        } else {
                            // Try to parse with strtotime
                            $timestamp = strtotime($dob_raw);
                            if ($timestamp !== false) {
                                $dob = date('Y-m-d', $timestamp);
                            }
                        }
                    }
                    
                    // Determine category from text
                    $category_raw = strtolower(trim($row[4] ?? 'non-gazetted'));
                    $category = 'non_gazetted'; // default
                    if (strpos($category_raw, 'gazetted') !== false && strpos($category_raw, 'non') === false) {
                        $category = 'gazetted';
                    }
                    
                    $data[] = [
                        'hrms_id' => trim($row[0]),
                        'name' => trim($row[1]),
                        'emp_number' => trim($row[2]),
                        'dob' => $dob,
                        'category' => $category,
                        'department' => trim($row[5] ?? ''),
                        'designation' => trim($row[6] ?? ''),
                        'mobile_no' => trim($row[7] ?? '')
                    ];
                    $row_count++;
                }
            }
            fclose($handle);
        }
    } else {
        // For Excel files, we'll use a simple approach
        // In a real implementation, you'd use PhpSpreadsheet library
        throw new Exception('Excel file support requires PhpSpreadsheet library. Please use CSV format for now.');
    }
    
    return $data;
}

/**
 * Import employee data into database
 */
function importEmployeeData($data) {
    $conn = getDbConnection();
    $inserted = 0;
    $skipped = 0;
    
    foreach ($data as $row) {
        // Validate required fields
        if (empty($row['hrms_id']) || empty($row['name']) || empty($row['dob'])) {
            $skipped++;
            continue;
        }
        
        // Check if employee already exists
        $check_sql = "SELECT hrms_id FROM employees WHERE hrms_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('s', $row['hrms_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $skipped++;
            $check_stmt->close();
            continue;
        }
        $check_stmt->close();
        
        // Format date
        $dob = date('Y-m-d', strtotime($row['dob']));
        
        // Set default values
        $category = in_array($row['category'], ['gazetted', 'non_gazetted']) ? $row['category'] : 'non_gazetted';
        $department_id = NULL; // No default department assignment
        
        // Insert employee
        $insert_sql = "INSERT INTO employees (hrms_id, name, emp_number, dob, category, department_id, designation, mobile_no) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param('sssssiss', 
            $row['hrms_id'], 
            $row['name'], 
            $row['emp_number'], 
            $dob, 
            $category, 
            $department_id, 
            $row['designation'], 
            $row['mobile_no']
        );
        
        if ($insert_stmt->execute()) {
            $inserted++;
        } else {
            $skipped++;
        }
        $insert_stmt->close();
    }
    
    $conn->close();
    
    return ['inserted' => $inserted, 'skipped' => $skipped];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Employees - Admin Panel</title>
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
                    Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Administrator'); ?>
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
                            <a class="nav-link active" href="import_employees.php">
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
                    <h1 class="h2">Import Employees</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- File Upload Form -->
                <?php if (empty($preview_data)): ?>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-file-upload me-2"></i>Upload Employee Data</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload">
                                    
                                    <div class="mb-3">
                                        <label for="excel_file" class="form-label">Select Excel or CSV File</label>
                                        <input type="file" class="form-control" id="excel_file" name="excel_file" 
                                               accept=".xlsx,.xls,.csv" required>
                                        <div class="form-text">
                                            Supported formats: Excel (.xlsx, .xls) and CSV (.csv). Maximum file size: 10MB.
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-cloud-upload-alt me-2"></i>Upload and Preview
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>File Format Requirements</h6>
                            </div>
                            <div class="card-body">
                                <p class="small">Your file should contain the following columns:</p>
                                <ol class="small mb-0">
                                    <li><strong>HRMS ID</strong> (Column A - Required)</li>
                                    <li><strong>Employee Name</strong> (Column B - Required)</li>
                                    <li><strong>Employee Number</strong> (Column C - Required)</li>
                                    <li><strong>DOB</strong> (Column D - DD-MM-YYYY format)</li>
                                    <li><strong>Category</strong> (Column E - GAZETTED/NON-GAZETTED)</li>
                                    <li><strong>Department</strong> (Column F - Optional)</li>
                                    <li><strong>Designation</strong> (Column G - Optional)</li>
                                    <li><strong>Mobile Number</strong> (Column H - Optional)</li>
                                </ol>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <strong>Note:</strong> Your CSV format matches perfectly! 
                                        The system will automatically convert DD-MM-YYYY dates and 
                                        handle NON-GAZETTED/GAZETTED categories.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Preview Data -->
                <?php if (!empty($preview_data)): ?>
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Data Preview (<?php echo count($preview_data); ?> records)</h5>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="import">
                            <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to import these records?')">
                                <i class="fas fa-database me-2"></i>Import Data
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>HRMS ID</th>
                                        <th>Name</th>
                                        <th>Emp Number</th>
                                        <th>DOB</th>
                                        <th>Category</th>
                                        <th>Designation</th>
                                        <th>Mobile</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($preview_data, 0, 20) as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['hrms_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['emp_number']); ?></td>
                                        <td><?php echo htmlspecialchars($row['dob']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td><?php echo htmlspecialchars($row['designation']); ?></td>
                                        <td><?php echo htmlspecialchars($row['mobile_no']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($preview_data) > 20): ?>
                        <div class="text-muted">
                            <small>Showing first 20 records for preview. Total records to import: <?php echo count($preview_data); ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="import_employees.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel Import
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>