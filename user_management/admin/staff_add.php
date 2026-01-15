<?php
$page_title = "Add Maintenance Staff";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';

// 🔒 Ensure admin authenticated
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin is not authenticated. Please login again.</p>');
}

/* =========================
   FORM VALUES
   ========================= */
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

/* =========================
   FORM SUBMIT
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

    if (
        empty($name) ||
        empty($email) ||
        empty($icNum) ||
        empty($gender) ||
        empty($staffID) ||
        empty($staffRole) ||
        empty($tempPassword)
    ) {
        $error = "Please fill in all required fields.";
    } else {

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

            $staffData = [
                'userID'   => $userID,
                'staffID'  => $staffID,
                'name'     => $name,
                'email'    => $email,

                // 🔒 ALWAYS STRING
                'icNum'     => $icNum,
                'contactNo'=> $contactNo,

                'gender'   => $gender,
                'role'     => 'maintenance_staff',
                'staffRole'=> $staffRole,
                'status'   => $status,
                'hostelID' => $hostelID,
                'changepasswordcount' => 0
            ];

            $save = firestore_set('Users', $userID, $staffData, $idToken);

            if (isset($save['error'])) {
                $error = "Failed to save maintenance staff.";
            } else {
                echo "<script>window.successMessage = true;</script>";
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
        <a href="staff_main.php"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Add New Maintenance Staff Member</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">

        <label>Staff ID *</label>
        <input type="text" name="staffID" required value="<?= htmlspecialchars($staffID) ?>">

        <label>Name *</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($name) ?>">

        <label>Email *</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>">

        <label>IC Number *</label>
        <input type="text" name="icNum" required value="<?= htmlspecialchars($icNum) ?>">

        <label>Gender *</label>
        <select name="gender" required>
            <option value="">-- Select Gender --</option>
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

        <label>Staff Role *</label>
        <select name="staffRole" required>
            <option value="">-- Select Role --</option>
            <option value="Electrician" <?= $staffRole === 'Electrician' ? 'selected' : '' ?>>Electrician</option>
            <option value="Plumber" <?= $staffRole === 'Plumber' ? 'selected' : '' ?>>Plumber</option>
            <option value="Cleaner" <?= $staffRole === 'Cleaner' ? 'selected' : '' ?>>Cleaner</option>
            <option value="Technician" <?= $staffRole === 'Technician' ? 'selected' : '' ?>>Technician</option>
        </select>

        <label>Status *</label>
        <select name="status" required>
            <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>

        <label>Hostel</label>
        <select name="hostelID">
            <option value="">-- Select Hostel --</option>
            <?php
            $hostels = [
                "Desasiswa Aman Damai","Desasiswa Bakti Permai","Desasiswa Cahaya Gemilang",
                "Desasiswa Fajar Harapan","Desasiswa Indah Kembara","Desasiswa Restu",
                "Desasiswa Rumah Antarabangsa","Desasiswa Saujana","Desasiswa Tekun",
                "Desasiswa Jaya","Desasiswa Lembaran","Desasiswa Utama",
                "Desasiswa Murni","Desasiswa Nurani"
            ];
            foreach ($hostels as $h):
            ?>
                <option value="<?= $h ?>" <?= $hostelID === $h ? 'selected' : '' ?>><?= $h ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-submit">Add Maintenance Staff</button>
    </form>
</div>

<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Maintenance Staff Member Created Successfully</h3>
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
            window.location.href = "staff_main.php";
        };
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
