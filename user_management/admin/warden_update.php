<?php
$page_title = "Update Warden";
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
    die('<p style="color:red">Missing warden ID.</p>');
}

$wardenID = $_GET['id'];
$wardenData = [];
$assigned_hostel_ids = []; // To track which hostels are taken by OTHER wardens
$hostels = [];

// 🟣 Step 1: Fetch data for form dropdowns
try {
    // First, find which hostels are assigned to other wardens
    $users_collection = firestore_get_collection('Users', $_SESSION['idToken']);
    if (!isset($users_collection['error'])) {
        foreach ($users_collection as $user) {
            // Check if a user is a warden, has a hostel, AND is not the warden we are currently editing
            if (($user['role'] ?? '') === 'warden' && !empty($user['hostelID']) && $user['userID'] !== $wardenID) {
                $assigned_hostel_ids[] = $user['hostelID'];
            }
        }
    }

    // Now, fetch all hostels
    $hostels_collection = firestore_get_collection('Hostels', $_SESSION['idToken']);
    if (!isset($hostels_collection['error'])) {
        $hostels = $hostels_collection;
    } else {
        $error = "Warning: Could not load hostels list.";
    }
} catch (Exception $e) {
    $error = "Error loading page data: " . $e->getMessage();
}
// 🔹 Step 1: Load warden data
try {
    $doc = firestore_get('Users', $wardenID, $_SESSION['idToken']);
    if (!isset($doc['error']) && isset($doc['fields'])) {
        foreach ($doc['fields'] as $key => $value) {
            $wardenData[$key] = reset($value);
        }
    } else {
        $error .= " Failed to load warden data: " . ($doc['error']['message'] ?? 'Unknown error');
    }
} catch (Exception $e) {
    $error .= " Error loading data: " . $e->getMessage();
}

// Initialize form values
$name = $_POST['name'] ?? ($wardenData['name'] ?? '');
$email = $wardenData['email'] ?? '';
$icNumber = $_POST['icNumber'] ?? ($wardenData['icNumber'] ?? '');
$gender = $_POST['gender'] ?? ($wardenData['gender'] ?? '');
$contactNo = $_POST['contactNo'] ?? ($wardenData['contactNo'] ?? '');
$hostelID = $_POST['hostelID'] ?? ($wardenData['hostelID'] ?? '');

// 🔹 Step 2: Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($name);
    $icNumber = trim($icNumber);
    $gender = trim($gender);
    $contactNo = trim($contactNo);
    $hostelID = trim($hostelID);

    if (empty($name) || empty($icNumber) || empty($gender)) {
        $error = "Please fill in all required fields (Name, IC Number, Gender).";
    } else {
        $updateData = [
            'name' => $name,
            'icNumber' => $icNumber,
            'gender' => $gender,
            'contactNo' => $contactNo,
            'hostelID' => $hostelID,
        ];

        $res = firestore_set('Users', $wardenID, $updateData, $_SESSION['idToken']);

        if (isset($res['error'])) {
            $error = "Failed to update warden: " . $res['error']['message'];
        } else {
            $message = "Warden updated successfully!";
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
        <a href="warden_main.php" class="back-icon-link" title="Back to Warden List"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Update Warden Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="name">Name <span style="color:red">*</span>:</label>
        <input id="name" name="name" type="text" required value="<?= htmlspecialchars($name) ?>">

        <label for="email">Email:</label>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($email) ?>" readonly>

        <label for="icNumber">IC Number <span style="color:red">*</span>:</label>
        <input id="icNumber" name="icNumber" type="text" required value="<?= htmlspecialchars($icNumber) ?>">

        <label for="gender">Gender <span style="color:red">*</span>:</label>
        <select id="gender" name="gender" required>
            <option value="">-- Select Gender --</option>
            <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>

        <label for="contactNo">Contact No:</label>
        <input id="contactNo" name="contactNo" type="text" value="<?= htmlspecialchars($contactNo) ?>">

        <label for="hostelID">Hostel (optional):</label>
        <select id="hostelID" name="hostelID">
            <option value="">-- Not Assigned --</option>
            <?php foreach ($hostels as $hostel): ?>
                <?php
                    // A hostel is disabled if it's in the assigned list AND it's not the current warden's hostel
                    $is_assigned_to_other = in_array($hostel['hostelID'], $assigned_hostel_ids);
                    $is_current_warden_hostel = ($hostel['hostelID'] === ($wardenData['hostelID'] ?? ''));
                    $is_disabled = $is_assigned_to_other && !$is_current_warden_hostel;
                    $display_name = htmlspecialchars($hostel['name']);
                    if ($is_disabled) $display_name .= " (Assigned)";
                ?>
                <option value="<?= htmlspecialchars($hostel['hostelID']) ?>" <?= ($hostelID === $hostel['hostelID']) ? 'selected' : '' ?> <?= $is_disabled ? 'disabled' : '' ?>>
                    <?= $display_name ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <button type="submit" class="btn-submit">Update Warden</button>
    </form>
</div>

<!-- ✅ Success Popup -->
<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Warden Updated Successfully</h3>
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
            window.location.href = "warden_main.php";
        });
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
