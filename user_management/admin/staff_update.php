<?php
$page_title = "Update Maintenance Staff";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';

// 🔒 Ensure admin session valid
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin not authenticated. Please login again.</p>');
}

// 🔹 Validate staff ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('<p style="color:red">Missing staff user ID.</p>');
}

$staffUserID = $_GET['id'];
$staffData = [];

/* =========================
   LOAD STAFF DATA
   ========================= */
try {
    $doc = firestore_get('Users', $staffUserID, $_SESSION['idToken']);
    if (!isset($doc['error']) && isset($doc['fields'])) {
        foreach ($doc['fields'] as $key => $value) {
            $staffData[$key] = reset($value);
        }
    } else {
        $error = "Failed to load staff data.";
    }
} catch (Exception $e) {
    $error = "Error loading staff data: " . $e->getMessage();
}

/* =========================
   FORM VALUES
   ========================= */
$name = $_POST['name'] ?? ($staffData['name'] ?? '');
$email = $staffData['email'] ?? '';
$staffID = $_POST['staffID'] ?? ($staffData['staffID'] ?? '');
$icNum = $_POST['icNum'] ?? ($staffData['icNum'] ?? '');
$gender = $_POST['gender'] ?? ($staffData['gender'] ?? '');
$contactNo = $_POST['contactNo'] ?? ($staffData['contactNo'] ?? '');
$staffRole = $_POST['staffRole'] ?? ($staffData['staffRole'] ?? '');
$status = $_POST['status'] ?? ($staffData['status'] ?? '');
$hostelID = $_POST['hostelID'] ?? ($staffData['hostelID'] ?? '');

/* =========================
   HANDLE UPDATE
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($name);
    $staffID = trim($staffID);
    $icNum = trim($icNum);
    $gender = trim($gender);
    $contactNo = trim($contactNo);
    $staffRole = trim($staffRole);
    $status = trim($status);
    $hostelID = trim($hostelID);

    if (empty($name) || empty($staffID) || empty($icNum) || empty($gender) || empty($staffRole)) {
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
           UPDATE FIRESTORE
           ========================= */
        $updateData = [
            'name'      => $name,
            'staffID'   => $staffID,
            'icNum'     => $icNum,        // 🔒 Always prefixed
            'gender'    => $gender,
            'contactNo' => $contactNo,    // 🔒 Always prefixed
            'staffRole' => $staffRole,
            'status'    => $status,
            'hostelID'  => $hostelID
        ];

        $res = firestore_set('Users', $staffUserID, $updateData, $_SESSION['idToken']);

        if (isset($res['error'])) {
            $error = "Failed to update maintenance staff member.";
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
        <a href="staff_main.php" class="back-icon-link">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h2>Update Maintenance Staff Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">

        <label>Staff ID *</label>
        <input type="text" name="staffID" required value="<?= htmlspecialchars($staffID) ?>">

        <label>Name *</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($name) ?>">

        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($email) ?>" readonly>

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
                <option value="<?= $h ?>" <?= $hostelID === $h ? 'selected' : '' ?>>
                    <?= $h ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-submit">Update Maintenance Staff</button>
    </form>
</div>

<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Maintenance Staff Updated Successfully</h3>
        <button id="okBtn">OK</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (window.successMessage) {
        document.getElementById("successPopup").style.display = "flex";
        document.getElementById("okBtn").onclick = function () {
            window.location.href = "staff_main.php";
        };
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
