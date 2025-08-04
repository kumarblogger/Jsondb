<?php
session_start();
if (!isset($_SESSION['username'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$username = $_SESSION['username'];
$filename = isset($_GET['file']) ? basename($_GET['file']) : null;

if (!$filename) {
    die(json_encode(['success' => false, 'message' => 'No filename provided']));
}

$userDir = "data/$username";
$configFile = "$userDir/$filename.config.json";

// First check if file is private
$isPrivate = false;
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: [];
    if (isset($config['private']) && $config['private'] === true) {
        die(json_encode(['success' => false, 'message' => 'File is private']));
    }
}

// Load or create config
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: [];
}

// Generate or get existing token
if (!isset($config['token'])) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';
    $token = '';
    for ($i = 0; $i < 20; $i++) {
        $token .= $characters[rand(0, strlen($characters) - 1)];
    }
    $config['token'] = $token;
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
} else {
    $token = $config['token'];
}

echo json_encode(['success' => true, 'token' => $token]);
?>