<?php
$page_title = "View Hostel Details";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$hostelData = null;
$roomsByFloor = [];
$wardenName = 'Not Assigned';
$error = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = "Invalid Hostel ID.";
} else {
    $hostelID = $_GET['id'];
    $idToken = $_SESSION['idToken'];

    try {
        // 1. Fetch Hostel Details
        $hostelDoc = firestore_get('Hostels', $hostelID, $idToken);
        if (!isset($hostelDoc['error']) && isset($hostelDoc['fields'])) {
            foreach ($hostelDoc['fields'] as $key => $value) {
                $hostelData[$key] = reset($value);
            }

            // 2. Fetch Warden's Name
            if (!empty($hostelData['wardenUserID'])) {
                $wardenDoc = firestore_get('Users', $hostelData['wardenUserID'], $idToken);
                if (isset($wardenDoc['fields']['name'])) {
                    $wardenName = reset($wardenDoc['fields']['name']);
                }
            }

            // 3. Fetch and Group Rooms for this Hostel
            $allRooms = firestore_get_collection('Rooms', $idToken);
            if (!isset($allRooms['error'])) {
                foreach ($allRooms as $room) {
                    if (($room['hostelID'] ?? '') === $hostelID) {
                        $floor = $room['floor'] ?? 'Unknown Floor';
                        $roomsByFloor[$floor][] = $room;
                    }
                }
                // Sort floors numerically
                ksort($roomsByFloor, SORT_NUMERIC);
            } else {
                $error .= " Could not load rooms for this hostel.";
            }

        } else {
            $error = isset($hostelDoc['error']['message']) ? $hostelDoc['error']['message'] : "Failed to load hostel details.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/view_style.css">
<link rel="stylesheet" href="../../assets/hostel_room_view.css">

<div class="view-container">
    <div class="view-header">
        <a href="hostel_main.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h2>Hostel Overview</h2>
    </div>

    <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($hostelData): ?>
        <!-- Hostel Details Card -->
        <div class="details-card top-card">
            <div class="detail-row main-title">
                <strong>Hostel Name:</strong> <?= htmlspecialchars($hostelData['name'] ?? '-') ?>
            </div>
            <div class="detail-row"><strong>Warden:</strong> <?= htmlspecialchars($wardenName) ?></div>
            <div class="detail-row"><strong>Location:</strong> <?= htmlspecialchars($hostelData['location'] ?? '-') ?></div>
        </div>

        <!-- Rooms by Floor Section -->
        <?php if (!empty($roomsByFloor)): ?>
            <?php foreach ($roomsByFloor as $floor => $rooms): ?>
                <div class="floor-section">
                    <h3 class="floor-title">Floor <?= htmlspecialchars($floor) ?></h3>
                    <div class="room-grid">
                        <?php foreach ($rooms as $room): ?>
                            <a href="room_view.php?id=<?= urlencode($room['roomID']) ?>" class="room-card status-<?= strtolower(str_replace(' ', '-', $room['status'] ?? 'unknown')) ?>">
                                <div class="room-number">
                                    <i class="fa-solid fa-door-closed"></i>
                                    <span><?= htmlspecialchars($room['roomNumber'] ?? '-') ?></span>
                                </div>
                                <div class="room-status"><?= htmlspecialchars($room['status'] ?? '-') ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center; color:#aaa; margin-top: 2rem;">No rooms found for this hostel.</p>
        <?php endif; ?>

    <?php else: ?>
        <p style="text-align:center; color:#aaa;">No details found for this hostel.</p>
    <?php endif; ?>
</div>

<?php include('../../inc/footer_private.php'); ?>
