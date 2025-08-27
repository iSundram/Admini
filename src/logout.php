<?php
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// Logout user
$auth->logout();

// Redirect to login page
header('Location: login.php');
exit;
?>