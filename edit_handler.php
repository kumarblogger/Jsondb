<?php
session_start();
if (!isset($_SESSION['username'])) header("Location: index.php");

$username = $_SESSION['username'];
$userDir = "data/$username";

if (!isset($_GET['file'])) die("File not specified.");
$filename = basename($_GET['file']);
$schemaPath = "$userDir/$filename.schema.json";

if (!file_exists($schemaPath)) {
    die("Schema not found.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vars = json_decode($_POST['vars'], true);
    $updated = array_filter($vars, fn($v) => trim($v) !== '');
    $updated = array_values(array_unique($updated));

    if (empty($updated)) {
        die("At least one variable must be present.");
    }

    file_put_contents($schemaPath, json_encode($updated, JSON_PRETTY_PRINT));
    echo "Variables updated successfully!";
    exit();
}

echo "Invalid request.";
?>