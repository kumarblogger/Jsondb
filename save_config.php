<?php
session_start();
if (!isset($_SESSION['username'])) {
    die("Unauthorized");
}

$username = $_SESSION['username'];
$input = json_decode(file_get_contents('php://input'), true);

$filename = $input['filename'] ?? '';
$config = $input['config'] ?? [];

if (empty($filename)) {
    die("Invalid filename");
}

$configFile = "data/$username/$filename.config.json";
file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

echo "Configuration saved successfully";
?>