<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
$userDir = "data/$username";

if (!isset($_GET['file'])) {
    die("File not specified.");
}

$filename = basename($_GET['file']); // Prevent path traversal

$schemaPath = "$userDir/$filename.schema.json";
$dataPath = "$userDir/$filename.json";
$configPath = "$userDir/$filename.config.json"; // Added config path

// Delete schema file if exists
if (file_exists($schemaPath)) {
    unlink($schemaPath);
}

// Delete data file if exists
if (file_exists($dataPath)) {
    unlink($dataPath);
}

// Delete config file if exists
if (file_exists($configPath)) {
    unlink($configPath);
}

header("Location: dashboard.php");
exit();
?>
