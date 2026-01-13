<?php
$page_title = "View Room Details";
include_once('../../inc/auth_check.php');
require_role('warden');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');


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


$roomData = null;
$studentsInRoom = [];
$error = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = "Invalid Room ID.";
} else {
    $roomID = $_GET['id'];
    $idToken = $_SESSION['idToken'];

    try {
        // 1. Fetch Room Details
        $roomDoc = firestore_get('Rooms', $roomID, $idToken);
        if (!isset($roomDoc['error']) && isset($roomDoc['fields'])) {
            foreach ($roomDoc['fields'] as $key => $value) {
                $roomData[$key] = reset($value);
            }

            // 2. Fetch all users and find students in this room
            $allUsers = firestore_get_collection('Users', $idToken);
            if (!isset($allUsers['error'])) {
                foreach ($allUsers as $user) {
                    if (($user['role'] ?? '') === 'student' && ($user['roomID'] ?? '') === $roomID) {
                        $studentsInRoom[] = $user;
                    }
                }
            } else {
                $error .= " Could not load student data.";
            }
        } else {
            $error = "Failed to load room details.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/view_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">

<div class="view-container">
    <div class="view-header">
        <a href="hostel_view.php?id=<?= urlencode($roomData['hostelID'] ?? '') ?>" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Hostel</a>
        <h2>Room Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($roomData): ?>
        <!-- Room Details Card -->
        <div class="details-card top-card">
            <div class="detail-row"><strong>Room Number:</strong> <?= htmlspecialchars($roomData['roomNumber'] ?? '-') ?></div>
            <div class="detail-row"><strong>Room Type:</strong> <?= htmlspecialchars($roomData['roomType'] ?? '-') ?></div>
            <div class="detail-row"><strong>Status:</strong> <?= htmlspecialchars($roomData['status'] ?? '-') ?></div>
            <div class="detail-row"><strong>Capacity:</strong> <?= htmlspecialchars($roomData['capacity'] ?? '-') ?></div>
            <div class="detail-row"><strong>Floor:</strong> <?= htmlspecialchars($roomData['floor'] ?? '-') ?></div>
        </div>

        <!-- Student Occupants Section -->
        <h3 class="section-title">Occupants</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact No</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($studentsInRoom)): ?>
                        <?php foreach ($studentsInRoom as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['studentID'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($student['name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($student['email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($student['contactNo'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; color:#ccc;">This room is currently empty.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <p style="text-align:center; color:#aaa;">No details found for this room.</p>
    <?php endif; ?>
</div>

<?php include('../../inc/footer_private.php'); ?>
