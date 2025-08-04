<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

// Check if PIN verification is required
$requirePin = ['email', 'username', 'password']; // Fields that require PIN verification
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';
$pin = $_POST['pin'] ?? '';

if (empty($field) || (empty($value) && $value !== '0')) {
    die(json_encode(['success' => false, 'message' => 'Invalid data']));
}

// Read current db.json
$db = json_decode(file_get_contents('db.json'), true);
if ($db === null) {
    die(json_encode(['success' => false, 'message' => 'Database error']));
}

// Find current user
$currentUser = null;
foreach ($db as &$user) {
    if ($user['username'] === $_SESSION['username']) {
        $currentUser = &$user;
        break;
    }
}

if (!$currentUser) {
    die(json_encode(['success' => false, 'message' => 'User not found']));
}

// Check if PIN verification is required for this field
if (in_array($field, $requirePin)) {
    // Check if PIN was provided
    if (empty($pin)) {
        die(json_encode(['success' => false, 'message' => 'PIN required', 'pin_required' => true]));
    }
    
    // Verify PIN
    if (!isset($currentUser['pin']) || $currentUser['pin'] !== $pin) {
        // Track failed attempts
        if (!isset($_SESSION['pin_attempts'])) {
            $_SESSION['pin_attempts'] = 1;
        } else {
            $_SESSION['pin_attempts']++;
        }
        
        // Check if max attempts reached
        $maxAttempts = 3;
        if ($_SESSION['pin_attempts'] >= $maxAttempts) {
            $_SESSION['pin_timeout'] = time() + (15 * 60); // 15 minute timeout
            unset($_SESSION['pin_attempts']);
            die(json_encode([
                'success' => false, 
                'message' => 'Too many failed attempts. Please wait 15 minutes.',
                'timeout' => true
            ]));
        }
        
        $remaining = $maxAttempts - $_SESSION['pin_attempts'];
        die(json_encode([
            'success' => false, 
            'message' => "Incorrect PIN. $remaining attempts remaining.",
            'attempts_remaining' => $remaining
        ]));
    }
    
    // PIN verified, reset attempts
    unset($_SESSION['pin_attempts']);
    unset($_SESSION['pin_timeout']);
}

// Check if timeout is active
if (isset($_SESSION['pin_timeout']) && $_SESSION['pin_timeout'] > time()) {
    $waitMinutes = ceil(($_SESSION['pin_timeout'] - time()) / 60);
    die(json_encode([
        'success' => false, 
        'message' => "Please wait $waitMinutes minutes before trying again.",
        'timeout' => true
    ]));
}

// Update the field
$currentUser[$field] = $value;

// Update session if username changed
if ($field === 'username') {
    $_SESSION['username'] = $value;
}

// Save back to db.json
if (file_put_contents('db.json', json_encode($db, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update database']);
}
?>