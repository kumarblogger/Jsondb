<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['username']) && isset($_SESSION['apikey'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$login_identifier = "";

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_identifier = trim($_POST['login_identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($login_identifier) || empty($password)) {
        $error = "Login identifier and password are required";
    } else {
        $users = json_decode(file_get_contents('db.json'), true);
        if ($users === null) {
            $error = "System error. Please try again later.";
        } else {
            $user_found = false;
            foreach ($users as $user) {
                // Check if login matches either username or email
                if (($user['username'] === $login_identifier || $user['email'] === $login_identifier) && 
                    password_verify($password, $user['password'])) {
                    
                    // Additional check if logging in with email - must be Gmail
                    if (strpos($login_identifier, '@') !== false && 
                        !preg_match('/@gmail\.com$|@googlemail\.com$/i', $login_identifier)) {
                        $error = "Only Gmail accounts are allowed";
                        break;
                    }
                    
                    // Set session variables
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['apikey'] = $user['apikey'];
                    
                    // Set session cookie parameters based on "Remember me" selection
                    if ($remember) {
                        // If "Remember me" is checked, set session to last 30 days
                        $lifetime = 60 * 60 * 24 * 30; // 30 days
                        session_set_cookie_params($lifetime);
                    } else {
                        // If not checked, session will expire when browser closes
                        session_set_cookie_params(0);
                    }
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    header("Location: dashboard.php");
                    exit();
                }
            }
            if (!$error) {
                $error = "Invalid login identifier or password";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
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
        
        .login-box {
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
        input[type="password"] {
            width: 100%;
            padding: 10px;
            background-color: #2d2d2d;
            border: 1px solid #444;
            border-radius: 3px;
            color: #ffffff;
            box-sizing: border-box;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
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
        
        .remember-me {
            margin: 15px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .remember-me input {
            margin-right: 8px;
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
        
        .login-note {
            font-size: 12px;
            color: #aaa;
            margin-top: -10px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login</h2>
        <div class="login-note">Use your username or Gmail account</div>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="login_identifier">Username or Gmail</label>
                <input type="text" id="login_identifier" name="login_identifier" required 
                       value="<?= htmlspecialchars($login_identifier) ?>" 
                       placeholder="username or yourname@gmail.com">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me (stay logged in for 30 days)</label>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div class="links">
            <a href="signup.php">Create account</a> | 
            <a href="forgot-password.php">Forgot password?</a>
        </div>
    </div>
</body>
</html>