<?php
session_start();
if (!isset($_SESSION['username'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$username = $_SESSION['username'];
$oldFilename = isset($_POST['old_filename']) ? basename($_POST['old_filename']) : null;
$newFilename = isset($_POST['new_filename']) ? basename($_POST['new_filename']) : null;
$isPrivate = isset($_POST['private']) && $_POST['private'] === 'true';

if (!$oldFilename || !$newFilename) {
    die(json_encode(['success' => false, 'message' => 'Filename not provided']));
}

$userDir = "data/$username";

// Check if new filename already exists
if ($oldFilename !== $newFilename && file_exists("$userDir/$newFilename.json")) {
    die(json_encode(['success' => false, 'message' => 'Filename already exists']));
}

// Rename all related files
$fileTypes = ['', '.schema', '.config'];
$success = true;

foreach ($fileTypes as $type) {
    $oldFile = "$userDir/$oldFilename$type.json";
    $newFile = "$userDir/$newFilename$type.json";
    
    if (file_exists($oldFile)) {
        if (!rename($oldFile, $newFile)) {
            $success = false;
            break;
        }
    }
}

if (!$success) {
    die(json_encode(['success' => false, 'message' => 'Error renaming files']));
}

// Update privacy setting in config file
$configFile = "$userDir/$newFilename.config.json";
$config = [];

if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: [];
}

$config['private'] = $isPrivate;
file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'message' => 'File settings updated successfully']);
?>