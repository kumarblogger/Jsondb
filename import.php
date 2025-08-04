<?php
// Redirect if accessed directly or with false data
if (!isset($_GET['apikey']) || !isset($_GET['filename']) || !isset($_GET['status']) || $_GET['status'] !== 'allowed') {
    header("Location: dashboard.php");
    exit();
}

// Load database configuration
$db = json_decode(file_get_contents('db.json'), true);
if ($db === null || !is_array($db)) {
    header("Location: dashboard.php");
    exit();
}

// Find user with matching API key and get username
$currentUser = null;
foreach ($db as $user) {
    if ($user['apikey'] === $_GET['apikey']) {
        $currentUser = $user;
        break;
    }
}

// Verify API key
if (!$currentUser) {
    header("Location: dashboard.php");
    exit();
}

$username = $currentUser['username'];
$isMember = ($currentUser['member'] ?? 'no') === 'yes';
$filename = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['filename']);
$schemaFile = "data/{$username}/{$filename}.schema.json";
$importFile = "data/{$username}/{$filename}.import.json";
$embeddedCodeFile = "data/{$username}/{$filename}.code.txt";

// Create user data directory if it doesn't exist
if (!file_exists("data/{$username}")) {
    mkdir("data/{$username}", 0755, true);
}

// Create images directory if it doesn't exist
if (!file_exists("data/{$username}/images")) {
    mkdir("data/{$username}/images", 0755, true);
}

// Check if schema file exists
if (!file_exists($schemaFile)) {
    die("Schema file not found at: {$schemaFile}");
}

// Load schema - this contains only the variable names we should accept
$schema = json_decode(file_get_contents($schemaFile), true);
if ($schema === null || !is_array($schema)) {
    die("Invalid schema file at: {$schemaFile}");
}

// Process form submission - only accept variables that exist in schema
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imageVars = [];
    $arrayVars = [];
    
    foreach ($schema as $field) {
        if (isset($_POST[$field]) && $_POST[$field] !== 'none') {
            $fieldType = $_POST[$field];
            if ($fieldType === 'image') {
                $imageVars[] = $field;
            } elseif ($fieldType === 'array') {
                $arrayVars[] = $field;
            }
        }
    }
    
    // Generate embedded code
    $embeddedCode = generateEmbeddedCode($schema, $imageVars, $arrayVars, $filename, $isMember);
    
    // Prepare import data - only includes schema variables
    $importData = [
        'filename' => $filename,
        'schema_vars' => $schema, // Store the original schema variables
        'image_vars' => $imageVars,
        'array_vars' => $arrayVars,
        'embedded_code' => $embeddedCode
    ];
    
    // Save import configuration and embedded code
    if (file_put_contents($importFile, json_encode($importData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) &&
        file_put_contents($embeddedCodeFile, $embeddedCode)) {
        // Show processing popup and redirect
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .processing-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #3498db;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            margin-bottom: 0;
        }
    </style>
    <script>
        setTimeout(function() {
            window.location.href = "dashboard.php";
        }, 2000);
    </script>
</head>
<body>
    <div class="processing-container">
        <div class="spinner"></div>
        <h1>Processing</h1>
        <p>Configuration saved successfully. Redirecting to dashboard...</p>
    </div>
</body>
</html>';
        exit();
    } else {
        $errorMessage = "Failed to save configuration. Please check directory permissions.";
    }
}

function compressAndConvertToWebP($source, $destination, $quality = 80, $maxFileSize = 80000) {
    $imageInfo = getimagesize($source);
    $mime = $imageInfo['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$image) return false;
    
    $result = imagewebp($image, $destination, $quality);
    
    if ($result && filesize($destination) > $maxFileSize) {
        $quality = 70;
        $result = imagewebp($image, $destination, $quality);
        
        if ($result && filesize($destination) > $maxFileSize) {
            $quality = 60;
            $result = imagewebp($image, $destination, $quality);
            
            if ($result && filesize($destination) > $maxFileSize) {
                $quality = 50;
                $result = imagewebp($image, $destination, $quality);
            }
        }
    }
    
    imagedestroy($image);
    return $result;
}

function generateEmbeddedCode($schema, $imageVars, $arrayVars, $filename, $isMember) {
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($filename) . ' Data Collection</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="file"], textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .array-item { display: flex; margin-bottom: 10px; }
        .array-item input { flex-grow: 1; margin-right: 10px; }
        .add-array-item { background-color: #4CAF50; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; margin-bottom: 20px; }
        .add-array-item:hover { background-color: #45a049; }
        .remove-array-item { background-color: #f44336; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
        .remove-array-item:hover { background-color: #d32f2f; }
        .submit-btn { background-color: #2196F3; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; display: block; width: 100%; margin-top: 20px; }
        .submit-btn:hover { background-color: #0b7dda; }
        .image-preview { max-width: 200px; max-height: 200px; margin-top: 10px; display: none; }
        .member-message { color: #ff5722; font-size: 14px; margin-top: 5px; font-style: italic; }
        .disabled-option { opacity: 0.6; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="container">
        <h1>' . htmlspecialchars($filename) . ' Data Collection</h1>
        <form id="dataForm" enctype="multipart/form-data">
            <input type="hidden" name="filename" value="' . htmlspecialchars($filename) . '">';
    
    // Only generate fields for variables in the schema
    foreach ($schema as $field) {
        $html .= '
            <div class="form-group">';
        
        if (in_array($field, $imageVars)) {
            $html .= '
                <label for="' . htmlspecialchars($field) . '">' . htmlspecialchars($field) . ' (Image)</label>';
            
            if ($isMember) {
                $html .= '
                <input type="file" id="' . htmlspecialchars($field) . '" name="' . htmlspecialchars($field) . '" accept="image/*" onchange="previewImage(this)">
                <img id="' . htmlspecialchars($field) . '_preview" class="image-preview" src="#" alt="Image Preview">';
            } else {
                $html .= '
                <input type="file" id="' . htmlspecialchars($field) . '" name="' . htmlspecialchars($field) . '" accept="image/*" disabled>
                <div class="member-message">Image upload requires membership. Please upgrade to enable this feature.</div>';
            }
        } elseif (in_array($field, $arrayVars)) {
            $html .= '
                <label>' . htmlspecialchars($field) . ' (Array)</label>
                <div id="' . htmlspecialchars($field) . '_container">
                    <div class="array-item">
                        <input type="text" name="' . htmlspecialchars($field) . '[]" placeholder="Enter value">
                        <button type="button" class="remove-array-item" onclick="removeArrayItem(this)">Remove</button>
                    </div>
                </div>
                <button type="button" class="add-array-item" onclick="addArrayItem(\'' . htmlspecialchars($field) . '\')">Add Item</button>';
        } else {
            $html .= '
                <label for="' . htmlspecialchars($field) . '">' . htmlspecialchars($field) . '</label>
                <input type="text" id="' . htmlspecialchars($field) . '" name="' . htmlspecialchars($field) . '" placeholder="Enter ' . htmlspecialchars($field) . '">';
        }
        
        $html .= '
            </div>';
    }
    
    $html .= '
            <button type="submit" class="submit-btn">Submit Data</button>
        </form>
    </div>

    <script>
        function addArrayItem(fieldName) {
            const container = document.getElementById(fieldName + "_container");
            const newItem = document.createElement("div");
            newItem.className = "array-item";
            newItem.innerHTML = `
                <input type="text" name="${fieldName}[]" placeholder="Enter value">
                <button type="button" class="remove-array-item" onclick="removeArrayItem(this)">Remove</button>
            `;
            container.appendChild(newItem);
        }

        function removeArrayItem(button) {
            const container = button.closest("div.array-item");
            if (container && container.parentElement.children.length > 1) {
                container.remove();
            }
        }

        function previewImage(input) {
            const previewId = input.id + "_preview";
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = "block";
                }
                reader.readAsDataURL(file);
            } else {
                preview.src = "#";
                preview.style.display = "none";
            }
        }

        document.getElementById("dataForm").addEventListener("submit", function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            // Only process image fields that are in our schema and not disabled
            const imageInputs = document.querySelectorAll("input[type=\'file\']");
            imageInputs.forEach(input => {
                if (input.files.length > 0 && !input.disabled) {
                    formData.append(input.name + "_is_image", "true");
                }
            });
            
            fetch("http://localhost/save_data.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Data saved successfully!");
                    this.reset();
                    
                    document.querySelectorAll("[id$=\'_container\']").forEach(container => {
                        while (container.children.length > 1) {
                            container.removeChild(container.lastChild);
                        }
                    });
                    
                    document.querySelectorAll(".image-preview").forEach(preview => {
                        preview.src = "#";
                        preview.style.display = "none";
                    });
                } else {
                    alert("Error: " + data.message);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("An error occurred while saving data.");
            });
        });
    </script>
</body>
</html>';
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Configuration - <?php echo htmlspecialchars($filename); ?></title>
    <style>
        :root {
            --bg-color: #1a1a1a;
            --container-bg: #2d2d2d;
            --text-color: #e0e0e0;
            --border-color: #444;
            --field-bg: #3a3a3a;
            --hover-bg: #4a4a4a;
            --primary-color: #4a89dc;
            --primary-hover: #3a70c2;
            --success-bg: #2d4a2d;
            --success-border: #3a6a3a;
            --error-bg: #4a2d2d;
            --error-border: #6a3a3a;
            --option-bg: #3a3a3a;
            --option-hover: #4a4a4a;
            --disabled-option: #555;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        .container {
            width: 800px;
            margin: 20px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            background-color: var(--container-bg);
            border: 1px solid var(--border-color);
        }

        h1 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .field-group {
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 8px;
            background-color: var(--field-bg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .field-group:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .field-name {
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-color);
            font-size: 16px;
            display: flex;
            align-items: center;
        }

        .field-name:before {
            content: "•";
            color: var(--primary-color);
            margin-right: 8px;
            font-size: 20px;
        }

        .checkbox-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .checkbox-option {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 6px;
            background-color: var(--option-bg);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .checkbox-option:hover {
            background-color: var(--option-hover);
        }

        .checkbox-option.disabled {
            background-color: var(--disabled-option);
            cursor: not-allowed;
        }

        .checkbox-option input {
            margin-right: 8px;
            cursor: pointer;
        }

        .checkbox-option.disabled input {
            cursor: not-allowed;
        }

        .member-notice {
            color: #ff9800;
            font-size: 14px;
            margin-top: 5px;
            font-style: italic;
        }

        .success {
            color: #a0e0a0;
            text-align: center;
            margin-bottom: 25px;
            padding: 15px;
            background-color: var(--success-bg);
            border: 1px solid var(--success-border);
            border-radius: 8px;
            font-weight: 500;
        }

        .error {
            color: #e0a0a0;
            text-align: center;
            margin-bottom: 25px;
            padding: 15px;
            background-color: var(--error-bg);
            border: 1px solid var(--error-border);
            border-radius: 8px;
            font-weight: 500;
        }

        .submit-btn {
            display: block;
            width: 240px;
            margin: 40px auto 0;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }

        .info-note {
            color: #a0a0a0;
            font-size: 14px;
            text-align: center;
            margin-top: 25px;
            line-height: 1.5;
        }

        .file-path {
            font-family: monospace;
            background-color: var(--option-bg);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Import Configuration for: <?php echo htmlspecialchars($filename); ?></h1>
        
        <?php if (isset($errorMessage)): ?>
            <div class="error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
        
        <form method="post">
            <?php foreach ($schema as $field): ?>
                <div class="field-group">
                    <div class="field-name"><?php echo htmlspecialchars($field); ?></div>
                    <div class="checkbox-group">
                        <label class="checkbox-option">
                            <input type="radio" name="<?php echo htmlspecialchars($field); ?>" value="none" checked> None
                        </label>
                        <label class="checkbox-option <?php echo !$isMember ? 'disabled' : ''; ?>">
                            <input type="radio" name="<?php echo htmlspecialchars($field); ?>" value="image" <?php echo !$isMember ? 'disabled' : ''; ?>> Image
                        </label>
                        <label class="checkbox-option">
                            <input type="radio" name="<?php echo htmlspecialchars($field); ?>" value="array"> Array
                        </label>
                    </div>
                    <?php if (!$isMember): ?>
                        <div class="member-notice">Image upload requires membership. Current status: <?php echo $isMember ? 'Member' : 'Not a member'; ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="submit-btn">Save Configuration</button>
            <p class="info-note">
                Select the appropriate data type for each field in your schema file.<br>
                Configuration will be saved to: <span class="file-path">data/<?php echo htmlspecialchars($username); ?>/<?php echo htmlspecialchars($filename); ?>.import.json</span><br>
                Embedded code will be saved to: <span class="file-path">data/<?php echo htmlspecialchars($username); ?>/<?php echo htmlspecialchars($filename); ?>.code.txt</span>
            </p>
        </form>
    </div>
</body>
</html>