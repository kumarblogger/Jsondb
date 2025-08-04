<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $users = json_decode(file_get_contents('db.json'), true) ?: [];

    if ($step == 1) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);

        if (empty($username) || empty($email)) {
            $error = "Both username and email are required";
        } else {
            $user_found = false;
            foreach ($users as $user) {
                if ($user['username'] === $username && $user['email'] === $email) {
                    $user_found = true;
                    $_SESSION['reset_user'] = $user;
                    header("Location: forgot-password.php?step=2");
                    exit();
                }
            }
            if (!$user_found) {
                $error = "No account found with that username and email combination";
            }
        }
    } elseif ($step == 2) {
        $entered_pin = trim($_POST['pin']);
        $user = $_SESSION['reset_user'];

        if (empty($entered_pin)) {
            $error = "Please enter your 4-digit PIN";
        } elseif (!isset($user['pin']) || $user['pin'] !== $entered_pin) {
            $error = "Invalid PIN";
        } else {
            header("Location: forgot-password.php?step=3");
            exit();
        }
    } elseif ($step == 3) {
        $new_pass = $_POST['new_pass'];
        $confirm_pass = $_POST['confirm_pass'];

        if (empty($new_pass) || empty($confirm_pass)) {
            $error = "Both password fields are required";
        } elseif (strlen($new_pass) < 8) {
            $error = "Password must be at least 8 characters";
        } elseif ($new_pass !== $confirm_pass) {
            $error = "Passwords do not match";
        } else {
            $user = $_SESSION['reset_user'];
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

            foreach ($users as &$u) {
                if ($u['username'] === $user['username']) {
                    $u['password'] = $hashed_pass;
                    break;
                }
            }

            if (file_put_contents('db.json', json_encode($users, JSON_PRETTY_PRINT)) !== false) {
                $success = "Password reset successfully!";
                unset($_SESSION['reset_user']);
            } else {
                $error = "Failed to update password. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .forgot-box {
            background-color: #1e1e1e;
            padding: 25px;
            border-radius: 6px;
            width: 320px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #fff;
        }
        .error {
            background: rgba(255, 85, 85, 0.1);
            color: #ff5555;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            text-align: center;
        }
        .success {
            background: rgba(85, 255, 85, 0.1);
            color: #55ff55;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 6px;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            background: #2d2d2d;
            border: 1px solid #444;
            border-radius: 4px;
            color: #fff;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #3a7bd5;
            border: none;
            border-radius: 4px;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background: #3366cc;
        }
        .links {
            margin-top: 20px;
            font-size: 14px;
            text-align: center;
        }
        .links a {
            color: #3a7bd5;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .instructions {
            font-size: 12px;
            text-align: center;
            color: #aaa;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="forgot-box">
        <h2>Forgot Password</h2>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
            <div class="links"><a href="index.php">Return to login</a></div>
        <?php else: ?>
            <?php if ($step == 1): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" required value="<?= htmlspecialchars($username) ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Gmail Address</label>
                        <input type="email" name="email" id="email" required placeholder="yourname@gmail.com" value="<?= htmlspecialchars($email) ?>">
                    </div>
                    <button type="submit">Continue</button>
                </form>
                <div class="links">
                    Remember your password? <a href="index.php">Login here</a>
                </div>
            <?php elseif ($step == 2): ?>
                <div class="instructions">
                    Enter your 4-digit PIN to verify your identity
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label for="pin">Your 4-digit PIN</label>
                        <input type="number" id="pin" name="pin" required placeholder="Enter your PIN" maxlength="4">
                    </div>
                    <button type="submit">Verify</button>
                </form>
            <?php elseif ($step == 3): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="new_pass">New Password</label>
                        <input type="password" name="new_pass" id="new_pass" required placeholder="Minimum 8 characters">
                    </div>
                    <div class="form-group">
                        <label for="confirm_pass">Confirm Password</label>
                        <input type="password" name="confirm_pass" id="confirm_pass" required placeholder="Re-enter new password">
                    </div>
                    <button type="submit">Reset Password</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
