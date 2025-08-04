<?php
session_start();
if (!isset($_SESSION['username'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$username = $_SESSION['username'];
$filename = isset($_GET['filename']) ? $_GET['filename'] : '';

if (empty($filename)) {
    die(json_encode(['success' => false, 'message' => 'Filename not provided']));
}

$importFile = "data/$username/$filename.import.json";

if (file_exists($importFile)) {
    if (unlink($importFile)) {
        echo json_encode(['success' => true, 'message' => 'Import cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete import file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Import file not found']);
}
?>