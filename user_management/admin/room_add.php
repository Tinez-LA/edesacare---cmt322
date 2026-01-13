<?php
$page_title = "Add Room";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';

// Ensure admin is logged in
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin not authenticated. Please login again.</p>');
}

// 🟣 Fetch all hostels to populate the dropdown
$hostels = [];
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

// Initialize form fields for the room
$roomID = $_POST['roomID'] ?? '';
$hostelID = $_POST['hostelID'] ?? '';
$roomNumber = $_POST['roomNumber'] ?? '';
$roomType = $_POST['roomType'] ?? '';
$capacity = $_POST['capacity'] ?? '';
$floor = $_POST['floor'] ?? '';
$status = $_POST['status'] ?? 'Available'; // Default to 'Available'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim all input values
    $roomID = trim($roomID);
    $hostelID = trim($hostelID);
    $roomNumber = trim($roomNumber);
    $roomType = trim($roomType);
    $capacity = trim($capacity);
    $floor = trim($floor);
    $status = trim($status);

    // Basic validation
    if (empty($roomID) || empty($hostelID) || empty($roomNumber) || empty($roomType) || empty($status) || empty($floor)) {
        $error = "Please fill in all required fields: Room ID, Hostel, Room Number, Floor, Type, and Status.";
    } else {
        // 🔹 Check if room ID already exists
        $existingRoom = firestore_get('Rooms', $roomID, $_SESSION['idToken']);
        if (isset($existingRoom['fields'])) {
            $error = "Room with ID '$roomID' already exists. Please use a unique ID.";
        } else {
            // 🔹 Save data to Firestore 'Rooms' collection
            $roomData = [
                'roomID' => $roomID,
                'hostelID' => $hostelID,
                'roomNumber' => $roomNumber,
                'roomType' => $roomType,
                'capacity' => (int)$capacity,
                'floor' => (int)$floor,
                'status' => $status,
            ];

            $res = firestore_set('Rooms', $roomID, $roomData, $_SESSION['idToken']);

            if (isset($res['error'])) {
                $error = "Failed to save room in Firestore: " . ($res['error']['message'] ?? 'Unknown error');
            } else {
                $message = "Room created successfully!";
                echo "<script>window.successMessage = true;</script>";

                // Clear form after success
                $roomID = $hostelID = $roomNumber = $roomType = $capacity = $floor = $status = '';
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
        <a href="room_main.php" class="back-icon-link" title="Back to Room List"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Add New Room</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="roomID">Room ID <span style="color:red">*</span>:</label>
        <input id="roomID" name="roomID" type="text" required value="<?= htmlspecialchars($roomID) ?>" placeholder="e.g., AD-01-01">

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
        <button type="submit" class="btn-submit">Add Room</button>
    </form>
</div>

<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Room Created Successfully</h3>
        <p>The new room has been added to the system.</p>
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