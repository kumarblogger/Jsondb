<?php
session_start();
if (!isset($_SESSION['username'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$username = $_SESSION['username'];
$filename = $_GET['filename'] ?? '';

if (empty($filename)) {
    die(json_encode(['success' => false, 'message' => 'Filename not provided']));
}

$codeFile = "data/$username/$filename.code.txt";

if (!file_exists($codeFile)) {
    die(json_encode(['success' => false, 'message' => 'Code file not found']));
}

$content = file_get_contents($codeFile);
echo json_encode(['success' => true, 'content' => $content]);