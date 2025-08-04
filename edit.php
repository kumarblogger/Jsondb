<?php
// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

session_start();

$dbFile = "db.json";

// Function to find file by random token
function findFileByToken($token) {
    $dataDir = 'data';
    foreach (glob("$dataDir/*/*.config.json") as $configFile) {
        $config = json_decode(file_get_contents($configFile), true);
        if (isset($config['token']) && $config['token'] === $token) {
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

// Check if token and file parameters are provided
if (!isset($_GET['token']) || !isset($_GET['file'])) {
    http_response_code(400);
    die("Missing token or file parameter");
}

$token = preg_replace("/[^a-zA-Z0-9_-]/", "", $_GET['token']);
$filename = basename($_GET['file']);

// Find the file associated with the token
$fileInfo = findFileByToken($token);
if (!$fileInfo) {
    http_response_code(404);
    die("File not found or invalid token");
}

// Verify the filename matches
$expectedFilename = basename($fileInfo['path'], '.json');
if ($filename !== $expectedFilename) {
    http_response_code(400);
    die("Invalid file name for this token");
}

$filePath = $fileInfo['path'];
$username = $fileInfo['username'];

// Load the JSON file
$jsonContent = file_get_contents($filePath);
$data = json_decode($jsonContent, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON data in file");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['json_data'])) {
        $newData = json_decode($_POST['json_data'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Save the file
            file_put_contents($filePath, json_encode($newData, JSON_PRETTY_PRINT));
            $data = $newData;
            $successMessage = "File saved successfully!";
        } else {
            $errorMessage = "Invalid JSON data provided";
        }
    }
}

// Get schema if available
$schemaPath = "data/$username/$filename.schema.json";
$schema = file_exists($schemaPath) ? json_decode(file_get_contents($schemaPath), true) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?= htmlspecialchars($filename) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .editor-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .editor {
            flex: 1;
        }
        textarea {
            width: 100%;
            height: 400px;
            font-family: monospace;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .preview {
            flex: 1;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            overflow-y: auto;
            max-height: 400px;
        }
        .buttons {
            margin-top: 20px;
        }
        button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background-color: #45a049;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        .json-key {
            color: #92278f;
            font-weight: bold;
        }
        .json-string {
            color: #3ab54a;
        }
        .json-number {
            color: #25aae2;
        }
        .json-boolean {
            color: #f98280;
        }
        .json-null {
            color: #f1592a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit <?= htmlspecialchars($filename) ?>.json</h1>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="editor-container">
                <div class="editor">
                    <h3>JSON Editor</h3>
                    <textarea name="json_data" id="json_data"><?= htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) ?></textarea>
                </div>
                <div class="preview">
                    <h3>Preview</h3>
                    <div id="json_preview"></div>
                </div>
            </div>
            
            <div class="buttons">
                <button type="submit">Save Changes</button>
                <button type="button" id="format_btn">Format JSON</button>
                <button type="button" id="minify_btn">Minify JSON</button>
                <a href="fetch.php?token=<?= urlencode($token) ?>&raw=1" target="_blank">
                    <button type="button">View Raw JSON</button>
                </a>
            </div>
        </form>
    </div>

    <script>
        // Function to syntax highlight JSON
        function syntaxHighlight(json) {
            if (typeof json != 'string') {
                json = JSON.stringify(json, null, 2);
            }
            
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            
            return json.replace(
                /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
                function (match) {
                    let cls = 'json-number';
                    if (/^"/.test(match)) {
                        if (/:$/.test(match)) {
                            cls = 'json-key';
                        } else {
                            cls = 'json-string';
                        }
                    } else if (/true|false/.test(match)) {
                        cls = 'json-boolean';
                    } else if (/null/.test(match)) {
                        cls = 'json-null';
                    }
                    return '<span class="' + cls + '">' + match + '</span>';
                }
            );
        }

        // Update preview
        function updatePreview() {
            try {
                const jsonData = JSON.parse(document.getElementById('json_data').value);
                document.getElementById('json_preview').innerHTML = syntaxHighlight(jsonData);
            } catch (e) {
                document.getElementById('json_preview').innerHTML = '<span style="color:red">Invalid JSON: ' + e.message + '</span>';
            }
        }

        // Format JSON
        document.getElementById('format_btn').addEventListener('click', function() {
            try {
                const jsonData = JSON.parse(document.getElementById('json_data').value);
                document.getElementById('json_data').value = JSON.stringify(jsonData, null, 2);
                updatePreview();
            } catch (e) {
                alert('Invalid JSON: ' + e.message);
            }
        });

        // Minify JSON
        document.getElementById('minify_btn').addEventListener('click', function() {
            try {
                const jsonData = JSON.parse(document.getElementById('json_data').value);
                document.getElementById('json_data').value = JSON.stringify(jsonData);
                updatePreview();
            } catch (e) {
                alert('Invalid JSON: ' + e.message);
            }
        });

        // Initialize
        document.getElementById('json_data').addEventListener('input', updatePreview);
        updatePreview();
    </script>
</body>
</html>