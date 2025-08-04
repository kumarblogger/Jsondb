<?php
session_start();
require_once 'db_utils.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    die(json_encode(['success' => false, 'message' => 'Not logged in']));
}

// Get POST data
$field = $_POST['field'] ?? null;
$value = $_POST['value'] ?? null;
$pin = $_POST['pin'] ?? null;

// Validate inputs
if (!$field || !$value || !$pin || strlen($pin) !== 4 || !ctype_digit($pin)) {
    header("HTTP/1.1 400 Bad Request");
    die(json_encode(['success' => false, 'message' => 'Invalid input']));
}

// Load user data
$db = load_db();
$username = $_SESSION['username'];
$userIndex = null;

foreach ($db as $index => $user) {
    if ($user['username'] === $username) {
        $userIndex = $index;
        break;
    }
}

if ($userIndex === null) {
    header("HTTP/1.1 404 Not Found");
    die(json_encode(['success' => false, 'message' => 'User not found']));
}

$user = $db[$userIndex];

// Check if user is in timeout
if (isset($user['pin_timeout']) && time() < $user['pin_timeout']) {
    $timeoutRemaining = $user['pin_timeout'] - time();
    die(json_encode([
        'success' => false,
        'message' => 'Too many failed attempts. Please try again later.',
        'timeout' => true,
        'timeout_remaining' => $timeoutRemaining
    ]));
}

// Verify PIN
if (!isset($user['pin']) || !password_verify($pin, $user['pin'])) {
    // Increment failed attempts
    $attempts = isset($user['pin_attempts']) ? $user['pin_attempts'] + 1 : 1;
    $db[$userIndex]['pin_attempts'] = $attempts;
    
    // Set timeout if reached max attempts
    if ($attempts >= 3) {
        $db[$userIndex]['pin_timeout'] = time() + (15 * 60); // 15 minutes timeout
        save_db($db);
        
        die(json_encode([
            'success' => false,
            'message' => 'Incorrect PIN. Maximum attempts reached. Please try again in 15 minutes.',
            'timeout' => true,
            'timeout_remaining' => 15 * 60,
            'attempts_remaining' => 0
        ]));
    }
    
    save_db($db);
    
    die(json_encode([
        'success' => false,
        'message' => 'Incorrect PIN. Please try again.',
        'attempts_remaining' => 3 - $attempts
    ]));
}

// PIN is correct - reset attempts and update field
$db[$userIndex]['pin_attempts'] = 0;
unset($db[$userIndex]['pin_timeout']);

// Update the requested field
$allowedFields = ['email']; // Add other fields that can be updated here
if (in_array($field, $allowedFields)) {
    $db[$userIndex][$field] = $value;
}

save_db($db);

echo json_encode([
    'success' => true,
    'message' => 'PIN verified and account updated successfully'
]);
?>