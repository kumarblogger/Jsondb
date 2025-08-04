<?php
// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

$dbFile = "db.json";

// Function to encode view token
function generateViewToken($key, $file) {
    return base64_encode(json_encode(["key" => $key, "file" => $file]));
}

// Function to decode view token
function parseViewToken($token) {
    $decoded = @base64_decode($token);
    $json = json_decode($decoded, true);
    return (is_array($json) && isset($json['key']) && isset($json['file'])) ? $json : null;
}

// Function to find file by random token
function findFileByToken($token) {
    $dataDir = 'data';
    foreach (glob("$dataDir/*/*.config.json") as $configFile) {
        $config = json_decode(file_get_contents($configFile), true);
        if (isset($config['token']) && $config['token'] === $token) {
            // Check if file is private
            if (isset($config['private']) && $config['private'] === true) {
                continue;
            }
            $filePath = str_replace('.config.json', '.json', $configFile);
            if (file_exists($filePath)) {
                return [
                    'path' => $filePath,
                    'config' => $config,
                    'username' => basename(dirname($configFile))
                ];
            }
        }
    }
    return null;
}

// If edit parameter is present, redirect to edit.php
if (isset($_GET['edit'])) {
    $filename = basename($_GET['edit']);
    
    if (isset($_GET['token'])) {
        $token = preg_replace("/[^a-zA-Z0-9_-]/", "", $_GET['token']);
        $fileInfo = findFileByToken($token);
        
        if ($fileInfo) {
            // Verify the edit filename matches the token's file
            $expectedFilename = basename($fileInfo['path'], '.json');
            if ($filename === $expectedFilename) {
                // Redirect to edit.php with the token
                header("Location: edit.php?token=" . urlencode($token) . "&file=" . urlencode($filename));
                exit;
            }
        }
    }
    
    // If we get here, the edit request is invalid
    http_response_code(400);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["error" => "Invalid edit request."]);
    exit;
}

// If ?key=...&file=... format, redirect to view=...
if (isset($_GET['key']) && isset($_GET['file'])) {
    $apiKey = preg_replace("/[^a-zA-Z0-9]/", "", $_GET['key']);
    $filename = basename($_GET['file']);
    $viewToken = generateViewToken($apiKey, $filename);
    header("Location: fetch.php?view=" . urlencode($viewToken));
    exit;
}

// If ?token=... format (new random token system)
if (isset($_GET['token'])) {
    $token = preg_replace("/[^a-zA-Z0-9_-]/", "", $_GET['token']);
    $fileInfo = findFileByToken($token);
    
    if ($fileInfo) {
        $content = file_get_contents($fileInfo['path']);
        
        // Validate JSON content
        json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500);
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode(["error" => "Invalid JSON content in file."]);
            exit;
        }
        
        // Output based on raw parameter
        if (isset($_GET['raw']) && $_GET['raw'] === '1') {
            header("Content-Type: text/plain; charset=utf-8");
            // Ensure proper URL formatting and language support
            $content = str_replace('https:\/\/', 'https://', $content);
            echo $content;
        } else {
            header("Content-Type: application/json; charset=utf-8");
            echo $content;
        }
        exit;
    } else {
        http_response_code(404);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["error" => "File not found or access denied."]);
        exit;
    }
}

// If ?view=... format (legacy base64 encoded system)
if (isset($_GET['view'])) {
    $viewData = parseViewToken($_GET['view']);

    if (!$viewData) {
        http_response_code(400);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["error" => "Invalid view token."]);
        exit;
    }

    $apiKey = preg_replace("/[^a-zA-Z0-9]/", "", $viewData['key']);
    $filename = basename($viewData['file']);

    if (!file_exists($dbFile)) {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["error" => "db.json not found."]);
        exit;
    }

    $users = json_decode(file_get_contents($dbFile), true);
    if (!is_array($users)) {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["error" => "Invalid db.json format."]);
        exit;
    }

    $username = null;
    foreach ($users as $userData) {
        if (isset($userData['apikey']) && $userData['apikey'] === $apiKey) {
            $username = $userData['username'];
            break;
        }
    }

    if (!$username) {
        http_response_code(403);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["error" => "Invalid API key."]);
        exit;
    }

    $filePath = "data/$username/$filename.json";
    $configPath = "data/$username/$filename.config.json";
    
    // Check if file is private
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
        if (isset($config['private']) && $config['private'] === true) {
            http_response_code(403);
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode(["error" => "This file is private."]);
            exit;
        }
    }

    if (!file_exists($filePath)) {
        http_response_code(404);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["error" => "File not found."]);
        exit;
    }

    $content = file_get_contents($filePath);
    
    // Validate JSON content
    json_decode($content);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["error" => "Invalid JSON content in file."]);
        exit;
    }
    
    // Output based on raw parameter
    if (isset($_GET['raw']) && $_GET['raw'] === '1') {
        header("Content-Type: text/plain; charset=utf-8");
        // Ensure proper URL formatting and language support
        $content = str_replace('https:\/\/', 'https://', $content);
        echo $content;
    } else {
        header("Content-Type: application/json; charset=utf-8");
        echo $content;
    }
    exit;
}

// If no valid parameters are provided
http_response_code(400);
header("Content-Type: application/json; charset=utf-8");
echo json_encode([
    "error" => "Missing parameters.",
    "usage" => [
        "Legacy format" => "fetch.php?view=BASE64_ENCODED_JSON",
        "New token format" => "fetch.php?token=RANDOM_20_CHAR_TOKEN",
        "Edit format" => "fetch.php?token=TOKEN&edit=FILENAME",
        "Raw output" => "Add &raw=1 to any format"
    ]
]);
exit;
?>