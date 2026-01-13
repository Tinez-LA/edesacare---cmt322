<?php
$page_title = "Update Hostel";
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

// 🔹 Validate Hostel ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('<p style="color:red">Missing hostel ID.</p>');
}

$hostelID_from_url = $_GET['id'];
$hostelData = [];
$wardens = [];

// 🟣 Step 1: Fetch all wardens to populate the dropdown
try {
    $users_collection = firestore_get_collection('Users', $_SESSION['idToken']);
    if (!isset($users_collection['error'])) {
        foreach ($users_collection as $doc) {
            if (($doc['role'] ?? '') === 'warden') {
                $wardens[] = $doc;
            }
        }
    } else {
        $error = "Warning: Could not load wardens list.";
    }
} catch (Exception $e) {
    $error = "Error loading wardens: " . $e->getMessage();
}

// 🟣 Step 2: Load existing hostel data
try {
    $doc = firestore_get('Hostels', $hostelID_from_url, $_SESSION['idToken']);
    if (!isset($doc['error']) && isset($doc['fields'])) {
        foreach ($doc['fields'] as $key => $value) {
            $hostelData[$key] = reset($value);
        }
    } else {
        $error .= " Failed to load hostel data: " . ($doc['error']['message'] ?? 'Unknown error');
    }
} catch (Exception $e) {
    $error .= " Error loading data: " . $e->getMessage();
}

// Initialize form values from loaded data or POST
$name = $_POST['name'] ?? ($hostelData['name'] ?? '');
$wardenID = $_POST['wardenID'] ?? ($hostelData['wardenID'] ?? '');
$location = $_POST['location'] ?? ($hostelData['location'] ?? '');
$hostelType = $_POST['hostelType'] ?? ($hostelData['hostelType'] ?? '');
$totalRooms = $_POST['totalRooms'] ?? ($hostelData['totalRooms'] ?? '');
$capacity = $_POST['capacity'] ?? ($hostelData['capacity'] ?? '');
$totalFloors = $_POST['totalFloors'] ?? ($hostelData['totalFloors'] ?? '');

// 🟣 Step 3: Handle Update on POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($name);
    $wardenID = trim($wardenID);
    $location = trim($location);
    $hostelType = trim($hostelType);
    $totalRooms = trim($totalRooms);
    $capacity = trim($capacity);
    $totalFloors = trim($totalFloors);

    if (empty($name) || empty($location) || empty($hostelType)) {
        $error = "Please fill in all required fields: Name, Location, and Type.";
    } else {
        $updateData = [
            'name' => $name,
            'wardenID' => $wardenID,
            'location' => $location,
            'hostelType' => $hostelType,
            'totalRooms' => (int)$totalRooms,
            'capacity' => (int)$capacity,
            'totalFloors' => (int)$totalFloors,
        ];

        $res = firestore_set('Hostels', $hostelID_from_url, $updateData, $_SESSION['idToken']);

        if (isset($res['error'])) {
            $error = "Failed to update hostel: " . ($res['error']['message'] ?? 'Unknown error');
        } else {
            $message = "Hostel updated successfully!";
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
        <a href="hostel_main.php" class="back-icon-link" title="Back to Hostel List"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Update Hostel Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="hostelID">Hostel ID:</label>
        <input id="hostelID" name="hostelID" type="text" readonly disabled value="<?= htmlspecialchars($hostelID_from_url) ?>">

        <label for="name">Hostel Name <span style="color:red">*</span>:</label>
        <input id="name" name="name" type="text" required value="<?= htmlspecialchars($name) ?>" placeholder="e.g., Desasiswa Aman Damai">

        <label for="wardenID">Assign Warden (Optional):</label>
        <select id="wardenID" name="wardenID">
            <option value="">-- Not Assigned --</option>
            <?php foreach ($wardens as $warden): ?>
                <option value="<?= htmlspecialchars($warden['userID']) ?>" <?= ($wardenID === $warden['userID']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($warden['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="location">Location <span style="color:red">*</span>:</label>
        <input id="location" name="location" type="text" required value="<?= htmlspecialchars($location) ?>" placeholder="e.g., Kampus Induk">

        <label for="hostelType">Hostel Type <span style="color:red">*</span>:</label>
        <select id="hostelType" name="hostelType" required>
            <option value="">-- Select Type --</option>
            <option value="Male" <?= $hostelType === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $hostelType === 'Female' ? 'selected' : '' ?>>Female</option>
            <option value="Mixed" <?= $hostelType === 'Mixed' ? 'selected' : '' ?>>Mixed</option>
        </select>

        <label for="totalRooms">Total Rooms:</label>
        <input id="totalRooms" name="totalRooms" type="number" value="<?= htmlspecialchars($totalRooms) ?>" placeholder="e.g., 150">

        <label for="capacity">Capacity:</label>
        <input id="capacity" name="capacity" type="number" value="<?= htmlspecialchars($capacity) ?>" placeholder="e.g., 300">

        <label for="totalFloors">Total Floors:</label>
        <input id="totalFloors" name="totalFloors" type="number" value="<?= htmlspecialchars($totalFloors) ?>" placeholder="e.g., 5">

        <br><br>
        <button type="submit" class="btn-submit">Update Hostel</button>
    </form>
</div>

<!-- ✅ Success Popup -->
<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Hostel Updated Successfully</h3>
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
            window.location.href = "hostel_main.php";
        });
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
