<?php
session_start();
if (!isset($_SESSION['username'])) {
    die("Unauthorized");
}

$username = $_SESSION['username'];
$userDir = "data/$username";

if (!is_dir($userDir)) {
    mkdir($userDir, 0777, true);
}

$filename = $_POST['filename'] ?? '';
$file = $_FILES['jsonfile'] ?? null;

if (empty($filename) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
    die("Invalid file upload");
}

// Validate filename
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $filename)) {
    die("Invalid filename. Only letters, numbers, underscores and hyphens are allowed.");
}

$targetFile = "$userDir/$filename.json";

// Check if file already exists
if (file_exists($targetFile)) {
    die("A file with that name already exists.");
}

// Save the main file
if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
    die("Failed to save file");
}

// Create schema file by extracting keys from JSON
$jsonContent = file_get_contents($targetFile);
$jsonData = json_decode($jsonContent, true);

if ($jsonData === null) {
    unlink($targetFile);
    die("Invalid JSON file");
}

// Get keys from first item if array, or directly if object
$keys = [];
if (is_array($jsonData) && !empty($jsonData)) {
    $keys = array_keys($jsonData[0]);
} elseif (is_object($jsonData)) {
    $keys = array_keys($jsonData);
}

// Save schema file
if (!empty($keys)) {
    $schemaFile = "$userDir/$filename.schema.json";
    file_put_contents($schemaFile, json_encode($keys, JSON_PRETTY_PRINT));
}

echo "File uploaded successfully. You can now fetch variables.";
?>