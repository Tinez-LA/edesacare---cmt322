<?php
$page_title = "Add Student";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';

// 🔒 Ensure admin authenticated
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin not authenticated. Please login again.</p>');
}

/* =========================
   FETCH HOSTELS
   ========================= */
$hostels = [];
try {
    $hostels_collection = firestore_get_collection('Hostels', $_SESSION['idToken']);
    if (!isset($hostels_collection['error'])) {
        $hostels = $hostels_collection;
    }
} catch (Exception $e) {
    $error = "Error loading hostels: " . $e->getMessage();
}

/* =========================
   FORM VALUES
   ========================= */
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$icNum = $_POST['icNum'] ?? '';
$gender = $_POST['gender'] ?? '';
$contactNo = $_POST['contactNo'] ?? '';
$studentID = $_POST['studentID'] ?? '';
$hostelID = $_POST['hostelID'] ?? '';
$tempPassword = $_POST['tempPassword'] ?? '';

/* =========================
   FORM SUBMIT
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($name);
    $email = trim($email);
    $icNum = trim($icNum);
    $gender = trim($gender);
    $contactNo = trim($contactNo);
    $studentID = trim($studentID);
    $hostelID = trim($hostelID);
    $tempPassword = trim($tempPassword);

    // Validation
    if (
        empty($name) ||
        empty($email) ||
        empty($icNum) ||
        empty($gender) ||
        empty($studentID) ||
        empty($tempPassword)
    ) {
        $error = "Please fill in all required fields.";
    }
    else {

        /* =========================
           🔒 FORCE IC- / TEL-
           ========================= */

        if (!str_starts_with($icNum, 'IC-')) {
            $icNum = 'IC-' . $icNum;
        }

        if (!empty($contactNo) && !str_starts_with($contactNo, 'TEL-')) {
            $contactNo = 'TEL-' . $contactNo;
        }

        /* =========================
           FIREBASE AUTH
           ========================= */
        $signup = firebase_signup($email, $tempPassword);
        $userID = '';
        $idToken = '';

        if (isset($signup['error'])) {
            if ($signup['error']['message'] === 'EMAIL_EXISTS') {
                $signin = firebase_signin($email, $tempPassword);
                if (isset($signin['error'])) {
                    $error = "User already exists but sign-in failed.";
                } else {
                    $userID = $signin['localId'];
                    $idToken = $signin['idToken'];
                }
            } else {
                $error = "Firebase error: " . $signup['error']['message'];
            }
        } else {
            $userID = $signup['localId'];
            $idToken = $signup['idToken'];
        }

        /* =========================
           FIRESTORE SAVE
           ========================= */
        if (!empty($idToken)) {

            $studentData = [
                'userID'   => $userID,
                'studentID'=> $studentID,
                'name'     => $name,
                'email'    => $email,

                // 🔒 ALWAYS STRING
                'icNum'     => $icNum,
                'contactNo'=> $contactNo,

                'gender'   => $gender,
                'role'     => 'student',
                'hostelID' => $hostelID,
                'roomID'   => '',
                'changepasswordcount' => 0
            ];

            $save = firestore_set('Users', $userID, $studentData, $idToken);

            if (isset($save['error'])) {
                $error = "Failed to save student.";
            } else {
                echo "<script>window.successMessage = true;</script>";
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
        <a href="student_main.php"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Add New Student</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Student ID *</label>
        <input type="text" name="studentID" required value="<?= htmlspecialchars($studentID) ?>">

        <label>Name *</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($name) ?>">

        <label>Email *</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>">

        <label>IC Number *</label>
        <input type="text" name="icNum" required value="<?= htmlspecialchars($icNum) ?>">

        <label>Gender *</label>
        <select name="gender" required>
            <option value="">-- Select --</option>
            <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>

        <label>Contact Number</label>
        <input type="text" name="contactNo" value="<?= htmlspecialchars($contactNo) ?>">

        <label>Temporary Password *</label>
        <div class="password-wrapper">
            <input type="password" name="tempPassword" required value="<?= htmlspecialchars($tempPassword) ?>">
            <button type="button" class="toggle-password"><i class="fa-solid fa-eye"></i></button>
        </div>

        <label>Hostel</label>
        <select name="hostelID">
            <option value="">-- Select Hostel --</option>
            <?php foreach ($hostels as $hostel): ?>
                <option value="<?= $hostel['hostelID'] ?>" <?= $hostelID === $hostel['hostelID'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($hostel['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-submit">Add Student</button>
    </form>
</div>

<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Student Created Successfully</h3>
        <button id="okBtn">OK</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const toggle = document.querySelector(".toggle-password");
    const input = document.querySelector("input[name='tempPassword']");
    const icon = toggle.querySelector("i");

    toggle.onclick = () => {
        input.type = input.type === "password" ? "text" : "password";
        icon.classList.toggle("fa-eye");
        icon.classList.toggle("fa-eye-slash");
    };

    if (window.successMessage) {
        document.getElementById("successPopup").style.display = "flex";
        document.getElementById("okBtn").onclick = () => {
            window.location.href = "student_main.php";
        };
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
