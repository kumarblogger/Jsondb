<?php
header('Content-Type: application/json');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the filename from the form data
$filename = isset($_POST['filename']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['filename']) : '';
if (empty($filename)) {
    echo json_encode(['success' => false, 'message' => 'Filename is required']);
    exit();
}

// Find the username from the directory structure
$username = '';
$dataDir = 'data/';
foreach (scandir($dataDir) as $dir) {
    if ($dir !== '.' && $dir !== '..' && is_dir($dataDir . $dir)) {
        if (file_exists($dataDir . $dir . '/' . $filename . '.import.json')) {
            $username = $dir;
            break;
        }
    }
}

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'User data not found']);
    exit();
}

// Load the import configuration
$importFile = $dataDir . $username . '/' . $filename . '.import.json';
if (!file_exists($importFile)) {
    echo json_encode(['success' => false, 'message' => 'Import configuration not found']);
    exit();
}

$importConfig = json_decode(file_get_contents($importFile), true);
if ($importConfig === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid import configuration']);
    exit();
}

// Prepare the data to be saved (simple format without metadata)
$dataToSave = [];

// Process regular fields
foreach ($_POST as $key => $value) {
    if ($key === 'filename') continue;
    
    // Skip array fields (they will be processed separately)
    if (strpos($key, '[]') !== false) continue;
    
    // Skip file fields (they will be processed separately)
    if (isset($_FILES[$key])) continue;
    
    $dataToSave[$key] = $value;
}

// Process array fields
foreach ($importConfig['array_vars'] as $arrayField) {
    if (isset($_POST[$arrayField . '[]'])) {
        $dataToSave[$arrayField] = $_POST[$arrayField . '[]'];
    } elseif (isset($_POST[$arrayField])) {
        $dataToSave[$arrayField] = is_array($_POST[$arrayField]) ? $_POST[$arrayField] : [$_POST[$arrayField]];
    }
}

// Process image uploads
foreach ($importConfig['image_vars'] as $imageField) {
    if (isset($_FILES[$imageField])) {
        $file = $_FILES[$imageField];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $file['tmp_name']);
        finfo_close($fileInfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            continue;
        }
        
        // Create uploads directory if it doesn't exist
        $uploadDir = $dataDir . $username . '/uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate a unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = $imageField . '_' . uniqid() . '.' . $extension;
        $destination = $uploadDir . $newFilename;
        
        // Move the uploaded file
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $dataToSave[$imageField] = $newFilename;
        }
    }
}

// Create or update the data file
$dataFile = $dataDir . $username . '/' . $filename . '.json';
$existingData = [];

if (file_exists($dataFile)) {
    $existingData = json_decode(file_get_contents($dataFile), true);
    if ($existingData === null) {
        $existingData = [];
    }
    
    // Ensure we have an array of entries
    if (!isset($existingData[0])) {
        $existingData = [$existingData];
    }
}

// Add the new submission to the existing data
$existingData[] = $dataToSave;

// Save the data with proper JSON encoding for all languages and unescaped slashes
if (file_put_contents($dataFile, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
    echo json_encode(['success' => true, 'message' => 'Data saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save data']);
}