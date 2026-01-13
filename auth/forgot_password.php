<?php
ini_set('session.cookie_path', '/');
session_start();

$page_title = "Forgot Password";
include_once('../inc/firebase.php');

$email = '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = "Please enter your email.";
    } else {

        $url = "https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=" . FIREBASE_API_KEY;

        $response = firebase_post($url, [
            "requestType" => "PASSWORD_RESET",
            "email" => $email
        ]);

        // 🔴 NETWORK / CURL FAILURE
        if ($response === false || $response === null) {
            $error = "❌ Network error. Unable to reach Firebase. Please try again later.";
        }
        // 🔴 FIREBASE ERROR
        elseif (isset($response['error'])) {
            $error = "❌ " . $response['error']['message'];
        }
        // ✅ SUCCESS
        else {
            $message = "✅ Password reset link sent to $email";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/login_style.css">
</head>
<body>
<div class="login-container">
    <div class="login-image-panel"></div>
    <div class="login-form-panel">
        <h2>Forgot Password?</h2>
        <p>No worries! Enter your email and we'll send you a reset link.</p>

        <form method="POST">
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div style="color:#155724;background:#d4edda;border:1px solid #c3e6cb;padding:10px;border-radius:8px;margin-bottom:20px;text-align:center;">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <input type="email" name="email" placeholder="Enter your Email Address"
                       required value="<?= htmlspecialchars($email) ?>">
            </div>

            <button type="submit" class="btn-login">Send Reset Link</button>

            <div class="links">
                <a href="../index.php">← Back to Homepage</a>
                <a href="login.php">← Back to Login</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
