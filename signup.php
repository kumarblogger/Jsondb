<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Database file
define('DB_FILE', 'db.json');

// Function to generate random user ID
function generateUserID($length = 16) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $users = file_exists(DB_FILE) ? json_decode(file_get_contents(DB_FILE), true) : [];
    
    // First step: username, email, password
    if (isset($_POST['username']) && !isset($_POST['verify_pin'])) {
        $uname = trim($_POST['username']);
        $email = trim($_POST['email']);
        $pass = $_POST['password'];
        $pass_confirm = $_POST['password_confirm'];
        
        // Validate inputs
        if (empty($uname) || empty($email) || empty($pass) || empty($pass_confirm)) {
            $error = "All fields are required";
        } elseif (strlen($uname) < 4) {
            $error = "Username must be at least 4 characters";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $uname)) {
            $error = "Username can only contain letters, numbers and underscores";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (!preg_match('/@gmail\.com$|@googlemail\.com$/i', $email)) {
            $error = "Only Gmail accounts are allowed";
        } elseif (strlen($pass) < 8) {
            $error = "Password must be at least 8 characters";
        } elseif ($pass !== $pass_confirm) {
            $error = "Passwords do not match";
        } else {
            // Check for duplicate usernames or emails
            foreach ($users as $u) {
                if ($u['username'] === $uname) {
                    $error = "Username already exists";
                    break;
                }
                if ($u['email'] === $email) {
                    $error = "Email already registered";
                    break;
                }
            }
            
            if (!isset($error)) {
                // Store in session for step 2
                $_SESSION['signup_data'] = [
                    'username' => $uname,
                    'email' => $email,
                    'password' => $pass
                ];
                $show_pin_step = true;
            }
        }
    }
    
    // Second step: PIN verification
    if (isset($_POST['verify_pin'])) {
        $pin = trim($_POST['pin']);
        $pin_confirm = trim($_POST['pin_confirm']);
        
        if (empty($pin) || empty($pin_confirm)) {
            $pin_error = "PIN fields are required";
        } elseif (!preg_match('/^\d{4}$/', $pin)) {
            $pin_error = "PIN must be exactly 4 digits";
        } elseif ($pin !== $pin_confirm) {
            $pin_error = "PINs do not match";
        } else {
            // Get data from session
            $signup_data = $_SESSION['signup_data'];
            
            // Create new user
            $userid = generateUserID();
            $hashedPass = password_hash($signup_data['password'], PASSWORD_DEFAULT);
            $apikey = bin2hex(random_bytes(8)); // 16-char API key
            
            $users[] = [
                "userid" => $userid,
                "username" => $signup_data['username'],
                "email" => $signup_data['email'],
                "password" => $hashedPass,
                "pin" => $pin,
                "apikey" => $apikey,
                "created_at" => date('Y-m-d H:i:s')
            ];
            
            if (file_put_contents(DB_FILE, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                $pin_error = "Failed to save user data";
            } else {
                // Create user directory based on user ID only
                if (!file_exists("data/$userid") && !mkdir("data/$userid", 0755, true)) {
                    $pin_error = "Failed to create user directory";
                } else {
                    // Clear signup session
                    unset($_SESSION['signup_data']);
                    
                    // Set login session
                    $_SESSION['userid'] = $userid;
                    $_SESSION['username'] = $signup_data['username'];
                    $_SESSION['email'] = $signup_data['email'];
                    $_SESSION['apikey'] = $apikey;
                    
                    header("Location: dashboard.php");
                    exit();
                }
            }
        }
        
        $show_pin_step = true;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: #e0e0e0;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .signup-box {
            background-color: #1e1e1e;
            border: 1px solid #333;
            border-radius: 5px;
            padding: 25px;
            width: 320px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        h2 {
            margin-top: 0;
            margin-bottom: 20px;
            text-align: center;
            color: #ffffff;
        }

        .error {
            color: #ff5555;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
            padding: 8px;
            background-color: rgba(255,85,85,0.1);
            border-radius: 3px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            background-color: #2d2d2d;
            border: 1px solid #444;
            border-radius: 3px;
            color: #ffffff;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #555;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #3a7bd5;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }

        button:hover {
            background-color: #3366cc;
        }

        .links {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #aaa;
        }

        .links a {
            color: #3a7bd5;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .gmail-notice {
            font-size: 12px;
            color: #aaa;
            margin-top: -10px;
            margin-bottom: 15px;
            text-align: center;
        }

        .password-rules {
            font-size: 12px;
            color: #aaa;
            margin-top: 5px;
        }

        .pin-notice {
            font-size: 12px;
            color: #aaa;
            margin-top: -10px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="signup-box">
        <?php if (isset($show_pin_step)): ?>
            <!-- PIN Verification Step -->
            <h2>Set Verification PIN</h2>
            <div class="pin-notice">This 4-digit PIN will be used for account verification</div>

            <?php if (isset($pin_error)): ?>
                <div class="error"><?= htmlspecialchars($pin_error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="pin">4-digit PIN</label>
                    <input type="number" id="pin" name="pin" required maxlength="4"
                           placeholder="e.g. 1234"
                           oninput="if(this.value.length > 4) this.value = this.value.slice(0,4);">
                </div>

                <div class="form-group">
                    <label for="pin_confirm">Confirm PIN</label>
                    <input type="number" id="pin_confirm" name="pin_confirm" required maxlength="4"
                           placeholder="Re-enter your PIN"
                           oninput="if(this.value.length > 4) this.value = this.value.slice(0,4);">
                </div>

                <button type="submit" name="verify_pin">Complete Registration</button>
            </form>

        <?php else: ?>
            <!-- Initial Signup Step -->
            <h2>Sign Up</h2>
            <div class="gmail-notice">Only Gmail accounts allowed (@gmail.com)</div>

            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required 
                           value="<?= isset($uname) ? htmlspecialchars($uname) : '' ?>"
                           placeholder="4+ characters, letters/numbers/_">
                </div>

                <div class="form-group">
                    <label for="email">Gmail Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?= isset($email) ? htmlspecialchars($email) : '' ?>"
                           placeholder="yourname@gmail.com">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Minimum 8 characters">
                    <div class="password-rules">Must be at least 8 characters</div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required
                           placeholder="Re-enter your password">
                </div>

                <button type="submit">Continue to PIN Setup</button>
            </form>

            <div class="links">
                Already registered? <a href="index.php">Login here</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>