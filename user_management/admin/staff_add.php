<?php
$page_title = "Add Maintenance Staff";
include_once('../../inc/auth_check.php');
require_role(role: 'admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';

// Ensure admin is logged in
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin is not authenticated. Please login again.</p>');
}

// Initialize form fields
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$icNum = $_POST['icNum'] ?? '';
$gender = $_POST['gender'] ?? '';
$contactNo = $_POST['contactNo'] ?? '';
$staffID = $_POST['staffID'] ?? '';
$staffRole = $_POST['staffRole'] ?? '';
$status = $_POST['status'] ?? 'Active';
$hostelID = $_POST['hostelID'] ?? '';
$tempPassword = $_POST['tempPassword'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim all input values
    $name = trim($name);
    $email = trim($email);
    $icNum = trim($icNum);
    $gender = trim($gender);
    $contactNo = trim($contactNo);
    $staffID = trim($staffID);
    $staffRole = trim($staffRole);
    $status = trim($status);
    $hostelID = trim($hostelID);
    $tempPassword = trim($tempPassword);

    // Basic validation
    if (empty($name) || empty($email) || empty($icNum) || empty($tempPassword) || empty($gender) || empty($staffID) || empty($staffRole)) {
        $error = "Please fill in all required fields including Staff ID, Staff Role, gender, IC Number, and temporary password.";
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
            $staffData = [
                'userID' => $userID,
                'staffID' => $staffID,
                'name' => $name,
                'email' => $email,
                'icNum' => $icNum,
                'gender' => $gender,
                'role' => 'maintenance_staff',
                'contactNo' => $contactNo,
                'staffRole' => $staffRole,
                'status' => $status,
                'hostelID' => $hostelID,
                'changepasswordcount' => 0
            ];

            $res = firestore_set('Users', $userID, $staffData, $idToken);

            if (isset($res['error'])) {
                $error = "Failed to save staff in Firestore: " . ($res['error']['message'] ?? 'Unknown error');
            } else {
                $message = "Maintenance staff member created successfully! They can login with the temporary password.";
                echo "<script>window.successMessage = true;</script>";

                // Clear form after success
                $name = $email = $icNum = $contactNo = $staffID = $staffRole = $status = $hostelID = $tempPassword = $gender = '';
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
        <a href="staff_main.php" class="back-icon-link" title="Back to Staff List"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Add New Maintenance Staff Member</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="staffID">Staff ID <span style="color:red">*</span>:</label>
        <input id="staffID" name="staffID" type="text" required value="<?= htmlspecialchars($staffID) ?>">

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
        <small style="color: #555; font-size: 0.85rem;">Temporary password for first login only. Maintenance Staff must change it afterwards.</small>

        <label for="staffRole">Maintenance Staff Role <span style="color:red">*</span>:</label>
        <select id="staffRole" name="staffRole" required>
            <option value="">-- Select Role --</option>
            <option value="Electrician" <?= $staffRole === 'Electrician' ? 'selected' : '' ?>>Electrician</option>
            <option value="Plumber" <?= $staffRole === 'Plumber' ? 'selected' : '' ?>>Plumber</option>
            <option value="Cleaner" <?= $staffRole === 'Cleaner' ? 'selected' : '' ?>>Cleaner</option>
            <option value="Technician" <?= $staffRole === 'Technician' ? 'selected' : '' ?>>Technician</option>
        </select>

        <label for="status">Status <span style="color:red">*</span>:</label>
        <select id="status" name="status" required>
            <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>

        <label for="hostelID">Hostel (optional):</label>
        <select id="hostelID" name="hostelID">
            <option value="">-- Select Hostel --</option>
            <optgroup label="Kampus Induk">
                <option value="Desasiswa Aman Damai" <?= $hostelID === 'Desasiswa Aman Damai' ? 'selected' : '' ?>>Desasiswa Aman Damai</option>
                <option value="Desasiswa Bakti Permai" <?= $hostelID === 'Desasiswa Bakti Permai' ? 'selected' : '' ?>>Desasiswa Bakti Permai</option>
                <option value="Desasiswa Cahaya Gemilang" <?= $hostelID === 'Desasiswa Cahaya Gemilang' ? 'selected' : '' ?>>Desasiswa Cahaya Gemilang</option>
                <option value="Desasiswa Fajar Harapan" <?= $hostelID === 'Desasiswa Fajar Harapan' ? 'selected' : '' ?>>Desasiswa Fajar Harapan</option>
                <option value="Desasiswa Indah Kembara" <?= $hostelID === 'Desasiswa Indah Kembara' ? 'selected' : '' ?>>Desasiswa Indah Kembara</option>
                <option value="Desasiswa Restu" <?= $hostelID === 'Desasiswa Restu' ? 'selected' : '' ?>>Desasiswa Restu</option>
                <option value="Desasiswa Rumah Antarabangsa" <?= $hostelID === 'Desasiswa Rumah Antarabangsa' ? 'selected' : '' ?>>Desasiswa Rumah Antarabangsa</option>
                <option value="Desasiswa Saujana" <?= $hostelID === 'Desasiswa Saujana' ? 'selected' : '' ?>>Desasiswa Saujana</option>
                <option value="Desasiswa Tekun" <?= $hostelID === 'Desasiswa Tekun' ? 'selected' : '' ?>>Desasiswa Tekun</option>
            </optgroup>
            <optgroup label="Kampus Kejuruteraan">
                <option value="Desasiswa Jaya" <?= $hostelID === 'Desasiswa Jaya' ? 'selected' : '' ?>>Desasiswa Jaya</option>
                <option value="Desasiswa Lembaran" <?= $hostelID === 'Desasiswa Lembaran' ? 'selected' : '' ?>>Desasiswa Lembaran</option>
                <option value="Desasiswa Utama" <?= $hostelID === 'Desasiswa Utama' ? 'selected' : '' ?>>Desasiswa Utama</option>
            </optgroup>
            <optgroup label="Kampus Kesihatan">
                <option value="Desasiswa Murni" <?= $hostelID === 'Desasiswa Murni' ? 'selected' : '' ?>>Desasiswa Murni</option>
                <option value="Desasiswa Nurani" <?= $hostelID === 'Desasiswa Nurani' ? 'selected' : '' ?>>Desasiswa Nurani</option>
            </optgroup>
        </select><br><br>

        <button type="submit" class="btn-submit">Add Maintenance Staff</button>
    </form>
</div>

<!-- ⭐ NEW — Popup HTML -->
<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Maintenance Staff Member Created Successfully</h3>
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
            window.location.href = "staff_main.php";
        });
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
