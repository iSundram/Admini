<?php
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// Check authentication
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getCurrentUser();

// Redirect based on role if accessing index directly
if (!isset($_GET['redirect']) && $user) {
    switch ($user['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'reseller':
            header('Location: reseller/dashboard.php');
            break;
        case 'user':
            header('Location: user/dashboard.php');
            break;
        default:
            header('Location: login.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admini Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .welcome-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .welcome-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
        }
        .welcome-body {
            padding: 3rem 2rem;
        }
        .feature-list {
            text-align: left;
            margin: 2rem 0;
        }
        .feature-list li {
            margin: 0.5rem 0;
            color: #6c757d;
        }
        .btn-dashboard {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0.5rem;
        }
        .btn-dashboard:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="welcome-header">
            <h1><i class="fas fa-server me-3"></i>Admini</h1>
            <p class="mb-0 fs-5">Web Hosting Control Panel</p>
        </div>
        <div class="welcome-body">
            <h3 class="mb-4">Welcome to Admini Control Panel</h3>
            <p class="text-muted mb-4">
                A comprehensive web hosting management solution combining the best features 
                of cPanel/WHM, DirectAdmin, and Webuzo.
            </p>
            
            <div class="feature-list">
                <h5>Key Features:</h5>
                <ul>
                    <li><i class="fas fa-users text-primary me-2"></i>Multi-level user management (Admin, Reseller, User)</li>
                    <li><i class="fas fa-globe text-success me-2"></i>Domain and DNS management</li>
                    <li><i class="fas fa-envelope text-info me-2"></i>Email accounts and management</li>
                    <li><i class="fas fa-database text-warning me-2"></i>MySQL and PostgreSQL databases</li>
                    <li><i class="fas fa-lock text-danger me-2"></i>SSL certificate management</li>
                    <li><i class="fas fa-download text-secondary me-2"></i>Backup and restore functionality</li>
                    <li><i class="fas fa-chart-bar text-primary me-2"></i>Statistics and monitoring</li>
                    <li><i class="fas fa-cog text-dark me-2"></i>Advanced configuration options</li>
                </ul>
            </div>
            
            <div class="mt-4">
                <a href="login.php" class="btn btn-primary btn-dashboard">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to Panel
                </a>
            </div>
            
            <div class="mt-4 pt-3 border-top">
                <small class="text-muted">
                    Version 1.0.0 | 
                    <a href="https://github.com/iSundram/Admini" class="text-decoration-none">
                        <i class="fab fa-github me-1"></i>GitHub
                    </a>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>