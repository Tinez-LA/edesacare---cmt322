<?php
// Secure session configuration
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access
ini_set('session.use_strict_mode', 1); // Prevent session fixation
ini_set('session.gc_maxlifetime', 1800); // 30 minutes

session_start();

$page_title = "Login";
include_once('../inc/firebase.php'); // Make sure this path is correct

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Please fill in both fields.";
    } else {
        $signin = firebase_signin($email, $password);

        if (isset($signin['idToken'])) {
            $idToken = $signin['idToken'];
            $uid = $signin['localId'];

            // Fetch user details from Firestore
            $doc = firestore_get('Users', $uid, $idToken);
            $data = ['uid' => $uid]; // ✅ Add the UID to the user data array
            
            // ✅ Correctly parse all field types from Firestore
            if (isset($doc['fields'])) {
                foreach ($doc['fields'] as $key => $value) {
                    $data[$key] = reset($value); // Gets the value regardless of type (string, integer, etc.)
                }
            }

            if (empty($data)) {
                $error = "User record not found in Firestore.";
            } else {
                // Save user info in session
                $_SESSION['user'] = $data; // Now contains the UID
                $_SESSION['idToken'] = $idToken;

                // Redirect to dashboard based on role
                $role = strtolower($data['role'] ?? '');
                $dashboardFile = "dashboard.php"; // Default for maintenance_staff
                
                // Set specific dashboard filenames for other roles
                if ($role === 'admin' || $role === 'warden' || $role === 'maintenance_staff'|| $role === 'student') {
                    $dashboardFile = "dashboard.php";
                }

                // Construct the final path
                $dashboardPath = "/user_management/{$role}/{$dashboardFile}";

                // Safety check
                if (!file_exists(__DIR__ . "/.." . $dashboardPath)) {
                    echo "<p style='color:red'>Dashboard file not found at: $dashboardPath</p>";
                    exit;
                }

                header("Location: $dashboardPath");
                exit;
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="../assets/login_style.css">
    <style>
    
    .password-group {
    position: relative;
}



.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 18px;
    color: #666;
    user-select: none;
}

.toggle-password:hover {
    color: #000;
}
</style>
</head>
<body>
    <div class="login-container">
        <div class="login-image-panel">
            <!-- Image is set via CSS background -->
        </div>
        <div class="login-form-panel">
            <h2>Welcome Back!</h2>
            <p>Login to manage your hostel needs.</p>

            <form method="POST" action="">
                <?php if (!empty($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <input type="email" name="email" placeholder="Email Address" required>
                </div>
                <div class="form-group password-group">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePassword()"></i>
                </div>
                <button type="submit" class="btn-login">Login</button>

                <div class="links">
                    <a href="../index.php">← Back to Homepage</a>
                    <a href="forgot_password.php">Forgot password?</a>
                </div>
            </form>
        </div>
    </div>
            <script>
            function togglePassword() {
                const passwordInput = document.getElementById("password");
                const toggleIcon = document.querySelector(".toggle-password");

                if (passwordInput.type === "password") {
                    passwordInput.type = "text";
                    toggleIcon.classList.remove("fa-eye");
                    toggleIcon.classList.add("fa-eye-slash");
                } else {
                    passwordInput.type = "password";
                    toggleIcon.classList.remove("fa-eye-slash");
                    toggleIcon.classList.add("fa-eye");
                }
            }
            </script>

</body>
</html>