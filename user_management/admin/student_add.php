<?php
$page_title = "Add Student";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';

// Ensure admin is logged in
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin not authenticated. Please login again.</p>');
}

$hostels = [];
// 🟣 Fetch all hostels to populate the dropdown
try {
    $hostels_collection = firestore_get_collection('Hostels', $_SESSION['idToken']);
    if (!isset($hostels_collection['error'])) {
        $hostels = $hostels_collection;
    } else {
        $error = "Warning: Could not load hostels list.";
    }
} catch (Exception $e) {
    $error = "Error loading hostels: " . $e->getMessage();
}

// Initialize form fields
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$icNum = $_POST['icNum'] ?? '';
$gender = $_POST['gender'] ?? '';
$contactNo = $_POST['contactNo'] ?? '';
$studentID = $_POST['studentID'] ?? '';
$hostelID = $_POST['hostelID'] ?? '';
$tempPassword = $_POST['tempPassword'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim all input values
    $name = trim($name);
    $email = trim($email);
    $icNum = trim($icNum);
    $gender = trim($gender);
    $contactNo = trim($contactNo);
    $studentID = trim($studentID);
    $hostelID = trim($hostelID);
    $tempPassword = trim($tempPassword);

    // Basic validation
    if (empty($name) || empty($email) || empty($icNum) || empty($tempPassword) || empty($gender) || empty($studentID)) {
        $error = "Please fill in all required fields including Student ID, gender, IC Number, and temporary password.";
    } else {
        // 🔹 Create user in Firebase Authentication
        $signupRes = firebase_signup($email, $tempPassword);
        $userID = '';
        $idToken = '';

        if (isset($signupRes['error'])) {
            if ($signupRes['error']['message'] === 'EMAIL_EXISTS') {
                // If user already exists, attempt to sign in
                $signinRes = firebase_signin($email, $tempPassword);
                if (isset($signinRes['error'])) {
                    $error = "A user with this email already exists and automatic sign-in failed. Please check the password or use a different email.";
                } else {
                    $userID = $signinRes['localId'];
                    $idToken = $signinRes['idToken'];
                }
            } else {
                $error = "Failed to create user in Firebase Auth: " . $signupRes['error']['message'];
            }
        } else {
            // Successfully created user
            $userID = $signupRes['localId'];
            $idToken = $signupRes['idToken'];
        }

        // 🔹 Save data to Firestore
        if (!empty($idToken)) {
            $studentData = [
                'userID' => $userID,
                'studentID' => $studentID,
                'name' => $name,
                'email' => $email,
                'icNum' => $icNum,
                'gender' => $gender,
                'role' => 'student',
                'contactNo' => $contactNo,
                'hostelID' => $hostelID,
                'roomID' => '', // Initially empty
                'changepasswordcount' => 0
            ];

            // Use the student's unique userID as the document ID in Firestore
            $res = firestore_set('Users', $userID, $studentData, $idToken);

            if (isset($res['error'])) {
                $error = "Failed to save student in Firestore: " . ($res['error']['message'] ?? 'Unknown error');
            } else {
                $message = "Student created successfully! They can login with the temporary password.";
                echo "<script>window.successMessage = true;</script>";

                // Clear form after success
                $name = $email = $icNum = $contactNo = $studentID = $hostelID = $tempPassword = $gender = '';
            }
        }
    }
}
?>


<link rel="stylesheet" href="../../assets/header_style.css"> 
<link rel="stylesheet" href="../../assets/form_style.css"> 
<link rel="stylesheet" href="../../assets/pop_style.css"> 

<div class="form-wrapper">
    <div class="form-header">
        <a href="student_main.php" class="back-icon-link" title="Back to Student List"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Add New Student</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="studentID">Student ID <span style="color:red">*</span>:</label>
        <input id="studentID" name="studentID" type="text" required value="<?= htmlspecialchars($studentID) ?>">

        <label for="name">Name <span style="color:red">*</span>:</label>
        <input id="name" name="name" type="text" required value="<?= htmlspecialchars($name) ?>">

        <label for="email">Email <span style="color:red">*</span>:</label>
        <input id="email" name="email" type="email" required value="<?= htmlspecialchars($email) ?>">

        <label for="icNum">IC Number <span style="color:red">*</span>:</label>
        <input id="icNum" name="icNum" type="text" required value="<?= htmlspecialchars($icNum) ?>">

        <label for="gender">Gender <span style="color:red">*</span>:</label>
        <select id="gender" name="gender" required>
            <option value="">-- Select Gender --</option>
            <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>

        <label for="contactNo">Contact No:</label>
        <input id="contactNo" name="contactNo" type="text" value="<?= htmlspecialchars($contactNo) ?>">

        <label for="tempPassword">Temporary Password <span style="color:red">*</span>:</label>
        <div class="password-wrapper">
            <input id="tempPassword" name="tempPassword" type="password" required value="<?= htmlspecialchars($tempPassword) ?>">
            <button type="button" class="toggle-password"><i class="fa-solid fa-eye"></i></button>
        </div>
        <small style="color: #555; font-size: 0.85rem;">Temporary password for first login only. Student must change it afterwards.</small>

        <label for="hostelID">Hostel (optional):</label>
        <select id="hostelID" name="hostelID">
            <option value="">-- Select Hostel --</option>
            <?php foreach ($hostels as $hostel): ?>
                <option value="<?= htmlspecialchars($hostel['hostelID']) ?>" <?= ($hostelID === $hostel['hostelID']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($hostel['name']) ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <button type="submit" class="btn-submit">Add Student</button>
    </form>
</div>

<!-- ⭐ NEW — Popup HTML -->
<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Student Created Successfully</h3>
        <p>They can now login using the temporary password.</p>
        <button id="okBtn">Okay</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Toggle password visibility
    const toggleBtn = document.querySelector(".toggle-password");
    const passwordInput = document.getElementById("tempPassword");
    const icon = toggleBtn.querySelector("i");

    toggleBtn.addEventListener("click", function() {
        const type = passwordInput.type === "password" ? "text" : "password";
        passwordInput.type = type;
        icon.classList.toggle("fa-eye");
        icon.classList.toggle("fa-eye-slash");
    });

    // ⭐ NEW — Show popup if success
    if (window.successMessage) {
        const popup = document.getElementById("successPopup");
        popup.style.display = "flex";
        document.getElementById("okBtn").addEventListener("click", function() {
            popup.style.display = "none";
            window.location.href = "student_main.php";
        });
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
