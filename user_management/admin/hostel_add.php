<?php
$page_title = "Add Hostel";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';
/* ===============================
   SESSION INACTIVITY TIMEOUT
   =============================== */
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: /auth/login.php?timeout=1");
    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();


// Ensure admin is logged in
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin not authenticated. Please login again.</p>');
}

// 🟣 Fetch all wardens to populate the dropdown
$wardens = [];
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

// Initialize form fields for the hostel
$hostelID = $_POST['hostelID'] ?? '';
$name = $_POST['name'] ?? '';
$wardenID = $_POST['wardenID'] ?? '';
$location = $_POST['location'] ?? '';
$hostelType = $_POST['hostelType'] ?? '';
$totalRooms = $_POST['totalRooms'] ?? '';
$capacity = $_POST['capacity'] ?? '';
$totalFloors = $_POST['totalFloors'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim all input values
    $hostelID = trim($hostelID);
    $name = trim($name);
    $wardenID = trim($wardenID);
    $location = trim($location);
    $hostelType = trim($hostelType);
    $totalRooms = trim($totalRooms);
    $capacity = trim($capacity);
    $totalFloors = trim($totalFloors);

    // Basic validation
    if (empty($hostelID) || empty($name) || empty($hostelType) || empty($location)) {
        $error = "Please fill in all required fields: Hostel ID, Name, Location, and Type.";
    } else {
        // 🔹 Check if hostel ID already exists
        $existingHostel = firestore_get('Hostels', $hostelID, $_SESSION['idToken']);
        if (isset($existingHostel['fields'])) {
            $error = "Hostel with ID '$hostelID' already exists. Please use a unique ID.";
        } else {
            // 🔹 Save data to Firestore 'Hostels' collection
            $hostelData = [
                'hostelID' => $hostelID,
                'name' => $name,
                'wardenID' => $wardenID,
                'location' => $location,
                'hostelType' => $hostelType,
                'totalRooms' => (int)$totalRooms,
                'capacity' => (int)$capacity,
                'totalFloors' => (int)$totalFloors,
            ];

            $res = firestore_set('Hostels', $hostelID, $hostelData, $_SESSION['idToken']);

            if (isset($res['error'])) {
                $error = "Failed to save hostel in Firestore: " . ($res['error']['message'] ?? 'Unknown error');
            } else {
                $message = "Hostel created successfully!";
                echo "<script>window.successMessage = true;</script>";

                // Clear form after success
                $hostelID = $name = $wardenID = $location = $hostelType = $totalRooms = $capacity = $totalFloors = '';
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
        <a href="hostel_main.php" class="back-icon-link" title="Back to Hostel List"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Add New Hostel</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="hostelID">Hostel ID <span style="color:red">*</span>:</label>
        <input id="hostelID" name="hostelID" type="text" required value="<?= htmlspecialchars($hostelID) ?>" placeholder="e.g., AD01">

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
        <button type="submit" class="btn-submit">Add Hostel</button>
    </form>
</div>

<!-- ⭐ NEW — Popup HTML -->
<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Hostel Created Successfully</h3>
        <p>The new hostel has been added to the system.</p>
        <button id="okBtn">Okay</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // ⭐ NEW — Show popup if success
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
