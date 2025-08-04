<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['username'];
$userDir = "data/$username";
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

if (!isset($_GET['file'])) {
    die("File not specified.");
}

$filename = basename($_GET['file']);
$schemaPath = "$userDir/$filename.schema.json";
$dataPath = "$userDir/$filename.json";
$configPath = "$userDir/$filename.config.json";

if (!file_exists($schemaPath)) {
    die("Variable schema not found.");
}

if (!file_exists($dataPath)) {
    file_put_contents($dataPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// Load or create config file
if (!file_exists($configPath)) {
    file_put_contents($configPath, json_encode([
        'autocode' => [],
        'image_vars' => [],
        'array_vars' => []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

$variables = json_decode(file_get_contents($schemaPath), true);
$data = json_decode(file_get_contents($dataPath), true);
$config = json_decode(file_get_contents($configPath), true);

// Handle image uploads
if (isset($_FILES['image_upload'])) {
    $targetDir = "data/$username/images/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $uniqueName = uniqid() . '_' . basename($_FILES['image_upload']['name']);
    $targetFile = $targetDir . $uniqueName;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Check if file is an image
    $check = getimagesize($_FILES['image_upload']['tmp_name']);
    if($check === false) {
        die(json_encode(['error' => 'File is not an image.']));
    }
    
    // Check file size (700KB max)
    if ($_FILES['image_upload']['size'] > 700000) {
        die(json_encode(['error' => 'Image size exceeds 700KB limit.']));
    }
    
    // Allow certain file formats
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    if (!in_array($imageFileType, $allowedTypes)) {
        die(json_encode(['error' => 'Only JPG, JPEG, PNG, GIF, WEBP, BMP, and SVG files are allowed.']));
    }
    
    // Move the file
    if (!move_uploaded_file($_FILES['image_upload']['tmp_name'], $targetFile)) {
        die(json_encode(['error' => 'Error uploading file.']));
    }
    
    $imageUrl = "$baseUrl/data/$username/images/" . $uniqueName;
    die(json_encode(['url' => $imageUrl]));
}

// Handle image URL upload
if (isset($_POST['image_url_upload'])) {
    $imageUrl = filter_var($_POST['image_url'], FILTER_VALIDATE_URL);
    if (!$imageUrl) {
        die(json_encode(['error' => 'Invalid URL.']));
    }
    
    // Check if URL points to an image
    $headers = get_headers($imageUrl, 1);
    if (!str_contains($headers[0], '200 OK')) {
        die(json_encode(['error' => 'Could not access image URL.']));
    }
    
    $contentType = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
    if (!str_contains($contentType, 'image/')) {
        die(json_encode(['error' => 'URL does not point to an image.']));
    }
    
    // Download the image
    $imageData = file_get_contents($imageUrl);
    if (!$imageData) {
        die(json_encode(['error' => 'Could not download image.']));
    }
    
    // Check file size (700KB max)
    if (strlen($imageData) > 700000) {
        die(json_encode(['error' => 'Image size exceeds 700KB limit.']));
    }
    
    $targetDir = "data/$username/images/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $imageInfo = getimagesizefromstring($imageData);
    if (!$imageInfo) {
        die(json_encode(['error' => 'Downloaded file is not a valid image.']));
    }
    
    $extension = '';
    switch($imageInfo[2]) {
        case IMAGETYPE_JPEG: $extension = 'jpg'; break;
        case IMAGETYPE_PNG: $extension = 'png'; break;
        case IMAGETYPE_GIF: $extension = 'gif'; break;
        case IMAGETYPE_WEBP: $extension = 'webp'; break;
        case IMAGETYPE_BMP: $extension = 'bmp'; break;
        case IMAGETYPE_SVG: $extension = 'svg'; break;
        default: die(json_encode(['error' => 'Unsupported image type.']));
    }
    
    $uniqueName = uniqid() . '_downloaded.' . $extension;
    $targetFile = $targetDir . $uniqueName;
    
    // Save the image
    if (!file_put_contents($targetFile, $imageData)) {
        die(json_encode(['error' => 'Error saving image.']));
    }
    
    $imageUrl = "$baseUrl/data/$username/images/" . $uniqueName;
    die(json_encode(['url' => $imageUrl]));
}

// Delete entries
if (isset($_GET['delete'])) {
    $deleteIndex = (int) $_GET['delete'];
    if (isset($data[$deleteIndex])) {
        array_splice($data, $deleteIndex, 1);
        file_put_contents($dataPath, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        header("Location: adddata.php?file=" . urlencode($filename));
        exit;
    }
}

// Handle edit
$editIndex = null;
if (isset($_GET['edit'])) {
    $editIndex = (int) $_GET['edit'];
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update']) && isset($_POST['update_index'])) {
        $updateIndex = (int) $_POST['update_index'];
        if (isset($data[$updateIndex])) {
            $set = $_POST['set'];
            $cleanSet = [];
            foreach ($variables as $var) {
                if (isset($config['array_vars']) && in_array($var, $config['array_vars'])) {
                    if (is_string($set[$var] ?? '')) {
                        $cleanSet[$var] = array_filter(array_map('trim', explode(';', $set[$var] ?? '')));
                    } else {
                        $cleanSet[$var] = $set[$var] ?? [];
                    }
                } else {
                    $cleanSet[$var] = trim($set[$var] ?? '');
                }
            }
            $data[$updateIndex] = $cleanSet;
            file_put_contents($dataPath, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            header("Location: adddata.php?file=" . urlencode($filename));
            exit;
        }
    }
    
    if (isset($_POST['set']) && !isset($_POST['update'])) {
        $set = $_POST['set'];
        $cleanSet = [];
        foreach ($variables as $var) {
            if (isset($config['array_vars']) && in_array($var, $config['array_vars'])) {
                if (is_string($set[$var] ?? '')) {
                    $cleanSet[$var] = array_filter(array_map('trim', explode(';', $set[$var] ?? '')));
                } else {
                    $cleanSet[$var] = $set[$var] ?? [];
                }
            } else {
                $cleanSet[$var] = trim($set[$var] ?? '');
            }
        }

        if (!empty($config['autocode']['var'])) {
            $targetVar = $config['autocode']['var'];
            $length = (int)$config['autocode']['len'];
            $useAlpha = $config['autocode']['alpha'];
            $useNums = $config['autocode']['nums'];
            $useSpecial = $config['autocode']['special'];

            $chars = '';
            if ($useAlpha) $chars .= 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            if ($useNums) $chars .= '0123456789';
            if ($useSpecial) $chars .= '!@#$%^&*()-_+=';

            if ($chars && isset($cleanSet[$targetVar]) && $cleanSet[$targetVar] === '') {
                $code = '';
                for ($i = 0; $i < $length; $i++) {
                    $code .= $chars[random_int(0, strlen($chars) - 1)];
                }
                $cleanSet[$targetVar] = $code;
            }
        }

        $data[] = $cleanSet;
        file_put_contents($dataPath, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        
        // Handle "Add More" functionality
        if (isset($_POST['submit_and_add'])) {
            // Clear the form but keep the popup open
            header("Location: adddata.php?file=" . urlencode($filename) . "&add_more=1");
            exit;
        } else {
            header("Location: adddata.php?file=" . urlencode($filename));
            exit;
        }
    }
    
    if (isset($_POST['autocode_config'])) {
        $config['autocode'] = [
            'var' => $_POST['autocode_var'],
            'len' => (int)$_POST['autocode_len'],
            'alpha' => $_POST['autocode_alpha'] === 'true',
            'nums' => $_POST['autocode_nums'] === 'true',
            'special' => $_POST['autocode_special'] === 'true'
        ];
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        header("Location: adddata.php?file=" . urlencode($filename));
        exit;
    }
    
    if (isset($_POST['image_vars_config'])) {
        $config['image_vars'] = json_decode($_POST['image_vars'], true);
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        header("Location: adddata.php?file=" . urlencode($filename));
        exit;
    }
    
    if (isset($_POST['array_vars_config'])) {
        $config['array_vars'] = json_decode($_POST['array_vars'], true);
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        header("Location: adddata.php?file=" . urlencode($filename));
        exit;
    }
}

// Check if we're in "add more" mode
$addMoreMode = isset($_GET['add_more']);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Add Data - <?= htmlspecialchars($filename) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/adddata.css">
  <style>
    /* Search box styles */
    .search-container {
      display: inline-block;
      margin-left: 20px;
      vertical-align: middle;
    }
    
    .search-box {
      padding: 6px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      width: 200px;
      font-size: 14px;
    }
    
    /* Row highlight animation */
    @keyframes highlightBlink {
      0% { background-color: rgba(255, 255, 0, 0.5); }
      50% { background-color: rgba(255, 255, 0, 0.2); }
      100% { background-color: rgba(255, 255, 0, 0.5); }
    }
    
    .highlighted-row {
      animation: highlightBlink 1s infinite;
    }
    
    .search-highlight {
      background-color: yellow;
      color: black;
    }
  </style>
</head>
<body>

<div class="custom-alert" id="customAlert"></div>

<div class="custom-confirm" id="customConfirm">
  <div id="confirmMessage"></div>
  <div class="custom-confirm-buttons">
    <button class="btn-secondary" id="confirmCancel">Cancel</button>
    <button class="btn-danger" id="confirmOk">OK</button>
  </div>
</div>

<div class="header">
  <div class="header-top">
    <h2>
      <a href="dashboard"><button class="btn-secondary">Dashboard</button></a>
      📁 Data Table: <span style="color:#58a6ff;font-style:italic;"><?= htmlspecialchars($filename) ?>.json</span>
      <div class="search-container">
        <input type="text" id="searchBox" class="search-box" placeholder="Search data..." autocomplete="off">
      </div>
    </h2>
  </div>
</div>

<!-- Add Data Button (Fixed position) -->
<button class="btn-primary add-data-btn" onclick="openAddDataPopup()">➕ Add Data</button>

<!-- Add Data Popup -->
<div class="modal" id="addDataModal">
  <div class="modal-content modal-content-wide">
    <div class="modal-header">
      <h3 class="modal-title"><?= isset($editIndex) ? 'Edit Entry' : 'Add New Entry' ?></h3>
      <div class="modal-header-actions">
        <button onclick="openCodePopup()" class="btn-warning">Auto-Code</button>
        <button onclick="openImageVarsPopup()" class="btn-warning">Image Vars</button>
        <button onclick="openArrayVarsPopup()" class="btn-warning" style="margin-right:20px;">Array Vars</button>
      </div>
      <span class="close-modal" onclick="closeAddDataPopup()">&times;</span>
    </div>
    <form method="POST" id="autoForm" enctype="multipart/form-data">
      <?php foreach ($variables as $v): ?>
        <div class="form-group" id="field-container-<?= htmlspecialchars($v) ?>">
          <label for="input-<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?>:</label>
          <?php if (isset($config['array_vars']) && in_array($v, $config['array_vars'])): ?>
            <div class="array-input-container">
              <input type="text" name="set[<?= htmlspecialchars($v) ?>]" id="input-<?= htmlspecialchars($v) ?>" 
                     value="<?= isset($editIndex) && isset($data[$editIndex][$v]) ? (is_array($data[$editIndex][$v]) ? implode(';', $data[$editIndex][$v]) : $data[$editIndex][$v]) : '' ?>" 
                     readonly>
              <button type="button" class="btn-pink" onclick="openArrayInputPopup('<?= htmlspecialchars($v) ?>')">Edit</button>
            </div>
            <?php if (isset($editIndex) && isset($data[$editIndex][$v])): ?>
              <div class="array-items-display" id="array-display-<?= htmlspecialchars($v) ?>">
                <?php 
                $items = is_array($data[$editIndex][$v]) ? $data[$editIndex][$v] : explode(';', $data[$editIndex][$v]);
                foreach ($items as $item): ?>
                  <?php if (!empty(trim($item))): ?>
                    <span class="array-item"><?= htmlspecialchars(trim($item)) ?></span>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php elseif (isset($config['image_vars']) && in_array($v, $config['image_vars'])): ?>
            <div class="image-upload-container">
              <button type="button" class="btn-purple" onclick="openImageUploadPopup('<?= htmlspecialchars($v) ?>')">Upload Image</button>
              <span id="image-link-<?= htmlspecialchars($v) ?>" class="image-link">
                <?php if (isset($editIndex) && isset($data[$editIndex][$v])): ?>
                  <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i', $data[$editIndex][$v])): ?>
                    <img src="<?= htmlspecialchars($data[$editIndex][$v]) ?>" class="image-preview"><br>
                  <?php endif; ?>
                <?php endif; ?>
              </span>
            </div>
            <input type="hidden" name="set[<?= htmlspecialchars($v) ?>]" id="input-<?= htmlspecialchars($v) ?>" 
                   value="<?= isset($editIndex) && isset($data[$editIndex][$v]) ? htmlspecialchars($data[$editIndex][$v]) : '' ?>">
          <?php else: ?>
            <input type="text" name="set[<?= htmlspecialchars($v) ?>]" id="input-<?= htmlspecialchars($v) ?>" 
                   value="<?= isset($editIndex) && isset($data[$editIndex][$v]) ? htmlspecialchars($data[$editIndex][$v]) : '' ?>"
                   <?= (isset($config['autocode']['var']) && $config['autocode']['var'] === $v) ? 'readonly' : '' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <input type="hidden" name="update_index" value="<?= isset($editIndex) ? $editIndex : '' ?>">
      <div class="action-buttons">
        <button type="submit" name="submit" id="submit-btn" class="btn-primary" <?= isset($editIndex) ? 'style="display:none"' : '' ?>>Submit</button>
        <button type="submit" name="update" id="update-btn" class="btn-primary" <?= !isset($editIndex) ? 'style="display:none"' : '' ?>>Update</button>
        <?php if (!isset($editIndex)): ?>
          <button type="submit" name="submit_and_add" id="submit-and-add-btn" class="btn-primary">Add More</button>
        <?php endif; ?>
        <button type="button" class="btn-secondary" onclick="closeAddDataPopup()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<table id="dataTable">
  <thead>
    <tr>
      <?php foreach ($variables as $var): ?>
        <th><?= htmlspecialchars($var) ?></th>
      <?php endforeach; ?>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($data as $index => $row): ?>
      <tr class="<?= $index === $editIndex ? 'editing-row' : '' ?>" id="row-<?= $index ?>">
        <?php foreach ($variables as $var): ?>
          <td>
            <?php if (isset($row[$var])): ?>
              <?php if (isset($config['image_vars']) && in_array($var, $config['image_vars']) && preg_match('/\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i', $row[$var])): ?>
                <img src="<?= htmlspecialchars($row[$var]) ?>" class="image-preview" alt="Image preview">
              <?php elseif (isset($config['array_vars']) && in_array($var, $config['array_vars'])): ?>
                <?php 
                $items = is_array($row[$var]) ? $row[$var] : explode(';', $row[$var]);
                foreach ($items as $item): ?>
                  <?php if (!empty(trim($item))): ?>
                    <span class="array-item"><?= htmlspecialchars(trim($item)) ?></span>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                <?= htmlspecialchars($row[$var]) ?>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td class="action-buttons">
          <button type="button" class="btn-warning" onclick="editEntry(<?= $index ?>)">Edit</button>
          <button type="button" class="btn-danger" onclick="confirmDeleteEntry(<?= $index ?>)">Delete</button>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Auto-code modal -->
<div class="modal" id="codeModal">
  <div class="modal-content">
    <div class="modal-header">
      <span class="close-modal" onclick="closeCodePopup()">&times;</span>
      <h3>Auto-Code Settings</h3>
    </div>
    <form method="POST" id="autocodeForm">
      <div class="form-group">
        <label for="targetVar">Select Variable:</label>
        <select id="targetVar" name="autocode_var" class="form-control">
          <?php foreach ($variables as $v): ?>
            <option value="<?= htmlspecialchars($v) ?>" <?= isset($config['autocode']['var']) && $config['autocode']['var'] === $v ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label for="codeLength">Length (1–16):</label>
        <input type="number" id="codeLength" name="autocode_len" min="1" max="16" 
               value="<?= isset($config['autocode']['len']) ? $config['autocode']['len'] : 8 ?>">
      </div>
      
      <div class="form-group">
        <label><input type="checkbox" id="alpha" name="autocode_alpha" value="true" 
               <?= isset($config['autocode']['alpha']) && $config['autocode']['alpha'] ? 'checked' : '' ?>> Include Alphabets</label>
      </div>
      
      <div class="form-group">
        <label><input type="checkbox" id="nums" name="autocode_nums" value="true" 
               <?= isset($config['autocode']['nums']) && $config['autocode']['nums'] ? 'checked' : '' ?>> Include Numbers</label>
      </div>
      
      <div class="form-group">
        <label><input type="checkbox" id="special" name="autocode_special" value="true" 
               <?= isset($config['autocode']['special']) && $config['autocode']['special'] ? 'checked' : '' ?>> Include Special Characters</label>
      </div>
      
      <input type="hidden" name="autocode_config" value="1">
      <div class="action-buttons">
        <button type="submit" class="btn-primary">Set</button>
        <button type="button" class="btn-secondary" onclick="closeCodePopup()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Image variables modal -->
<div class="modal" id="imageVarsModal">
  <div class="modal-content modal-content-wide">
    <div class="modal-header">
      <span class="close-modal" onclick="closeImageVarsPopup()">&times;</span>
      <h3>Image Variables Settings</h3>
    </div>
    <form method="POST" id="imageVarsForm">
      <p>Select which variables should be treated as image fields:</p>
      <div class="image-vars-list">
        <?php foreach ($variables as $v): ?>
          <label>
            <input type="checkbox" class="image-var-checkbox" name="image_vars[]" value="<?= htmlspecialchars($v) ?>"
              <?= isset($config['image_vars']) && in_array($v, $config['image_vars']) ? 'checked' : '' ?>>
            <?= htmlspecialchars($v) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="image_vars_config" value="1">
      <input type="hidden" name="image_vars" id="imageVarsInput">
      <div class="action-buttons">
        <button type="submit" class="btn-primary">Set Image Variables</button>
        <button type="button" class="btn-secondary" onclick="closeImageVarsPopup()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Array variables modal -->
<div class="modal" id="arrayVarsModal">
  <div class="modal-content modal-content-wide">
    <div class="modal-header">
      <span class="close-modal" onclick="closeArrayVarsPopup()">&times;</span>
      <h3>Array Variables Settings</h3>
    </div>
    <form method="POST" id="arrayVarsForm">
      <p>Select which variables should be treated as array fields (values will be split by semicolon):</p>
      <div class="array-vars-list">
        <?php foreach ($variables as $v): ?>
          <label>
            <input type="checkbox" class="array-var-checkbox" name="array_vars[]" value="<?= htmlspecialchars($v) ?>"
              <?= isset($config['array_vars']) && in_array($v, $config['array_vars']) ? 'checked' : '' ?>>
            <?= htmlspecialchars($v) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="array_vars_config" value="1">
      <input type="hidden" name="array_vars" id="arrayVarsInput">
      <div class="action-buttons">
        <button type="submit" class="btn-primary">Set Array Variables</button>
        <button type="button" class="btn-secondary" onclick="closeArrayVarsPopup()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Array input modal -->
<div class="modal" id="arrayInputModal">
  <div class="modal-content modal-content-wide">
    <div class="modal-header">
      <span class="close-modal" onclick="closeArrayInputPopup()">&times;</span>
      <h3 id="arrayInputModalTitle">Edit Array</h3>
    </div>
    <div class="array-popup-content">
      <input type="text" id="arrayItemInput" class="array-popup-input" placeholder="Enter new item...">
      <button type="button" class="btn-pink" onclick="addArrayItem()">Add Item</button>
      <div class="array-popup-items" id="arrayItemsContainer"></div>
      <input type="hidden" id="currentArrayVar">
      <div class="action-buttons">
        <button type="button" class="btn-primary" onclick="saveArrayItems()">Save</button>
        <button type="button" class="btn-secondary" onclick="closeArrayInputPopup()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Image upload options modal -->
<div class="modal" id="imageUploadOptionsModal">
  <div class="modal-content">
    <div class="modal-header">
      <span class="close-modal" onclick="closeImageUploadPopup()">&times;</span>
      <h3>Upload Image</h3>
    </div>
    <div class="upload-option-container">
      <button type="button" class="upload-option-btn btn-purple" onclick="uploadLocalImage()">Upload Local File</button>
      <button type="button" class="upload-option-btn btn-purple" onclick="uploadImageFromUrl()">Enter Image URL</button>
    </div>
  </div>
</div>

<!-- Image URL upload modal -->
<div class="modal" id="imageUrlModal">
  <div class="modal-content">
    <div class="modal-header">
      <span class="close-modal" onclick="closeImageUrlPopup()">&times;</span>
      <h3>Enter Image URL</h3>
    </div>
    <div>
      <input type="text" id="imageUrlInput" placeholder="https://example.com/image.jpg" style="width: 100%; padding: 10px; margin-bottom: 15px;">
      <div class="action-buttons">
        <button type="button" class="btn-primary" onclick="submitImageUrl()">Upload</button>
        <button type="button" class="btn-secondary" onclick="closeImageUrlPopup()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Upload progress modal -->
<div class="modal" id="uploadModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Uploading Image</h3>
    </div>
    <div id="uploadStatus">Preparing upload...</div>
    <div class="progress-container" id="progressContainer">
      <div class="progress-bar" id="progressBar"></div>
    </div>
  </div>
</div>

<input type="file" id="imageFileInput" style="display:none" accept="image/*">
<input type="hidden" id="currentImageVar">


<script>
// Custom alert function with different types
function showAlert(message, type = 'info') {
  const alert = document.getElementById('customAlert');
  alert.textContent = message;
  alert.className = 'custom-alert';
  
  // Add class based on type
  if (type === 'error') {
    alert.classList.add('error');
  } else if (type === 'success') {
    alert.classList.add('success');
  } else if (type === 'warning') {
    alert.classList.add('warning');
  }
  
  alert.style.display = 'block';
  
  // Hide after 3 seconds
  setTimeout(() => {
    alert.style.display = 'none';
  }, 3000);
}

// Custom confirm function
function showConfirm(message, callback) {
  const confirm = document.getElementById('customConfirm');
  const messageEl = document.getElementById('confirmMessage');
  const okBtn = document.getElementById('confirmOk');
  const cancelBtn = document.getElementById('confirmCancel');
  
  messageEl.textContent = message;
  confirm.style.display = 'block';
  
  const handleResponse = (response) => {
    confirm.style.display = 'none';
    callback(response);
    
    // Remove event listeners
    okBtn.onclick = null;
    cancelBtn.onclick = null;
  };
  
  okBtn.onclick = () => handleResponse(true);
  cancelBtn.onclick = () => handleResponse(false);
}

function confirmDeleteEntry(index) {
  showConfirm('Are you sure you want to delete this item?', (confirmed) => {
    if (confirmed) {
      window.location.href = `adddata.php?file=<?= urlencode($filename) ?>&delete=${index}`;
    }
  });
}

function validateForm() {
  let isValid = true;
  const variables = <?= json_encode($variables, JSON_UNESCAPED_UNICODE) ?>;
  const config = <?= json_encode($config, JSON_UNESCAPED_UNICODE) ?>;
  
  variables.forEach(v => {
    const input = document.getElementById(`input-${v}`);
    if (input && input.value.trim() === '' && 
        !(config.autocode && config.autocode.var === v && config.autocode.len > 0)) {
      showAlert(`Please fill in the ${v} field`, 'error');
      input.focus();
      isValid = false;
      return false; // break loop
    }
  });
  
  return isValid;
}

// Attach validation to form submission
document.getElementById('autoForm').addEventListener('submit', function(e) {
  if (!validateForm()) {
    e.preventDefault();
  }
});

// Add Data Popup functions
function openAddDataPopup() {
  document.getElementById("addDataModal").style.display = "flex";
  // Reset form if not in edit mode or add more mode
  if (!<?= isset($editIndex) ? 'true' : 'false' ?> && !<?= $addMoreMode ? 'true' : 'false' ?>) {
    document.getElementById('autoForm').reset();
    // Clear image previews
    const imageVars = <?= isset($config['image_vars']) ? json_encode($config['image_vars'], JSON_UNESCAPED_UNICODE) : '[]' ?>;
    imageVars.forEach(varName => {
      const linkSpan = document.getElementById(`image-link-${varName}`);
      if (linkSpan) {
        linkSpan.innerHTML = '';
      }
      const input = document.getElementById(`input-${varName}`);
      if (input) {
        input.value = '';
      }
    });
    // Clear array displays
    const arrayVars = <?= isset($config['array_vars']) ? json_encode($config['array_vars'], JSON_UNESCAPED_UNICODE) : '[]' ?>;
    arrayVars.forEach(varName => {
      const displayContainer = document.getElementById(`array-display-${varName}`);
      if (displayContainer) {
        displayContainer.innerHTML = '';
      }
    });
  }
}

function closeAddDataPopup() {
  document.getElementById("addDataModal").style.display = "none";
  // Redirect to clear edit mode and add more mode
  if (<?= isset($editIndex) || $addMoreMode ? 'true' : 'false' ?>) {
    window.location.href = `adddata.php?file=<?= urlencode($filename) ?>`;
  }
}

function editEntry(index) {
  window.location.href = `adddata.php?file=<?= urlencode($filename) ?>&edit=${index}`;
}

function openCodePopup() {
  document.getElementById("codeModal").style.display = "flex";
}

function closeCodePopup() {
  document.getElementById("codeModal").style.display = "none";
}

function openImageVarsPopup() {
  document.getElementById("imageVarsModal").style.display = "flex";
}

function closeImageVarsPopup() {
  document.getElementById("imageVarsModal").style.display = "none";
}

function openArrayVarsPopup() {
  document.getElementById("arrayVarsModal").style.display = "flex";
}

function closeArrayVarsPopup() {
  document.getElementById("arrayVarsModal").style.display = "none";
}

let arrayItems = [];

function openArrayInputPopup(varName) {
  const input = document.getElementById(`input-${varName}`);
  const currentValue = input.value;
  arrayItems = currentValue ? currentValue.split(';').filter(item => item.trim() !== '') : [];
  
  document.getElementById('currentArrayVar').value = varName;
  document.getElementById('arrayInputModalTitle').textContent = `Edit ${varName} Array`;
  renderArrayItems();
  document.getElementById("arrayInputModal").style.display = "flex";
  document.getElementById("arrayItemInput").focus();
}

function closeArrayInputPopup() {
  document.getElementById("arrayInputModal").style.display = "none";
}

function addArrayItem() {
  const input = document.getElementById('arrayItemInput');
  const item = input.value.trim();
  if (item) {
    arrayItems.push(item);
    input.value = '';
    renderArrayItems();
    input.focus();
  }
}

function removeArrayItem(index) {
  arrayItems.splice(index, 1);
  renderArrayItems();
}

function renderArrayItems() {
  const container = document.getElementById('arrayItemsContainer');
  container.innerHTML = '';
  
  if (arrayItems.length === 0) {
    container.innerHTML = '<div style="padding: 10px; text-align: center; color: #8b949e;">No items added yet</div>';
    return;
  }
  
  arrayItems.forEach((item, index) => {
    const itemDiv = document.createElement('div');
    itemDiv.className = 'array-popup-item';
    itemDiv.innerHTML = `
      <span>${item}</span>
      <span class="array-popup-remove" onclick="removeArrayItem(${index})" title="Remove item">×</span>
    `;
    container.appendChild(itemDiv);
  });
}

function saveArrayItems() {
  const varName = document.getElementById('currentArrayVar').value;
  const value = arrayItems.join(';');
  
  document.getElementById(`input-${varName}`).value = value;
  
  const displayContainer = document.getElementById(`array-display-${varName}`);
  if (displayContainer) {
    if (arrayItems.length > 0) {
      displayContainer.innerHTML = arrayItems.map(item => `<span class="array-item">${item}</span>`).join('');
    } else {
      displayContainer.innerHTML = '';
    }
  }
  
  closeArrayInputPopup();
  showAlert('Array saved successfully', 'success');
}

// Image upload functions
function openImageUploadPopup(varName) {
  document.getElementById('currentImageVar').value = varName;
  document.getElementById("imageUploadOptionsModal").style.display = "flex";
}

function closeImageUploadPopup() {
  document.getElementById("imageUploadOptionsModal").style.display = "none";
}

function uploadLocalImage() {
  closeImageUploadPopup();
  const fileInput = document.getElementById('imageFileInput');
  fileInput.onchange = function() {
    if (fileInput.files.length > 0) {
      uploadImageFile(fileInput.files[0]);
    }
  };
  fileInput.click();
}

function uploadImageFromUrl() {
  closeImageUploadPopup();
  document.getElementById("imageUrlModal").style.display = "flex";
  document.getElementById("imageUrlInput").focus();
}

function closeImageUrlPopup() {
  document.getElementById("imageUrlModal").style.display = "none";
}

function submitImageUrl() {
  const url = document.getElementById('imageUrlInput').value.trim();
  if (!url) {
    showAlert('Please enter a URL', 'error');
    return;
  }
  
  closeImageUrlPopup();
  
  const uploadModal = document.getElementById('uploadModal');
  const progressContainer = document.getElementById('progressContainer');
  const progressBar = document.getElementById('progressBar');
  const uploadStatus = document.getElementById('uploadStatus');
  
  uploadModal.style.display = 'flex';
  progressContainer.style.display = 'block';
  uploadStatus.textContent = 'Downloading image...';
  progressBar.style.width = '0%';
  
  // Use fetch API to download the image
  fetch('adddata.php?file=<?= urlencode($filename) ?>', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `image_url_upload=1&image_url=${encodeURIComponent(url)}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.url) {
      const varName = document.getElementById('currentImageVar').value;
      document.getElementById(`input-${varName}`).value = data.url;
      const linkSpan = document.getElementById(`image-link-${varName}`);
      linkSpan.innerHTML = `<img src="${data.url}" class="image-preview"><br>`;
      uploadStatus.textContent = 'Upload complete!';
      setTimeout(() => {
        uploadModal.style.display = 'none';
      }, 1000);
      showAlert('Image uploaded successfully!', 'success');
    } else if (data.error) {
      showAlert(data.error, 'error');
      uploadModal.style.display = 'none';
    }
  })
  .catch(error => {
    showAlert('Error uploading image: ' + error.message, 'error');
    uploadModal.style.display = 'none';
  });
}

function uploadImageFile(file) {
  const uploadModal = document.getElementById('uploadModal');
  const progressContainer = document.getElementById('progressContainer');
  const progressBar = document.getElementById('progressBar');
  const uploadStatus = document.getElementById('uploadStatus');
  
  uploadModal.style.display = 'flex';
  progressContainer.style.display = 'block';
  uploadStatus.textContent = 'Uploading...';
  progressBar.style.width = '0%';
  
  const formData = new FormData();
  formData.append('image_upload', file);
  
  const xhr = new XMLHttpRequest();
  xhr.open('POST', window.location.href, true);
  
  xhr.upload.onprogress = function(e) {
    if (e.lengthComputable) {
      const percentComplete = (e.loaded / e.total) * 100;
      progressBar.style.width = percentComplete + '%';
      uploadStatus.textContent = `Uploading... ${Math.round(percentComplete)}%`;
    }
  };
  
  xhr.onload = function() {
    if (xhr.status === 200) {
      try {
        const response = JSON.parse(xhr.responseText);
        if (response.url) {
          const varName = document.getElementById('currentImageVar').value;
          document.getElementById(`input-${varName}`).value = response.url;
          const linkSpan = document.getElementById(`image-link-${varName}`);
          linkSpan.innerHTML = `<img src="${response.url}" class="image-preview"><br>`;
          uploadStatus.textContent = 'Upload complete!';
          setTimeout(() => {
            uploadModal.style.display = 'none';
          }, 1000);
          showAlert('Image uploaded successfully!', 'success');
        } else if (response.error) {
          showAlert(response.error, 'error');
          uploadModal.style.display = 'none';
        }
      } catch (e) {
        showAlert('Error processing upload', 'error');
        uploadModal.style.display = 'none';
      }
    } else {
      showAlert('Upload failed', 'error');
      uploadModal.style.display = 'none';
    }
  };
  
  xhr.onerror = function() {
    showAlert('Upload error', 'error');
    uploadModal.style.display = 'none';
  };
  
  xhr.send(formData);
}

window.onclick = function(event) {
  if (event.target.className === 'modal') {
    event.target.style.display = 'none';
  }
};

document.getElementById('imageVarsForm').onsubmit = function() {
  const checkboxes = document.querySelectorAll('.image-var-checkbox:checked');
  const imageVars = Array.from(checkboxes).map(cb => cb.value);
  document.getElementById('imageVarsInput').value = JSON.stringify(imageVars);
  return true;
};

document.getElementById('arrayVarsForm').onsubmit = function() {
  const checkboxes = document.querySelectorAll('.array-var-checkbox:checked');
  const arrayVars = Array.from(checkboxes).map(cb => cb.value);
  document.getElementById('arrayVarsInput').value = JSON.stringify(arrayVars);
  return true;
};

// Handle Enter key in array item input
document.getElementById('arrayItemInput').addEventListener('keypress', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    addArrayItem();
  }
});

// Handle Enter key in image URL input
document.getElementById('imageUrlInput').addEventListener('keypress', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    submitImageUrl();
  }
});

// Instant search functionality
let currentHighlightedRow = null;
let highlightTimeout = null;

document.getElementById('searchBox').addEventListener('input', function(e) {
  const searchTerm = e.target.value.trim().toLowerCase();
  const table = document.getElementById('dataTable');
  const rows = table.getElementsByTagName('tr');
  
  // Remove previous highlights
  if (currentHighlightedRow) {
    currentHighlightedRow.classList.remove('highlighted-row');
    clearTimeout(highlightTimeout);
  }
  
  if (searchTerm.length < 1) {
    return;
  }
  
  // Search through all rows (skip header row)
  for (let i = 1; i < rows.length; i++) {
    const cells = rows[i].getElementsByTagName('td');
    let rowMatches = false;
    
    // Check each cell in the row
    for (let j = 0; j < cells.length; j++) {
      const cellText = cells[j].textContent.toLowerCase();
      if (cellText.includes(searchTerm)) {
        rowMatches = true;
        break;
      }
    }
    
    // If row matches, highlight it and move to top
    if (rowMatches) {
      // Remove highlight class from any previously highlighted row
      if (currentHighlightedRow) {
        currentHighlightedRow.classList.remove('highlighted-row');
      }
      
      // Highlight the matching row
      rows[i].classList.add('highlighted-row');
      currentHighlightedRow = rows[i];
      
      // Scroll to the row
      rows[i].scrollIntoView({ behavior: 'smooth', block: 'center' });
      
      // Remove highlight after 10 seconds
      highlightTimeout = setTimeout(() => {
        rows[i].classList.remove('highlighted-row');
        currentHighlightedRow = null;
      }, 10000);
      
      // Stop after first match
      break;
    }
  }
});

window.onload = function() {
  <?php if (isset($editIndex) || $addMoreMode): ?>
    // If in edit mode or add more mode, open the add data popup automatically
    openAddDataPopup();
    
    // Set up form for editing
    const imageVars = <?= isset($config['image_vars']) ? json_encode($config['image_vars'], JSON_UNESCAPED_UNICODE) : '[]' ?>;
    imageVars.forEach(varName => {
      const input = document.getElementById(`input-${varName}`);
      if (input && input.value && input.value.match(/\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i)) {
        const linkSpan = document.getElementById(`image-link-${varName}`);
        if (linkSpan) {
          linkSpan.innerHTML = `<img src="${input.value}" class="image-preview"><br>`;
        }
      }
    });
    
    const arrayVars = <?= isset($config['array_vars']) ? json_encode($config['array_vars'], JSON_UNESCAPED_UNICODE) : '[]' ?>;
    arrayVars.forEach(varName => {
      const input = document.getElementById(`input-${varName}`);
      if (input) {
        const items = input.value.split(';').filter(item => item.trim() !== '');
        const displayContainer = document.getElementById(`array-display-${varName}`);
        if (displayContainer) {
          displayContainer.innerHTML = items.map(item => `<span class="array-item">${item}</span>`).join('');
        }
      }
    });
  <?php endif; ?>
  
  // Make auto-code fields readonly
  const config = <?= json_encode($config, JSON_UNESCAPED_UNICODE) ?>;
  if (config.autocode && config.autocode.var) {
    const input = document.getElementById(`input-${config.autocode.var}`);
    if (input) {
      input.readOnly = true;
    }
  }
};
</script>

</body>
</html>