<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['username'];

// Read db.json for user data
$db = json_decode(file_get_contents('db.json'), true);
$userData = null;
foreach ($db as $user) {
    if ($user['username'] === $username) {
        $userData = $user;
        break;
    }
}

if (!$userData) {
    header("Location: logout.php");
    exit;
}

$apikey = $userData['apikey'];
$userId = $userData['id'] ?? null;
$_SESSION['apikey'] = $apikey;

// Check if user has exceeded PIN attempts
if (isset($userData['pin_attempts']) && $userData['pin_attempts'] >= 3) {
    if (!isset($userData['pin_timeout']) || time() < $userData['pin_timeout']) {
        $pinTimeoutActive = true;
        $remainingTime = isset($userData['pin_timeout']) ? ceil(($userData['pin_timeout'] - time()) / 60) : 15;
    } else {
        $userData['pin_attempts'] = 0;
        unset($userData['pin_timeout']);
        file_put_contents('db.json', json_encode($db, JSON_PRETTY_PRINT));
    }
}

$userDir = "data/$username";
if (!is_dir($userDir)) mkdir($userDir, 0777, true);

$allFiles = glob("$userDir/*.json");
$files = array_filter($allFiles, function($file) {
    return !str_ends_with($file, '.schema.json') && 
           !str_ends_with($file, '.config.json') &&
           !str_ends_with($file, '.import.json');
});

function generateRandomString($length = 20) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title><?= htmlspecialchars($username) ?>@JSONDb</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .var-item {
      display: flex;
      align-items: center;
      margin-bottom: 8px;
    }
    .var-item input {
      flex-grow: 1;
      margin-right: 8px;
    }
    .delete-var {
      background-color: #4f1616ff;
      color: white;
      border: 2px solid #831e1eff;
      border-radius: 4px;
      padding: 6px 8px;
      cursor: pointer;
      margin-top:-8px;
    }
    .delete-var:hover {
      background-color: #6a0101ff;
      border: 2px solid #d92929ff;
    }
    .checkbox-options {
      display: flex;
      gap: 15px;
      margin-top: 8px;
    }
    .checkbox-option {
      display: flex;
      align-items: center;
    }
    .checkbox-option input[type="checkbox"] {
      margin-right: 5px;
    }
    #pinAttemptsInfo, #pinTimeoutInfo {
      margin-top: 10px;
      color: #ff6b6b;
      display: none;
    }
    .filename-link {
      text-decoration: none;
      color: inherit;
      cursor: pointer;
    }
    .filename-link:hover {
      text-decoration: underline;
    }
    .import-btn {
      background-color: #4CAF50;
      color: white;
      border: none;
      border-radius: 4px;
      padding: 6px 12px;
      cursor: pointer;
      margin-left: 5px;
    }
    .import-btn:hover {
      background-color: #45a049;
    }
    .import-btn:disabled {
      background-color: #cccccc;
      cursor: not-allowed;
    }
    .cancel-import-btn {
      background-color: #1a73e8;
      color: white;
      border: none;
      border-radius: 4px;
      padding: 6px 12px;
      cursor: pointer;
      margin-left: 5px;
    }
    .cancel-import-btn:hover {
      background-color: #1765cc;
    }
    .embedded-code-btn {
      background-color: #9c27b0;
      color: white;
      border: none;
      border-radius: 4px;
      padding: 6px 12px;
      cursor: pointer;
      margin-left: 5px;
    }
    .embedded-code-btn:hover {
      background-color: #7b1fa2;
    }
    #importModal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.4);
    }
    #importModal .modal-content {
      background-color: #0d1117;
      margin: 15% auto;
      padding: 20px;
      border: 1px solid #30363d;
      width: 80%;
      max-width: 500px;
      border-radius: 6px;
    }
    #importModal .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 20px;
    }
    #importModal .btn-primary {
      background-color: #238636;
    }
    #importModal .btn-secondary {
      background-color: #6e7681;
    }
    #importModal .btn-primary:hover {
      background-color: #2ea043;
    }
    #importModal .btn-secondary:hover {
      background-color: #8b949e;
    }
    .pin-disabled {
      opacity: 0.6;
      pointer-events: none;
    }
    .confirmation-modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.4);
    }
    .confirmation-content {
      background-color: #0d1117;
      margin: 15% auto;
      padding: 20px;
      border: 1px solid #30363d;
      width: 80%;
      max-width: 400px;
      border-radius: 6px;
      text-align: center;
    }
    .confirmation-actions {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
    }
    .confirmation-btn {
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      border: none;
    }
    .confirm-btn {
      background-color: #d32f2f;
      color: white;
    }
    .confirm-btn:hover {
      background-color: #b71c1c;
    }
    .cancel-btn {
      background-color: #6e7681;
      color: white;
    }
    .cancel-btn:hover {
      background-color: #8b949e;
    }
    #embeddedCodeModal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0px;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.4);
    }
    #embeddedCodeModal .modal-content {
      background-color: #0d1117;
      margin: 0 auto;
      padding: 20px;
      border: 1px solid #30363d;
      width: 80%;
      margin-top:-30px;
      max-width: 600px;
      border-radius: 6px;
    }
    #embeddedCodeModal .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 5px;
    }
    #embeddedCodeModal .modal-body {
      margin-bottom: 20px;
    }
    #embeddedCodeModal .modal-footer {
      display: flex;
      justify-content: flex-end;
    }
    #embeddedCodeContent {
      width: 100%;
      height: 200px;
      padding: 10px;
      background-color: #161b22;
      border: 1px solid #30363d;
      border-radius: 4px;
      color: #c9d1d9;
      font-family: monospace;
      white-space: pre-wrap;
      overflow-y: auto;
    }
    #copyEmbeddedCodeBtn {
      background-color: #238636;
      color: white;
      border: none;
      border-radius: 4px;
      padding: 8px 16px;
      cursor: pointer;
    }
    #copyEmbeddedCodeBtn:hover {
      background-color: #2ea043;
    }
    #getCodeModal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.4);
    }
    #getCodeModal .modal-content {
      background-color: #0d1117;
      margin: 15% auto;
      padding: 20px;
      border: 1px solid #30363d;
      width: 80%;
      max-width: 600px;
      border-radius: 6px;
    }
    #getCodeModal .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 5px;
    }
    #getCodeModal .modal-body {
      margin-bottom: 20px;
    }
    #getCodeModal .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    #getCodeContent {
      width: 100%;
      height: 200px;
      padding: 10px;
      background-color: #161b22;
      border: 1px solid #30363d;
      border-radius: 4px;
      color: #c9d1d9;
      font-family: monospace;
      white-space: pre-wrap;
      overflow-y: auto;
    }
    #copyGetCodeBtn, #downloadCodeBtn {
      background-color: #238636;
      color: white;
      border: none;
      border-radius: 4px;
      padding: 8px 16px;
      cursor: pointer;
    }
    #copyGetCodeBtn:hover, #downloadCodeBtn:hover {
      background-color: #2ea043;
    }
    #downloadCodeBtn.processing {
      background-color: #6e7681;
      cursor: not-allowed;
    }
    .get-code-btn {
      background-color: #607d8b;
      color: white;
      border: none;
      border-radius: 4px;
      padding: 6px 12px;
      cursor: pointer;
      margin-left: 5px;
    }
    .get-code-btn:hover {
      background-color: #455a64;
    }
    .not-found-message {
      color: #ff6b6b;
      font-style: italic;
      text-align: center;
      padding: 20px;
    }
    .loading-message {
      color: #c9d1d9;
      font-style: italic;
      text-align: center;
      padding: 20px;
    }
  </style>
</head>
<body>

<div class="header">
  <div class="header-top">
    <div>
      <h2>JSON DB :@<?= htmlspecialchars($username) ?></h2>
    </div>
    <div class="header-actions">
      <button onclick="openCreateModal()">Create JSON</button>
      <button onclick="openUploadModal()">Upload JSON</button>
      <button onclick="openAccountSettings()" <?= isset($pinTimeoutActive) ? 'class="pin-disabled" title="Account settings locked for '.$remainingTime.' minutes"' : '' ?>>Account Settings</button>
      <a href="logout.php"><button>Logout</button></a>
    </div>
  </div>
</div>

<h3>📁 Your JSON Files:</h3>
<table>
  <thead>
    <tr><th>File Name</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($files as $file): 
      $base = basename($file, '.json');
      $configFile = "data/$username/$base.config.json";
      $isPrivate = false;
      if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        $isPrivate = isset($config['private']) && $config['private'] === true;
      }
      
      $importFile = "data/$username/$base.import.json";
      $hasImportFile = file_exists($importFile);
      $hasEmbeddedCode = false;
      $embeddedCode = '';
      
      if ($hasImportFile) {
          $importData = json_decode(file_get_contents($importFile), true);
          $hasEmbeddedCode = isset($importData['embored_code']);
          if ($hasEmbeddedCode) {
              $embeddedCode = $importData['embored_code'];
          }
      }
      
      $tokenFile = "data/$username/$base.token";
      $token = '';
      if (file_exists($tokenFile)) {
        $token = file_get_contents($tokenFile);
      } else {
        $token = generateRandomString();
        file_put_contents($tokenFile, $token);
      }
    ?>
      <tr>
        <td>
          <span class="filename-link" onclick="handleFilenameClick('<?= $token ?>', '<?= urlencode($base) ?>', <?= $isPrivate ? 'true' : 'false' ?>)">
            <?= htmlspecialchars($base) ?>.json <?= $isPrivate ? '🔒' : '' ?>
          </span>
        </td>
        <td class="actions">
          <a href="add?file=<?= urlencode($base) ?>"><button>Add Data</button></a>
          <button onclick="openEditModal('<?= urlencode($base) ?>')">Edit Keys</button>
          <button onclick="openFileSettings('<?= urlencode($base) ?>', <?= $hasImportFile ? 'true' : 'false' ?>)">Options</button>
          <button class="get-code-btn" onclick="showGetCode('<?= $base ?>')">Get Code</button>
          <?php if ($hasImportFile): ?>
            <?php if ($hasEmbeddedCode): ?>
              <button class="embedded-code-btn" onclick="showEmbeddedCode('<?= $base ?>', `<?= htmlspecialchars($embeddedCode, ENT_QUOTES) ?>`)">Embedded Code</button>
            <?php endif; ?>
            <button class="cancel-import-btn" onclick="showCancelImportConfirmation('<?= $base ?>')">Cancel Import</button>
          <?php else: ?>
            <button class="import-btn" onclick="openImportModal('<?= $base ?>')">Import</button>
          <?php endif; ?>
          <a href="delete?file=<?= urlencode($base) ?>" onclick="return confirm('Delete this file?')"><button style="background-image: linear-gradient(to bottom, #ff4d4d, #cc0000);color#fff;">Delete</button></a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Import Confirmation Modal -->
<div id="importModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeImportModal()">&times;</span>
    <h3>Import Data</h3>
    <p>Are you sure you want to import data into <span id="importFilename"></span>? This will merge external data with your existing file.</p>
    <div class="modal-actions">
      <button class="btn-secondary" onclick="closeImportModal()">Cancel</button>
      <button class="btn-primary" onclick="confirmImport()">Yes, Import</button>
    </div>
  </div>
</div>

<!-- Cancel Import Confirmation Modal -->
<div id="cancelImportModal" class="confirmation-modal">
  <div class="confirmation-content">
    <span class="close" onclick="closeCancelImportModal()">&times;</span>
    <h3>Cancel Import</h3>
    <p>Are you sure you want to cancel the import and delete the import file for <span id="cancelImportFilename"></span>?</p>
    <div class="confirmation-actions">
      <button class="confirmation-btn cancel-btn" onclick="closeCancelImportModal()">No, Keep It</button>
      <button class="confirmation-btn confirm-btn" onclick="confirmCancelImport()">Yes, Cancel Import</button>
    </div>
  </div>
</div>

<!-- Embedded Code Modal -->
<div id="embeddedCodeModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Embedded Code for <span id="embeddedCodeFilename"></span></h3>
      <span class="close" onclick="closeEmbeddedCodeModal()">&times;</span>
    </div>
    <div class="modal-body">
      <div id="embeddedCodeContent"></div>
    </div>
    <div class="modal-footer">
      <button id="copyEmbeddedCodeBtn" onclick="copyEmbeddedCode()">Copy Code</button>
    </div>
  </div>
</div>

<!-- Get Code Modal -->
<div id="getCodeModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Code for <span id="getCodeFilename"></span></h3>
      <span class="close" onclick="closeGetCodeModal()">&times;</span>
    </div>
    <div class="modal-body">
      <div id="getCodeContent" class="loading-message">Loading...</div>
    </div>
    <div class="modal-footer" id="getCodeFooter" style="display:none;">
      <button id="copyGetCodeBtn" onclick="copyGetCode()">Copy Code</button>
      <button id="downloadCodeBtn" onclick="downloadCode()">Download Code</button>
    </div>
  </div>
</div>

<!-- File Settings Modal -->
<div id="fileSettingsModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeFileSettings()">&times;</span>
    <h3>File Settings</h3>
    <form id="fileSettingsForm">
      <input type="hidden" id="settingsFilename">
      <div id="filenameSettingsContainer">
        <label for="newFilename">New File Name (without .json):</label>
        <input type="text" id="newFilename" required>
      </div>
      <div>
        <label>Privacy Settings:</label>
        <div class="privacy-option">
          <input type="radio" id="privacyPublic" name="privacy" value="public">
          <label for="privacyPublic">Public (File content can be viewed by anyone with the link)</label>
        </div>
        <div class="privacy-option">
          <input type="radio" id="privacyPrivate" name="privacy" value="private">
          <label for="privacyPrivate">Private (File content will not be accessible via link)</label>
        </div>
      </div>
      <div id="fileSettingsAlert" class="custom-alert" style="display: none;"></div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeFileSettings()">Cancel</button>
        <button type="button" class="btn-primary" onclick="updateFileSettings()">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Link Modal -->
<div id="linkModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeLinkModal()">&times;</span>
    <h3>File Link</h3>
    <div style="margin-bottom: 15px;">
      <input type="text" id="fileLink" style="width: 100%; padding: 8px; border-radius: 2px; border: 1px solid #30363d; background-color: #0d1117; color: #c9d1d9;" readonly>
    </div>
    <button class="btn-primary" onclick="copyFileLink()">Copy Link</button>
    <div id="linkAlert" class="custom-alert" style="display: none;"></div>
  </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeUploadModal()">&times;</span>
    <h3>Upload JSON File</h3>
    <form id="uploadForm" enctype="multipart/form-data">
      <div>
        <label for="filename">Filename (without .json):</label>
        <input type="text" id="filename" name="filename" required>
      </div>
      <div>
        <label for="jsonfile">JSON File:</label>
        <input type="file" id="jsonfile" name="jsonfile" accept=".json" required>
      </div>
      <div id="loading">Uploading file...</div>
      <div id="uploadAlert" class="custom-alert"></div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeUploadModal()">Cancel</button>
        <button type="button" class="btn-primary" onclick="uploadFile()">Upload</button>
        <button id="fetchBtnInside" class="btn-primary" type="button" style="display:none;" onclick="openFetchFromUpload()">Fetch Variables</button>
      </div>
    </form>
  </div>
</div>

<!-- Variables Modal -->
<div id="variablesModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeVariablesModal()">&times;</span>
    <h3>Select Variable Types</h3>
    <div id="variablesContainer"></div>
    <div class="modal-actions">
      <button class="btn-secondary" onclick="closeVariablesModal()">Cancel</button>
      <button class="btn-primary" onclick="saveConfiguration()">Save Configuration</button>
    </div>
  </div>
</div>

<!-- Create Variable Modal -->
<div id="createModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeCreateModal()">&times;</span>
    <h3>Add Keys To Store Your Datas</h3>
    <form id="createForm" method="POST" action="create_handler.php">
      <div>
        <label>File Name (without .json):</label>
        <input type="text" name="filename" required>
      </div>
      <div>
        <label>Keys:</label>
        <div class="varlist" id="varlist">
          <div class="var-item">
            <input type="text" name="vars[]" placeholder="e.g. bookname" required>
            <button type="button" class="delete-var" onclick="removeVar(this)">Delete</button>
          </div>
        </div>
        <button type="button" class="btn-secondary" onclick="addVar()">+ Add More</button>
      </div>
      <div id="createAlert" class="custom-alert"></div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeCreateModal()">Cancel</button>
        <button type="button" class="btn-primary" onclick="submitCreateForm()">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Variable Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeEditModal()">&times;</span>
    <h3 id="editModalTitle">Edit Keyss</h3>
    <form id="editForm">
      <div id="var-list"></div>
      <button type="button" class="btn-secondary" onclick="addEditVar()">+ Add Keys</button>
      <div id="editAlert" class="custom-alert"></div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
        <button type="button" class="btn-primary" onclick="submitEditForm()">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Account Settings Modal -->
<div id="accountSettingsModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeAccountSettings()">&times;</span>
    <h3>Account Settings</h3>
    <div class="account-info">
      <div>
        <label>Username</label>
        <span id="accountUsername"><?= htmlspecialchars($username) ?></span>
      </div>
      <div>
        <label>Email</label>
        <span id="accountEmail"><?= htmlspecialchars($userData['email']) ?></span>
        <button class="edit-btn" onclick="editField('email')" <?= isset($pinTimeoutActive) ? 'disabled title="Account settings locked for '.$remainingTime.' minutes"' : '' ?>>Edit</button>
      </div>
      <div>
        <label>API Key</label>
        <span id="accountApiKey"><?= htmlspecialchars($apikey) ?></span>
        <button class="copy-btn" onclick="copyApiKey()">Copy</button>
      </div>
      <div>
        <label>Account Created</label>
        <span id="accountCreated"><?= htmlspecialchars($userData['created_at']) ?></span>
      </div>
    </div>
    <div id="accountEditForm" style="display:none;">
      <input type="hidden" id="editFieldName">
      <div id="editFieldContainer"></div>
      <button type="button" class="btn-primary" onclick="updateAccountField()">Update</button>
      <button type="button" class="btn-secondary" onclick="cancelEdit()">Cancel</button>
    </div>
    <div id="accountAlert" class="custom-alert"></div>
  </div>
</div>

<!-- PIN Verification Modal -->
<div id="pinModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closePinModal()">&times;</span>
    <h3>Verify PIN</h3>
    <p>Enter your 4-digit PIN to confirm changes:</p>
    <input type="password" id="pinInput" maxlength="4" placeholder="Enter 4-digit PIN" pattern="\d{4}" inputmode="numeric">
    <div id="pinAlert" class="custom-alert" style="display:none;"></div>
    <div id="pinAttemptsInfo">Attempts remaining: <?= isset($userData['pin_attempts']) ? (3 - $userData['pin_attempts']) : 3 ?></div>
    <div id="pinTimeoutInfo" style="display:none;"></div>
    <div class="modal-actions">
      <button type="button" class="btn-secondary" onclick="closePinModal()">Cancel</button>
      <button type="button" id="verifyPinBtn" class="btn-primary" onclick="verifyPin()">Verify</button>
    </div>
  </div>
</div>

<script>
let currentFilename = '';
let fileVariables = [];
let currentEditFile = '';
let currentEditField = '';
let currentEditValue = '';
let currentImportFilename = '';
let cancelImportFilename = '';
let currentCodeFilename = '';

function handleFilenameClick(token, filename, isPrivate) {
    if (isPrivate) {
        const url = `/`;
        window.open(url, '_self');
    } else {
        showFileLink(filename);
    }
}

// Get Code Modal Functions
function showGetCode(filename) {
    currentCodeFilename = filename;
    document.getElementById("getCodeFilename").textContent = filename + '.json';
    document.getElementById("getCodeFooter").style.display = "none";
    document.getElementById("getCodeContent").className = "loading-message";
    document.getElementById("getCodeContent").textContent = "Loading...";
    document.getElementById("getCodeModal").style.display = "block";
    
    fetch(`get_code.php?filename=${filename}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById("getCodeContent").className = "";
                document.getElementById("getCodeContent").textContent = data.content;
                document.getElementById("getCodeFooter").style.display = "flex";
            } else {
                document.getElementById("getCodeContent").className = "not-found-message";
                document.getElementById("getCodeContent").textContent = data.message || "Code file not found";
            }
        })
        .catch(error => {
            document.getElementById("getCodeContent").className = "not-found-message";
            document.getElementById("getCodeContent").textContent = "Error loading code file";
        });
}

function closeGetCodeModal() {
    document.getElementById("getCodeModal").style.display = "none";
}

function copyGetCode() {
    const code = document.getElementById("getCodeContent").textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.getElementById("copyGetCodeBtn");
        const originalText = btn.textContent;
        btn.textContent = "Copied!";
        setTimeout(() => {
            btn.textContent = originalText;
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy text: ', err);
    });
}

function downloadCode() {
    const btn = document.getElementById("downloadCodeBtn");
    const originalText = btn.textContent;
    
    btn.textContent = "Processing...";
    btn.classList.add("processing");
    btn.disabled = true;
    
    setTimeout(() => {
        const code = document.getElementById("getCodeContent").textContent;
        const blob = new Blob([code], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${currentCodeFilename}.code.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        btn.textContent = originalText;
        btn.classList.remove("processing");
        btn.disabled = false;
    }, 1000);
}

// Import Modal Functions
function openImportModal(filename) {
    currentImportFilename = filename;
    document.getElementById("importFilename").textContent = filename + '.json';
    document.getElementById("importModal").style.display = "block";
}

function closeImportModal() {
    document.getElementById("importModal").style.display = "none";
}

function confirmImport() {
    // Proceed with import
    const apikey = '<?= $apikey ?>';
    const userId = '<?= $userId ?>';
    window.location.href = `import.php?apikey=${apikey}&filename=${currentImportFilename}&status=allowed`;
}

// Embedded Code Functions
function showEmbeddedCode(filename, code) {
    document.getElementById("embeddedCodeFilename").textContent = filename + '.json';
    document.getElementById("embeddedCodeContent").textContent = code;
    document.getElementById("embeddedCodeModal").style.display = "block";
}

function closeEmbeddedCodeModal() {
    document.getElementById("embeddedCodeModal").style.display = "none";
}

function copyEmbeddedCode() {
    const code = document.getElementById("embeddedCodeContent").textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.getElementById("copyEmbeddedCodeBtn");
        const originalText = btn.textContent;
        btn.textContent = "Copied!";
        setTimeout(() => {
            btn.textContent = originalText;
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy text: ', err);
    });
}

// Cancel Import Functions
function showCancelImportConfirmation(filename) {
    cancelImportFilename = filename;
    document.getElementById("cancelImportFilename").textContent = filename + '.json';
    document.getElementById("cancelImportModal").style.display = "block";
}

function closeCancelImportModal() {
    document.getElementById("cancelImportModal").style.display = "none";
}

function confirmCancelImport() {
    // Delete the import file
    fetch(`cancel_import.php?filename=${cancelImportFilename}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to update the UI
                location.reload();
            } else {
                alert('Failed to cancel import: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while canceling import');
        });
}

// Link Modal Functions
function showFileLink(filename) {
    // Generate or get existing random token
    fetch(`get_or_create_token.php?file=${filename}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const fileLink = `${window.location.origin}/fetch?token=${data.token}`;
                document.getElementById("fileLink").value = fileLink;
                document.getElementById("linkAlert").style.display = "none";
            } else {
                document.getElementById("fileLink").value = "Error generating link: " + data.message;
            }
            document.getElementById("linkModal").style.display = "block";
        })
        .catch(error => {
            document.getElementById("fileLink").value = "Error generating link: " + error.message;
            document.getElementById("linkModal").style.display = "block";
        });
}

function closeLinkModal() {
    document.getElementById("linkModal").style.display = "none";
    document.getElementById("linkAlert").style.display = "none";
}

function copyFileLink() {
    const fileLink = document.getElementById("fileLink");
    if (fileLink.value.includes("Error")) {
        const alertBox = document.getElementById("linkAlert");
        alertBox.className = "custom-alert error";
        alertBox.textContent = "Cannot copy link - error occurred";
        alertBox.style.display = "block";
        return;
    }
    
    fileLink.select();
    document.execCommand('copy');
    
    const alertBox = document.getElementById("linkAlert");
    alertBox.className = "custom-alert";
    alertBox.textContent = 'Link copied to clipboard!';
    alertBox.style.display = "block";
    
    setTimeout(() => {
        alertBox.style.display = "none";
    }, 2000);
}

// File Settings Functions
function openFileSettings(filename, hasImportFile = false) {
    document.getElementById("settingsFilename").value = filename;
    
    // Hide filename change if import file exists
    const filenameContainer = document.getElementById("filenameSettingsContainer");
    if (hasImportFile) {
        filenameContainer.style.display = "none";
    } else {
        filenameContainer.style.display = "block";
        document.getElementById("newFilename").value = filename;
    }
    
    // Check current privacy status
    fetch(`get_file_config.php?file=${filename}`)
        .then(response => response.json())
        .then(config => {
            if (config && config.private) {
                document.getElementById("privacyPrivate").checked = true;
            } else {
                document.getElementById("privacyPublic").checked = true;
            }
            document.getElementById("fileSettingsModal").style.display = "block";
        })
        .catch(() => {
            document.getElementById("privacyPublic").checked = true;
            document.getElementById("fileSettingsModal").style.display = "block";
        });
}

function closeFileSettings() {
    document.getElementById("fileSettingsModal").style.display = "none";
    document.getElementById("fileSettingsAlert").style.display = "none";
    document.getElementById("filenameSettingsContainer").style.display = "block"; // Reset for next time
}

function updateFileSettings() {
    const oldFilename = document.getElementById("settingsFilename").value;
    const newFilename = document.getElementById("newFilename")?.value.trim() || oldFilename;
    const isPrivate = document.getElementById("privacyPrivate").checked;
    const alertBox = document.getElementById("fileSettingsAlert");
    
    const formData = new FormData();
    formData.append('old_filename', oldFilename);
    formData.append('new_filename', newFilename);
    formData.append('private', isPrivate);
    
    fetch('update_file_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertBox.className = "custom-alert";
            alertBox.textContent = "File settings updated successfully!";
            alertBox.style.display = "block";
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            alertBox.className = "custom-alert error";
            alertBox.textContent = data.message || "Error updating file settings";
            alertBox.style.display = "block";
        }
    })
    .catch(error => {
        alertBox.className = "custom-alert error";
        alertBox.textContent = "Error updating file settings";
        alertBox.style.display = "block";
    });
}

// Account Settings Functions
function openAccountSettings() {
    // Check if PIN verification is locked
    fetch('check_pin_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.timeout_active) {
                const minutes = Math.ceil(data.timeout_remaining / 60);
                document.getElementById("pinTimeoutInfo").textContent = `Please wait ${minutes} minutes before trying again.`;
                document.getElementById("pinTimeoutInfo").style.display = "block";
                document.getElementById("pinAttemptsInfo").style.display = "none";
            } else {
                document.getElementById("pinAttemptsInfo").textContent = `Attempts remaining: ${data.attempts_remaining || 3}`;
                document.getElementById("pinAttemptsInfo").style.display = "block";
                document.getElementById("pinTimeoutInfo").style.display = "none";
            }
            document.getElementById("accountSettingsModal").style.display = "block";
        })
        .catch(error => {
            console.error("Error checking PIN status:", error);
            document.getElementById("accountSettingsModal").style.display = "block";
        });
}

function closeAccountSettings() {
    document.getElementById("accountSettingsModal").style.display = "none";
    document.getElementById("accountEditForm").style.display = "none";
    document.getElementById("accountAlert").style.display = "none";
}

function editField(field) {
    currentEditField = field;
    const container = document.getElementById("accountEditForm");
    const fieldContainer = document.getElementById("editFieldContainer");
    const value = document.getElementById(`account${field.charAt(0).toUpperCase() + field.slice(1)}`).textContent;
    
    document.getElementById("editFieldName").value = field;
    fieldContainer.innerHTML = `
        <label for="editFieldValue">New ${field}:</label>
        <input type="text" id="editFieldValue" value="${value}">
    `;
    
    container.style.display = "block";
    document.getElementById("editFieldValue").focus();
}

function cancelEdit() {
    document.getElementById("accountEditForm").style.display = "none";
}

function copyApiKey() {
    const apiKey = document.getElementById("accountApiKey").textContent;
    navigator.clipboard.writeText(apiKey).then(() => {
        const alertBox = document.getElementById("accountAlert");
        alertBox.className = "custom-alert";
        alertBox.textContent = "API Key copied to clipboard!";
        alertBox.style.display = "block";
        setTimeout(() => {
            alertBox.style.display = "none";
        }, 2000);
    });
}

function updateAccountField() {
    const newValue = document.getElementById("editFieldValue").value.trim();
    const field = document.getElementById("editFieldName").value;
    
    if (!newValue) {
        alert("Please enter a value");
        return;
    }
    
    currentEditValue = newValue;
    document.getElementById("pinModal").style.display = "block";
    document.getElementById("pinInput").value = "";
    document.getElementById("pinAlert").style.display = "none";
    document.getElementById("verifyPinBtn").disabled = false;
}

function closePinModal() {
    document.getElementById("pinModal").style.display = "none";
}

function verifyPin() {
    const pin = document.getElementById("pinInput").value.trim();
    const alertBox = document.getElementById("pinAlert");
    const attemptsInfo = document.getElementById("pinAttemptsInfo");
    const timeoutInfo = document.getElementById("pinTimeoutInfo");
    const verifyBtn = document.getElementById("verifyPinBtn");

    if (pin.length !== 4 || !/^\d+$/.test(pin)) {
        alertBox.className = "custom-alert error";
        alertBox.textContent = "Please enter a valid 4-digit PIN";
        alertBox.style.display = "block";
        return;
    }

    verifyBtn.disabled = true;
    verifyBtn.textContent = "Verifying...";

    const formData = new FormData();
    formData.append('field', currentEditField);
    formData.append('value', currentEditValue);
    formData.append('pin', pin);

    fetch('verify_pin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertBox.className = "custom-alert";
            alertBox.textContent = "Update successful!";
            alertBox.style.display = "block";

            // Update the displayed value
            const fieldDisplay = document.getElementById(`account${currentEditField.charAt(0).toUpperCase() + currentEditField.slice(1)}`);
            if (fieldDisplay) {
                fieldDisplay.textContent = currentEditValue;
            }

            setTimeout(() => {
                closePinModal();
                document.getElementById("accountEditForm").style.display = "none";
                document.getElementById("accountAlert").className = "custom-alert";
                document.getElementById("accountAlert").textContent = "Account updated successfully!";
                document.getElementById("accountAlert").style.display = "block";
                
                // Reload the page to update any PIN lock status
                location.reload();
            }, 1000);
        } else {
            if (data.timeout) {
                attemptsInfo.style.display = "none";
                timeoutInfo.textContent = data.message;
                timeoutInfo.style.display = "block";
                verifyBtn.disabled = true;
                
                // Disable account settings button
                const accountSettingsBtn = document.querySelector('.header-actions button[onclick="openAccountSettings()"]');
                if (accountSettingsBtn) {
                    accountSettingsBtn.classList.add('pin-disabled');
                    accountSettingsBtn.title = `Account settings locked for ${Math.ceil(data.timeout_remaining / 60)} minutes`;
                }
                
                // Disable edit buttons
                const editButtons = document.querySelectorAll('.edit-btn');
                editButtons.forEach(btn => {
                    btn.disabled = true;
                    btn.title = `Account settings locked for ${Math.ceil(data.timeout_remaining / 60)} minutes`;
                });
                
                // Enable after timeout
                setTimeout(() => {
                    location.reload();
                }, data.timeout_remaining * 1000);
            } else {
                alertBox.className = "custom-alert error";
                alertBox.textContent = data.message;
                alertBox.style.display = "block";
                
                if (data.attempts_remaining !== undefined) {
                    attemptsInfo.textContent = `Attempts remaining: ${data.attempts_remaining}`;
                }
                
                verifyBtn.disabled = false;
                verifyBtn.textContent = "Verify";
            }
        }
    })
    .catch(error => {
        alertBox.className = "custom-alert error";
        alertBox.textContent = "Error during verification";
        alertBox.style.display = "block";
        verifyBtn.disabled = false;
        verifyBtn.textContent = "Verify";
    });
}

// Upload Modal Functions
function openUploadModal() {
    document.getElementById("uploadModal").style.display = "block";
}

function closeUploadModal() {
    document.getElementById("uploadModal").style.display = "none";
    document.getElementById("uploadForm").reset();
    document.getElementById("loading").style.display = "none";
    document.getElementById("uploadAlert").style.display = "none";
    document.getElementById("fetchBtnInside").style.display = "none";
}

// Variables Modal Functions
function openVariablesModal() {
    document.getElementById("variablesModal").style.display = "block";
}

function closeVariablesModal() {
    document.getElementById("variablesModal").style.display = "none";
}

// Create Modal Functions
function openCreateModal() {
    document.getElementById("createModal").style.display = "block";
}

function closeCreateModal() {
    document.getElementById("createModal").style.display = "none";
    document.getElementById("createForm").reset();
    document.getElementById("varlist").innerHTML = '<div class="var-item"><input type="text" name="vars[]" placeholder="e.g. bookname" required><button type="button" class="delete-var" onclick="removeVar(this)">Delete</button></div>';
    document.getElementById("createAlert").style.display = "none";
}

function addVar() {
    const div = document.createElement("div");
    div.className = "var-item";
    div.innerHTML = `
        <input type="text" name="vars[]" placeholder="e.g. price" required>
        <button type="button" class="delete-var" onclick="removeVar(this)">Delete</button>
    `;
    document.getElementById("varlist").appendChild(div);
}

function removeVar(button) {
    const varItem = button.parentElement;
    // Don't allow removing if it's the last item
    if (document.querySelectorAll('#varlist .var-item').length > 1) {
        varItem.remove();
    } else {
        alert("You must have at least one key.");
    }
}

function submitCreateForm() {
    const form = document.getElementById("createForm");
    const formData = new FormData(form);
    const alertBox = document.getElementById("createAlert");
    
    fetch('create_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes("successfully")) {
            alertBox.className = "custom-alert";
            alertBox.textContent = "Keys set created successfully!";
            alertBox.style.display = "block";
            setTimeout(() => {
                closeCreateModal();
                window.location.reload();
            }, 1500);
        } else {
            alertBox.className = "custom-alert error";
            alertBox.textContent = data;
            alertBox.style.display = "block";
        }
    })
    .catch(error => {
        alertBox.className = "custom-alert error";
        alertBox.textContent = "Error creating keys set";
        alertBox.style.display = "block";
    });
}

// Edit Modal Functions
function openEditModal(filename) {
    currentEditFile = filename;
    document.getElementById("editModalTitle").textContent = `Edit Keys for "${filename}"`;
    
    fetch(`get_schema.php?file=${filename}`)
        .then(res => res.json())
        .then(vars => {
            const varList = document.getElementById("var-list");
            varList.innerHTML = '';
            vars.forEach(v => {
                const div = document.createElement("div");
                div.className = "var-item";
                div.innerHTML = `
                    <input type="text" name="vars[]" value="${v}">
                    <button type="button" class="delete-var" onclick="removeEditVar(this)">Delete</button>
                `;
                varList.appendChild(div);
            });
            document.getElementById("editModal").style.display = "block";
        })
        .catch(() => {
            alert("Failed to load keys.");
        });
}

function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
    document.getElementById("editAlert").style.display = "none";
}

function addEditVar() {
    const div = document.createElement("div");
    div.className = "var-item";
    div.innerHTML = `
        <input type="text" name="vars[]" placeholder="New key">
        <button type="button" class="delete-var" onclick="removeEditVar(this)">Delete</button>
    `;
    document.getElementById("var-list").appendChild(div);
}

function removeEditVar(button) {
    const varItem = button.parentElement;
    // Don't allow removing if it's the last item
    if (document.querySelectorAll('#var-list .var-item').length > 1) {
        varItem.remove();
    } else {
        alert("You must have at least one key.");
    }
}

function submitEditForm() {
    const form = document.getElementById("editForm");
    const formData = new FormData();
    const inputs = document.querySelectorAll("#var-list input");
    const alertBox = document.getElementById("editAlert");
    
    const vars = Array.from(inputs).map(input => input.value).filter(v => v.trim() !== '');
    
    if (vars.length === 0) {
        alertBox.className = "custom-alert error";
        alertBox.textContent = "At least one variable must be present";
        alertBox.style.display = "block";
        return;
    }
    
    formData.append('vars', JSON.stringify(vars));
    
    fetch(`edit_handler.php?file=${currentEditFile}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes("successfully")) {
            alertBox.className = "custom-alert";
            alertBox.textContent = "Keys updated successfully!";
            alertBox.style.display = "block";
            setTimeout(() => {
                closeEditModal();
                window.location.reload();
            }, 1500);
        } else {
            alertBox.className = "custom-alert error";
            alertBox.textContent = data;
            alertBox.style.display = "block";
        }
    })
    .catch(error => {
        alertBox.className = "custom-alert error";
        alertBox.textContent = "Error updating variables";
        alertBox.style.display = "block";
    });
}

// Upload and Variables Functions
function uploadFile() {
    const filename = document.getElementById("filename").value;
    const fileInput = document.getElementById("jsonfile");
    const alertBox = document.getElementById("uploadAlert");

    if (!filename || !fileInput.files.length) {
        alertBox.className = "custom-alert error";
        alertBox.innerText = "Please enter a filename and select a file.";
        alertBox.style.display = "block";
        return;
    }

    currentFilename = filename;
    document.getElementById("loading").style.display = "block";

    const formData = new FormData();
    formData.append("filename", filename);
    formData.append("jsonfile", fileInput.files[0]);

    fetch("upload.php", { method: "POST", body: formData })
        .then(res => res.text())
        .then(data => {
            alertBox.className = data.includes("successfully") ? "custom-alert" : "custom-alert error";
            alertBox.innerText = data;
            alertBox.style.display = "block";
            if (data.includes("successfully")) {
                document.getElementById("fetchBtnInside").style.display = "inline-block";
            }
        })
        .catch(() => {
            alertBox.className = "custom-alert error";
            alertBox.innerText = "Upload failed.";
            alertBox.style.display = "block";
        })
        .finally(() => {
            document.getElementById("loading").style.display = "none";
        });
}

function openFetchFromUpload() {
    closeUploadModal();
    fetchVariablesFromSchema(currentFilename);
}

function fetchVariablesFromSchema(filename) {
    fetch(`get_schema.php?file=${filename}`)
        .then(res => res.json())
        .then(vars => {
            fileVariables = vars;
            displayVariables(vars);
            openVariablesModal();
        })
        .catch(() => alert("Failed to load variables."));
}

function displayVariables(variables) {
    const container = document.getElementById("variablesContainer");
    container.innerHTML = "";
    
    variables.forEach(variable => {
        const group = document.createElement("div");
        group.className = "variable-group";
        group.innerHTML = `<div class="variable-name">${variable}</div>`;
        
        const options = document.createElement("div");
        options.className = "checkbox-options";
        
        // Create radio buttons instead of checkboxes
        options.innerHTML = `
            <div class="checkbox-option">
                <input type="radio" id="${variable}_none" name="${variable}_type" value="none" checked>
                <label for="${variable}_none">None</label>
            </div>
            <div class="checkbox-option">
                <input type="radio" id="${variable}_image" name="${variable}_type" value="image">
                <label for="${variable}_image">image</label>
            </div>
            <div class="checkbox-option">
                <input type="radio" id="${variable}_code" name="${variable}_type" value="code">
                <label for="${variable}_code">code</label>
            </div>
            <div class="checkbox-option">
                <input type="radio" id="${variable}_array" name="${variable}_type" value="array">
                <label for="${variable}_array">array</label>
            </div>
        `;
        
        group.appendChild(options);
        container.appendChild(group);
    });
}

function saveConfiguration() {
    const imageVars = [], codeVars = [], arrayVars = [];

    document.querySelectorAll(".variable-group").forEach(group => {
        const name = group.querySelector(".variable-name").textContent;
        const selectedType = group.querySelector(`input[name="${name}_type"]:checked`).value;
        
        if (selectedType === "image") imageVars.push(name);
        if (selectedType === "code") codeVars.push(name);
        if (selectedType === "array") arrayVars.push(name);
    });

    const config = {};
    if (imageVars.length) config.image_vars = imageVars;
    if (arrayVars.length) config.array_vars = arrayVars;
    if (codeVars.length) config.autocode = { var: codeVars[0], len: 8, alpha: false, nums: true, special: true };

    fetch("save_config.php", {
        method: "POST",
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename: currentFilename, config })
    })
    .then(res => res.text())
    .then(data => {
        alert(data);
        if (data.includes("successfully")) location.reload();
    });
}
</script>
</body>

</html>
