<?php
session_start();
require_once 'db_utils.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    die(json_encode(['success' => false, 'message' => 'Not logged in']));
}

// Load user data
$db = load_db();
$username = $_SESSION['username'];
$user = null;

foreach ($db as $u) {
    if ($u['username'] === $username) {
        $user = $u;
        break;
    }
}

if (!$user) {
    header("HTTP/1.1 404 Not Found");
    die(json_encode(['success' => false, 'message' => 'User not found']));
}

// Check timeout status
$response = [
    'success' => true,
    'attempts_remaining' => isset($user['pin_attempts']) ? 3 - $user['pin_attempts'] : 3
];

if (isset($user['pin_timeout']) && time() < $user['pin_timeout'])) {
    $response['timeout_active'] = true;
    $response['timeout_remaining'] = $user['pin_timeout'] - time();
    $response['message'] = 'Account settings locked due to too many failed attempts';
} else {
    $response['timeout_active'] = false;
    $response['timeout_remaining'] = 0;
}

echo json_encode($response);
?>