<?php
session_start();
if (!isset($_SESSION['username'])) header("Location: index.php");

$username = $_SESSION['username'];
$userDir = "data/$username";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $filename = trim($_POST['filename']);
    $variables = array_filter($_POST['vars']);

    if (!$filename || empty($variables)) {
        die("Filename and at least one variable required.");
    }

    $filePath = "$userDir/$filename.schema.json";
    if (file_exists($filePath)) {
        die("A file with this name already exists.");
    }

    file_put_contents($filePath, json_encode(array_values($variables), JSON_PRETTY_PRINT));
    file_put_contents("$userDir/$filename.json", json_encode([], JSON_PRETTY_PRINT));
    echo "Variable set created successfully!";
    exit();
}

echo "Invalid request.";
?>