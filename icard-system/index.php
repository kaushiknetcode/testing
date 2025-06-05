<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eastern Railway I-Card System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://kit.fontawesome.com/a076d05399.css" rel="stylesheet">
    <style>
        /* CSS from your style.css file */
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --dark-blue: #0d47a1;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }

        /* Hamburger Menu Icon Styles from your CSS */
        .hamburger-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 10px;
            display: inline-block;
            position: relative;
        }

        .hamburger-lines,
        .hamburger-lines::before,
        .hamburger-lines::after {
            content: '';
            display: block;
            width: 25px;
            height: 3px;
            background-color: var(--dark-blue, #0d47a1);
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .hamburger-lines::before {
            transform: translateY(-8px);
        }

        .hamburger-lines::after {
            transform: translateY(5px);
        }

        /* Your original inline styles */
        :root {
            --primary-color: #1a237e;
            --secondary-color: #3949ab;
            --accent-color: #ff6f00;
            --dark-blue: #0d47a1;
            --light-blue: #e3f2fd;
            --text-dark: #263238;
            --professional-gray: #37474f;
            --gold: #ffa000;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            color: var(--text-dark);
        }

        /* Header */
        .main-header {
            background: linear-gradient(90deg, #ffffff 0%, #f8f9fa 100%);
            padding: 15px 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 40px;
            margin: 0 auto;
        }

        .logo-section img {
            height: 70px;
            object-fit: contain;
        }

        .login-section {
            display: flex;
            align-items: center;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        .login-dropdown {
            position: relative;
        }

        .dropdown-content {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            min-width: 200px;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            margin-top: 8px;
            border: 1px solid #e0e0e0;
        }

        .login-dropdown:hover .dropdown-content {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: block;
            padding: 10px 15px;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .dropdown-item:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Page title */
        .page-title {
            text-align: center;
            padding: 20px 0 15px;
            color: white;
        }

        .title-main {
            font-size: clamp(1.8rem, 4vw, 3.5rem);
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(45deg, #ffffff, #ffeb3b, #ffffff, #ffeb3b);
            background-size: 300% 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleGlow 3s ease-in-out infinite;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        @keyframes titleGlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .title-sub {
            font-size: clamp(1.1rem, 3vw, 1.4rem);
            opacity: 0.95;
            font-weight: 600;
            animation: fadeInOut 2s ease-in-out infinite alternate;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        @keyframes fadeInOut {
            0% { opacity: 0.8; transform: scale(0.98); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* Split layout container */
        .split-container {
            display: flex;
            gap: 30px;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Image carousel - left side */
        .image-carousel {
            flex: 0 0 45%;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            height: 350px;
        }

        .carousel-inner img {
            height: 350px;
            width: 100%;
            object-fit: cover;
            object-position: center;
        }

        .carousel-control-prev,
        .carousel-control-next {
            width: 50px;
            height: 50px;
            background: rgba(26, 35, 126, 0.8);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.9;
        }

        .carousel-control-prev:hover,
        .carousel-control-next:hover {
            opacity: 1;
            background: rgba(26, 35, 126, 1);
        }

        .carousel-control-prev {
            left: 15px;
        }

        .carousel-control-next {
            right: 15px;
        }

        .carousel-indicators {
            bottom: 15px;
        }

        .carousel-indicators [data-bs-target] {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin: 0 4px;
            background: rgba(255, 255, 255, 0.6);
            border: 2px solid white;
        }

        .carousel-indicators .active {
            background: var(--accent-color);
        }

        /* Employee portal card - right side */
        .portal-section {
            padding: 0;
            flex: 0 0 50%;
        }

        .portal-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            margin: 0;
            transition: all 0.4s ease;
            height: 350px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .portal-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
        }

        .portal-icon {
            width: 60px;
            height: 38px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            transition: all 0.3s ease;
            animation: cardPulse 2s ease-in-out infinite;
            box-shadow: 0 6px 20px rgba(26, 35, 126, 0.3);
            position: relative;
            overflow: hidden;
        }

        .portal-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transform: rotate(45deg);
            animation: shimmer 2s linear infinite;
        }

        @keyframes cardPulse {
            0%, 100% { 
                transform: scale(1); 
                box-shadow: 0 8px 25px rgba(26, 35, 126, 0.3);
            }
            50% { 
                transform: scale(1.05); 
                box-shadow: 0 12px 35px rgba(255, 111, 0, 0.4);
            }
        }

        @keyframes shimmer {
            0% { transform: rotate(45deg) translateX(-100%); }
            100% { transform: rotate(45deg) translateX(100%); }
        }

        .portal-card:hover .portal-icon {
            animation-play-state: paused;
            transform: scale(1.1);
            background: linear-gradient(135deg, var(--accent-color), var(--gold));
            box-shadow: 0 15px 40px rgba(255, 111, 0, 0.5);
        }

        .portal-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .portal-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .portal-description {
            font-size: 1rem;
            color: var(--professional-gray);
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .portal-note {
            font-size: 0.9rem;
            color: var(--accent-color);
            margin-bottom: 0;
            font-weight: 600;
            font-style: italic;
        }

        .portal-btn-container {
            margin-top: 15px;
        }

        .portal-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(26, 35, 126, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            width: 100%;
            animation: cardPulse 3s ease-in-out infinite;
        }

        .portal-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .portal-btn:hover {
            background: linear-gradient(45deg, var(--accent-color), var(--gold));
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 111, 0, 0.4);
            animation: none; /* Disable pulse on hover for better visual feedback */
        }

        .portal-btn:hover::before {
            left: 100%;
        }

        /* Footer */
        .footer {
            background: linear-gradient(45deg, var(--text-dark), var(--professional-gray));
            color: white;
            padding: 30px 0;
            text-align: center;
            margin-top: 40px;
        }

        .footer p {
            margin: 5px 0;
            opacity: 0.9;
        }

        .footer .version {
            font-size: 0.9rem;
            opacity: 0.7;
        }

        /* Responsive design */
        @media (max-width: 992px) {
            .header-content {
                justify-content: space-between;
                padding: 10px 20px;
            }

            .logo-section {
                position: static;
                transform: none;
                gap: 20px;
                flex: 1;
                justify-content: center;
            }

            .login-section {
                position: static;
                transform: none;
                flex: 0;
            }

            .logo-section img {
                height: 50px;
            }

            .split-container {
                flex-direction: column;
                gap: 20px;
                padding: 15px;
            }

            .image-carousel,
            .portal-section {
                flex: none;
                width: 100%;
            }

            .image-carousel {
                height: 250px;
            }

            .carousel-inner img {
                height: 250px;
            }

            .portal-card {
                height: auto;
                padding: 30px 25px;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                padding: 10px 15px 8px;
            }

            .split-container {
                padding: 10px;
                gap: 15px;
            }

            .image-carousel {
                height: 180px;
            }

            .carousel-inner img {
                height: 180px;
            }

            .carousel-control-prev,
            .carousel-control-next {
                width: 35px;
                height: 35px;
            }

            .logo-section {
                justify-content: center;
                flex-wrap: wrap;
                gap: 15px;
            }

            .portal-card {
                padding: 15px;
                height: 280px;
            }

            .portal-icon {
                width: 50px;
                height: 30px;
                font-size: 1rem;
                margin-bottom: 10px;
            }

            .portal-title {
                font-size: 1.5rem;
                margin-bottom: 10px;
            }

            .portal-description {
                font-size: 0.9rem;
                margin-bottom: 10px;
            }

            .portal-note {
                font-size: 0.8rem;
            }

            .portal-btn {
                padding: 10px 15px;
                font-size: 0.85rem;
            }

            .portal-btn-container {
                margin-top: 10px;
            }
        }

        @media (max-width: 480px) {
            .title-main {
                font-size: 1.5rem;
            }

            .title-sub {
                font-size: 0.9rem;
            }

            .split-container {
                padding: 8px;
                gap: 10px;
            }

            .image-carousel {
                height: 140px;
            }

            .carousel-inner img {
                height: 140px;
            }

            .portal-card {
                padding: 12px;
                height: 250px;
                border-radius: 12px;
            }

            .portal-icon {
                width: 45px;
                height: 28px;
                font-size: 0.9rem;
                margin-bottom: 8px;
            }

            .portal-title {
                font-size: 1.1rem;
                margin-bottom: 6px;
            }

            .portal-description {
                font-size: 0.75rem;
                margin-bottom: 6px;
                line-height: 1.3;
            }

            .portal-note {
                font-size: 0.65rem;
            }

            .portal-btn {
                padding: 8px 12px;
                font-size: 0.8rem;
                border-radius: 8px;
            }

            .portal-btn-container {
                margin-top: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Main Header with Login -->
    <header class="main-header">
        <div class="header-content">
            <div class="logo-section">
                <img src="assets/images/railway-logo.png" alt="Indian Railway Logo">
                <img src="assets/images/ashoka-stambha.png" alt="Ashoka Stambha Government Logo">
                <img src="assets/images/kanchrapara-logo.png" alt="Kanchrapara Workshop Logo">
            </div>
            
            <div class="login-section">
                <div class="login-dropdown">
                    <button class="hamburger-btn">
                        <div class="hamburger-lines"></div>
                    </button>
                    <div class="dropdown-content">
                        <a href="co/login.php" class="dropdown-item">CO Login</a>
                        <a href="dealer/login.php" class="dropdown-item">Dealer Login</a>
                        <a href="awo/login.php" class="dropdown-item">Authority Login</a>
                        <a href="admin/login.php" class="dropdown-item">Admin Login</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Title -->
    <div class="page-title">
        <div class="container">
            <h1 class="title-main">Eastern Railway Kanchrapara Workshop</h1>
            <p class="title-sub">Identity Card Portal</p>
        </div>
    </div>

    <!-- Split Layout Container -->
    <div class="split-container">
        <!-- Image Carousel - Left Side -->
        <div id="workshopCarousel" class="carousel slide image-carousel" data-bs-ride="carousel" data-bs-interval="3000">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#workshopCarousel" data-bs-slide-to="0" class="active"></button>
                <button type="button" data-bs-target="#workshopCarousel" data-bs-slide-to="1"></button>
                <button type="button" data-bs-target="#workshopCarousel" data-bs-slide-to="2"></button>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="assets/images/office-photo.jpg" alt="Eastern Railway Office Building">
                </div>
                <div class="carousel-item">
                    <img src="assets/images/office-photo2.jpg" alt="Workshop Interior View">
                </div>
                <div class="carousel-item">
                    <img src="assets/images/office-photo3.jpg" alt="Administrative Building">
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#workshopCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#workshopCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
        </div>

        <!-- Employee Portal Section - Right Side -->
        <div class="portal-section">
            <div class="portal-card">
                <div>
                    <div class="portal-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                </div>
                <div class="portal-content">
                    <h2 class="portal-title">Employee Portal</h2>
                    <p class="portal-description">
                        This portal allows you to apply for new identity cards, check application status, 
                        download ID cards, update personal information, and access various employee services 
                        for comprehensive identity management.
                    </p>
                    <p class="portal-note">
                        Both Gazetted and Non-Gazetted employees can apply here
                    </p>
                </div>
                <div class="portal-btn-container">
                    <a href="employee/" class="portal-btn">Access Employee Portal</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Eastern Railway Kanchrapara Workshop. All rights reserved.</p>
            <p class="version">Identity Card Portal System | Version 1.0.0</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add FontAwesome icons dynamically
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
        document.head.appendChild(link);
    </script>
</body>
</html>