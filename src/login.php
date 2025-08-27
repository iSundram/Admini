<?php
require_once 'includes/Database.php';
require_once 'includes/Auth.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
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
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        try {
            $user = $auth->login($username, $password);
            
            // Redirect based on role
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
            }
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Include login template
include 'includes/login_template.php';
?>