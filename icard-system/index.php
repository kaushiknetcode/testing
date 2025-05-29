<?php
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eastern Railway I-Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .card {
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
        .login-option {
            height: 100%;
        }
        .bg-custom {
            background: linear-gradient(135deg, #0d6efd, #0dcaf0);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-custom">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/railway-logo.png" alt="Logo" height="40" class="d-inline-block align-text-top me-2">
                Eastern Railway I-Card System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-5">
        <div class="text-center mb-5">
            <h1 class="display-4 mb-3">Welcome to Eastern Railway I-Card System</h1>
            <p class="lead">A digital solution for managing employee identification cards</p>
        </div>

        <div class="row g-4 justify-content-center">
            <!-- Employee Login -->
            <div class="col-md-5">
                <div class="card h-100 border-0 shadow">
                    <div class="card-body text-center p-5">
                        <div class="mb-4">
                            <i class="fas fa-user-tie fa-4x text-primary mb-3"></i>
                            <h3>Employee Portal</h3>
                            <p class="text-muted">Access your I-Card application and status</p>
                        </div>
                        <a href="employee/" class="btn btn-primary btn-lg px-4">Employee Login</a>
                    </div>
                </div>
            </div>

            <!-- Admin Login -->
            <div class="col-md-5">
                <div class="card h-100 border-0 shadow">
                    <div class="card-body text-center p-5">
                        <div class="mb-4">
                            <i class="fas fa-user-shield fa-4x text-danger mb-3"></i>
                            <h3>Administrator</h3>
                            <p class="text-muted">System administration and management</p>
                        </div>
                        <a href="admin/login.php" class="btn btn-outline-danger btn-lg px-4">Admin Login</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="row mt-5">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="h4 mb-3">About the System</h2>
                <p class="text-muted">
                    The Eastern Railway I-Card System streamlines the process of issuing, managing, 
                    and renewing employee identification cards. The system provides a seamless 
                    experience for employees and administrators alike.
                </p>
                <hr class="my-4">
                <div class="d-flex justify-content-center gap-4">
                    <div>
                        <i class="fas fa-shield-alt text-primary mb-2"></i>
                        <div>Secure Access</div>
                    </div>
                    <div>
                        <i class="fas fa-bolt text-primary mb-2"></i>
                        <div>Fast Processing</div>
                    </div>
                    <div>
                        <i class="fas fa-mobile-alt text-primary mb-2"></i>
                        <div>Mobile Friendly</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Eastern Railway. All rights reserved.</p>
            <p class="mb-0 small text-muted">Version 1.0.0</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>