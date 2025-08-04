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

$config = ['private' => false]; // Default config

if (file_exists($configFile)) {
    $fileConfig = json_decode(file_get_contents($configFile), true);
    if (is_array($fileConfig)) {
        $config = array_merge($config, $fileConfig);
    }
}

echo json_encode($config);
?>