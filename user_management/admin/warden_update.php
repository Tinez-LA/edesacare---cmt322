<?php
$page_title = "Update Warden";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';

// 🔒 Ensure admin session
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin not authenticated. Please login again.</p>');
}

// 🔹 Validate warden ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('<p style="color:red">Missing warden ID.</p>');
}

$wardenID = $_GET['id'];
$wardenData = [];
$assigned_hostel_ids = [];
$hostels = [];

/* =========================
   FETCH HOSTELS & ASSIGNMENTS
   ========================= */
try {
    $users = firestore_get_collection('Users', $_SESSION['idToken']);
    if (!isset($users['error'])) {
        foreach ($users as $user) {
            if (
                ($user['role'] ?? '') === 'warden' &&
                !empty($user['hostelID']) &&
                $user['userID'] !== $wardenID
            ) {
                $assigned_hostel_ids[] = $user['hostelID'];
            }
        }
    }

    $hostels_data = firestore_get_collection('Hostels', $_SESSION['idToken']);
    if (!isset($hostels_data['error'])) {
        $hostels = $hostels_data;
    }
} catch (Exception $e) {
    $error = "Error loading hostels: " . $e->getMessage();
}

/* =========================
   LOAD WARDEN DATA
   ========================= */
try {
    $doc = firestore_get('Users', $wardenID, $_SESSION['idToken']);
    if (!isset($doc['error']) && isset($doc['fields'])) {
        foreach ($doc['fields'] as $key => $value) {
            $wardenData[$key] = reset($value);
        }
    } else {
        $error = "Failed to load warden data.";
    }
} catch (Exception $e) {
    $error = "Error loading warden: " . $e->getMessage();
}

/* =========================
   FORM VALUES
   ========================= */
$name = $_POST['name'] ?? ($wardenData['name'] ?? '');
$email = $wardenData['email'] ?? '';
$icNumber = $_POST['icNumber'] ?? ($wardenData['icNumber'] ?? '');
$gender = $_POST['gender'] ?? ($wardenData['gender'] ?? '');
$contactNo = $_POST['contactNo'] ?? ($wardenData['contactNo'] ?? '');
$hostelID = $_POST['hostelID'] ?? ($wardenData['hostelID'] ?? '');

/* =========================
   HANDLE UPDATE
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($name);
    $icNumber = trim($icNumber);
    $gender = trim($gender);
    $contactNo = trim($contactNo);
    $hostelID = trim($hostelID);

    if (empty($name) || empty($icNumber) || empty($gender)) {
        $error = "Please fill in all required fields.";
    } else {

        /* =========================
           🔒 FORCE IC- / TEL- PREFIX
           ========================= */

        // IC Number
        if (!str_starts_with($icNumber, 'IC-')) {
            $icNumber = 'IC-' . $icNumber;
        }

        // Contact Number (only if provided)
        if (!empty($contactNo) && !str_starts_with($contactNo, 'TEL-')) {
            $contactNo = 'TEL-' . $contactNo;
        }

        /* =========================
           UPDATE DATA
           ========================= */
        $updateData = [
            'name'      => $name,
            'icNumber'  => $icNumber,     // 🔒 Always prefixed
            'gender'    => $gender,
            'contactNo'=> $contactNo,     // 🔒 Always prefixed
            'hostelID'  => $hostelID
        ];

        $res = firestore_set('Users', $wardenID, $updateData, $_SESSION['idToken']);

        if (isset($res['error'])) {
            $error = "Failed to update warden.";
        } else {
            echo "<script>window.successMessage = true;</script>";
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
        <h2>Update Warden Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Name *</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($name) ?>">

        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($email) ?>" readonly>

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

        <label>Hostel</label>
        <select name="hostelID">
            <option value="">-- Not Assigned --</option>
            <?php foreach ($hostels as $hostel): ?>
                <?php
                $assigned = in_array($hostel['hostelID'], $assigned_hostel_ids);
                $current = ($hostel['hostelID'] === ($wardenData['hostelID'] ?? ''));
                ?>
                <option value="<?= $hostel['hostelID'] ?>"
                    <?= ($assigned && !$current) ? 'disabled' : '' ?>
                    <?= $hostelID === $hostel['hostelID'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($hostel['name']) ?> <?= ($assigned && !$current) ? '(Assigned)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-submit">Update Warden</button>
    </form>
</div>

<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Warden Updated Successfully</h3>
        <button id="okBtn">OK</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (window.successMessage) {
        document.getElementById("successPopup").style.display = "flex";
        document.getElementById("okBtn").onclick = function () {
            window.location.href = "warden_main.php";
        };
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
