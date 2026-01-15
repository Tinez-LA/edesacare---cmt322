<?php
$page_title = "Add Warden";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';

// Ensure admin authenticated
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin not authenticated. Please login again.</p>');
}

/* =========================
   FETCH HOSTELS & ASSIGNMENTS
   ========================= */
$hostels = [];
$assigned_hostel_ids = [];

try {
    $users = firestore_get_collection('Users', $_SESSION['idToken']);
    if (!isset($users['error'])) {
        foreach ($users as $user) {
            if (($user['role'] ?? '') === 'warden' && !empty($user['hostelID'])) {
                $assigned_hostel_ids[] = $user['hostelID'];
            }
        }
    }

    $hostels_data = firestore_get_collection('Hostels', $_SESSION['idToken']);
    if (!isset($hostels_data['error'])) {
        $hostels = $hostels_data;
    }
} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
}

/* =========================
   FORM VALUES
   ========================= */
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$icNumber = $_POST['icNumber'] ?? '';
$gender = $_POST['gender'] ?? '';
$contactNo = $_POST['contactNo'] ?? '';
$hostelID = $_POST['hostelID'] ?? '';
$tempPassword = $_POST['tempPassword'] ?? '';

/* =========================
   FORM SUBMIT
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($name);
    $email = trim($email);
    $icNumber = trim($icNumber);
    $gender = trim($gender);
    $contactNo = trim($contactNo);
    $hostelID = trim($hostelID);
    $tempPassword = trim($tempPassword);

    // Validation
    if (
        empty($name) ||
        empty($email) ||
        empty($icNumber) ||
        empty($gender) ||
        empty($tempPassword)
    ) {
        $error = "Please fill in all required fields.";
    }
    elseif (!preg_match('/^[0-9]+$/', $icNumber)) {
        $error = "IC Number must contain digits only.";
    }
    elseif (!empty($contactNo) && !preg_match('/^[0-9]+$/', $contactNo)) {
        $error = "Contact number must contain digits only.";
    }
    else {

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
                    $error = "User already exists but cannot sign in.";
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

            $wardenID = uniqid('warden_');

            $userData = [
                'userID'   => $userID,
                'wardenID'=> $wardenID,
                'name'     => $name,
                'email'    => $email,

                // 🔒 FORCE STRING (PREVENT NUMBER STORAGE)
                'icNumber'  => 'IC-' . $icNumber,
                'contactNo'=> 'TEL-' . $contactNo,

                'gender'   => $gender,
                'password' => $tempPassword,
                'role'     => 'warden',
                'changepasswordcount' => 0
            ];

            if (!empty($hostelID)) {
                $userData['hostelID'] = $hostelID;
            }

            $save = firestore_set('Users', $userID, $userData, $idToken);

            if (isset($save['error'])) {
                $error = "Failed to save warden.";
            } else {
                echo "<script>window.successMessage = true;</script>";
                $name = $email = $icNumber = $contactNo = $hostelID = $tempPassword = $gender = '';
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
        <a href="warden_main.php"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Add New Warden</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Name *</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($name) ?>">

        <label>Email *</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>">

        <label>IC Number *</label>
        <input type="text" name="icNumber" required value="<?= htmlspecialchars($icNumber) ?>">

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
            <option value="">-- Not Assigned --</option>
            <?php foreach ($hostels as $hostel): ?>
                <?php $assigned = in_array($hostel['hostelID'], $assigned_hostel_ids); ?>
                <option value="<?= $hostel['hostelID'] ?>"
                    <?= $assigned ? 'disabled' : '' ?>
                    <?= $hostelID === $hostel['hostelID'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($hostel['name']) ?> <?= $assigned ? '(Assigned)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-submit">Add Warden</button>
    </form>
</div>

<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Warden Created Successfully</h3>
        <button id="okBtn">OK</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const toggle = document.querySelector(".toggle-password");
    const input = document.querySelector("input[name='tempPassword']");
    const icon = toggle.querySelector("i");

    toggle.addEventListener("click", function () {
        input.type = input.type === "password" ? "text" : "password";
        icon.classList.toggle("fa-eye");
        icon.classList.toggle("fa-eye-slash");
    });

    if (window.successMessage) {
        document.getElementById("successPopup").style.display = "flex";
        document.getElementById("okBtn").onclick = () => {
            window.location.href = "warden_main.php";
        };
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
