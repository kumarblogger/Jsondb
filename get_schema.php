<?php
session_start();
if (!isset($_SESSION['username'])) {
    die(json_encode([]));
}

$username = $_SESSION['username'];
$file = $_GET['file'] ?? '';

if (empty($file)) {
    die(json_encode([]));
}

$schemaFile = "data/$username/$file.schema.json";

if (!file_exists($schemaFile)) {
    die(json_encode([]));
}

$schema = file_get_contents($schemaFile);
die($schema);
?>