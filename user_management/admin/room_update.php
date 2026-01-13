<?php
$page_title = "Update Room";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';

// 🔒 Ensure admin session is valid
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin not authenticated. Please login again.</p>');
}

// 🔹 Validate Room ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('<p style="color:red">Missing room ID.</p>');
}

$roomID_from_url = $_GET['id'];
$roomData = [];
$hostels = [];

// 🟣 Step 1: Fetch all hostels to populate the dropdown
try {
    $hostels_collection = firestore_get_collection('Hostels', $_SESSION['idToken']);
    if (!isset($hostels_collection['error'])) {
        $hostels = $hostels_collection;
    } else {
        $error = "Warning: Could not load hostels list.";
    }
} catch (Exception $e) {
    $error = "Error loading hostels: " . $e->getMessage();
}

// 🟣 Step 2: Load existing room data
try {
    $doc = firestore_get('Rooms', $roomID_from_url, $_SESSION['idToken']);
    if (!isset($doc['error']) && isset($doc['fields'])) {
        foreach ($doc['fields'] as $key => $value) {
            $roomData[$key] = reset($value);
        }
    } else {
        $error .= " Failed to load room data: " . ($doc['error']['message'] ?? 'Unknown error');
    }
} catch (Exception $e) {
    $error .= " Error loading data: " . $e->getMessage();
}

// Initialize form values from loaded data or POST
$hostelID = $_POST['hostelID'] ?? ($roomData['hostelID'] ?? '');
$roomNumber = $_POST['roomNumber'] ?? ($roomData['roomNumber'] ?? '');
$roomType = $_POST['roomType'] ?? ($roomData['roomType'] ?? '');
$capacity = $_POST['capacity'] ?? ($roomData['capacity'] ?? '');
$floor = $_POST['floor'] ?? ($roomData['floor'] ?? '');
$status = $_POST['status'] ?? ($roomData['status'] ?? '');

// 🟣 Step 3: Handle Update on POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hostelID = trim($hostelID);
    $roomNumber = trim($roomNumber);
    $roomType = trim($roomType);
    $capacity = trim($capacity);
    $floor = trim($floor);
    $status = trim($status);

    if (empty($hostelID) || empty($roomNumber) || empty($roomType) || empty($status) || empty($floor)) {
        $error = "Please fill in all required fields.";
    } else {
        $updateData = [
            'hostelID' => $hostelID,
            'roomNumber' => $roomNumber,
            'roomType' => $roomType,
            'capacity' => (int)$capacity,
            'floor' => (int)$floor,
            'status' => $status,
        ];

        $res = firestore_set('Rooms', $roomID_from_url, $updateData, $_SESSION['idToken']);

        if (isset($res['error'])) {
            $error = "Failed to update room: " . ($res['error']['message'] ?? 'Unknown error');
        } else {
            $message = "Room updated successfully!";
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
        <a href="room_main.php" class="back-icon-link" title="Back to Room List"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Update Room Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars(trim($error)) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="roomID">Room ID:</label>
        <input id="roomID" name="roomID" type="text" readonly disabled value="<?= htmlspecialchars($roomID_from_url) ?>">

        <label for="hostelID">Hostel <span style="color:red">*</span>:</label>
        <select id="hostelID" name="hostelID" required>
            <option value="">-- Select a Hostel --</option>
            <?php foreach ($hostels as $hostel): ?>
                <option value="<?= htmlspecialchars($hostel['hostelID']) ?>" <?= ($hostelID === $hostel['hostelID']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($hostel['name']) ?> (<?= htmlspecialchars($hostel['hostelID']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="roomNumber">Room Number <span style="color:red">*</span>:</label>
        <input id="roomNumber" name="roomNumber" type="text" required value="<?= htmlspecialchars($roomNumber) ?>" placeholder="e.g., 101">

        <label for="floor">Floor <span style="color:red">*</span>:</label>
        <input id="floor" name="floor" type="number" required value="<?= htmlspecialchars($floor) ?>" placeholder="e.g., 1">

        <label for="roomType">Room Type <span style="color:red">*</span>:</label>
        <select id="roomType" name="roomType" required>
            <option value="">-- Select Type --</option>
            <option value="Single" <?= $roomType === 'Single' ? 'selected' : '' ?>>Single</option>
            <option value="Double" <?= $roomType === 'Double' ? 'selected' : '' ?>>Double</option>
            <option value="Triple" <?= $roomType === 'Triple' ? 'selected' : '' ?>>Triple</option>
            <option value="Quad" <?= $roomType === 'Quad' ? 'selected' : '' ?>>Quad</option>
        </select>

        <label for="capacity">Capacity:</label>
        <input id="capacity" name="capacity" type="number" value="<?= htmlspecialchars($capacity) ?>" placeholder="e.g., 2">

        <label for="status">Status <span style="color:red">*</span>:</label>
        <select id="status" name="status" required>
            <option value="Available" <?= $status === 'Available' ? 'selected' : '' ?>>Available</option>
            <option value="Occupied" <?= $status === 'Occupied' ? 'selected' : '' ?>>Occupied</option>
            <option value="Maintenance" <?= $status === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
        </select>

        <br><br>
        <button type="submit" class="btn-submit">Update Room</button>
    </form>
</div>

<!-- ✅ Success Popup -->
<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Room Updated Successfully</h3>
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
            window.location.href = "room_main.php";
        });
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>