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

// 🔹 Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('<p style="color:red">Missing staff user ID.</p>');
}

$staffUserID = $_GET['id'];
$staffData = [];

// 🔹 Step 1: Load staff data
try {
    $doc = firestore_get('Users', $staffUserID, $_SESSION['idToken']);
    if (!isset($doc['error']) && isset($doc['fields'])) {
        foreach ($doc['fields'] as $key => $value) {
            $staffData[$key] = reset($value);
        }
    } else {
        $error = "Failed to load staff data: " . ($doc['error']['message'] ?? 'Unknown error');
    }
} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
}

// Initialize form values
$name = $_POST['name'] ?? ($staffData['name'] ?? '');
$email = $staffData['email'] ?? '';
$staffID = $_POST['staffID'] ?? ($staffData['staffID'] ?? '');
$icNum = $_POST['icNum'] ?? ($staffData['icNum'] ?? '');
$gender = $_POST['gender'] ?? ($staffData['gender'] ?? '');
$contactNo = $_POST['contactNo'] ?? ($staffData['contactNo'] ?? '');
$staffRole = $_POST['staffRole'] ?? ($staffData['staffRole'] ?? '');
$status = $_POST['status'] ?? ($staffData['status'] ?? '');
$hostelID = $_POST['hostelID'] ?? ($staffData['hostelID'] ?? '');

// 🔹 Step 2: Handle Update
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
        $error = "Please fill in all required fields (Staff ID, Name, IC Number, Gender, Staff Role).";
    } else {
        $updateData = [
            'name' => $name,
            'staffID' => $staffID,
            'icNum' => $icNum,
            'gender' => $gender,
            'contactNo' => $contactNo,
            'staffRole' => $staffRole,
            'status' => $status,
            'hostelID' => $hostelID,
        ];

        $res = firestore_set('Users', $staffUserID, $updateData, $_SESSION['idToken']);

        if (isset($res['error'])) {
            $error = "Failed to update staff member: " . ($res['error']['message'] ?? 'Unknown error');
        } else {
            $message = "Maintenance staff member updated successfully!";
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
        <a href="staff_main.php" class="back-icon-link" title="Back to Staff List"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Update Maintenance Staff Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="staffID">Staff ID <span style="color:red">*</span>:</label>
        <input id="staffID" name="staffID" type="text" required value="<?= htmlspecialchars($staffID) ?>">

        <label for="name">Name <span style="color:red">*</span>:</label>
        <input id="name" name="name" type="text" required value="<?= htmlspecialchars($name) ?>">

        <label for="email">Email:</label>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($email) ?>" readonly disabled>

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

        <label for="staffRole">Staff Role <span style="color:red">*</span>:</label>
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

        <button type="submit" class="btn-submit">Update Maintenance Staff</button>
    </form>
</div>

<!-- ✅ Success Popup -->
<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Maintenance Staff Member Updated Successfully</h3>
        <p>All details have been saved.</p>
        <button id="okBtn">Okay</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
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
