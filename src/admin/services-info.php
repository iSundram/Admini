<?php
require_once '../includes/Auth.php';

$auth = new Auth();

// Check authentication and admin role
if (!$auth->isLoggedIn() || !$auth->hasPermission('admin')) {
    http_response_code(403);
    exit('Access denied');
}

$serviceName = $_GET['service'] ?? '';

if (!$serviceName || !preg_match('/^[a-zA-Z0-9_-]+$/', $serviceName)) {
    http_response_code(400);
    exit('Invalid service name');
}

// Get service status information
$output = [];
exec("systemctl status $serviceName 2>&1", $output);

header('Content-Type: text/plain');
echo implode("\n", $output);
?>