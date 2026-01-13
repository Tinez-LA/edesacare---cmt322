<?php
$page_title = "View Student Details";
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


$studentData = null;
$hostelName = 'Not Assigned';
$roomNumber = 'Not Assigned';
$error = '';

// Get warden's details from session
$warden = $_SESSION['user'];
$wardenHostelID = $warden['hostelID'] ?? null;
$idToken = $_SESSION['idToken'];

if (empty($wardenHostelID)) {
    $error = "Access Denied: You are not assigned to a hostel.";
} elseif (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = "Invalid Student User ID.";
} else {
    $studentUserID = $_GET['id'];

    try {
        // 1. Fetch the student's document
        $doc = firestore_get('Users', $studentUserID, $idToken);

        if (!isset($doc['error']) && isset($doc['fields'])) {
            // Convert Firestore structure to a normal array
            $studentData = [];
            foreach ($doc['fields'] as $key => $value) {
                $studentData[$key] = reset($value);
            }

            // 2. SECURITY CHECK: Ensure the student belongs to the warden's hostel
            if (($studentData['hostelID'] ?? null) !== $wardenHostelID) {
                $error = "Access Denied: This student is not in your assigned hostel.";
                $studentData = null; // Clear data to prevent display
            } else {
                // 3. Fetch Hostel Name (it will be the warden's own hostel)
                $hostelDoc = firestore_get('Hostels', $wardenHostelID, $idToken);
                if (isset($hostelDoc['fields']['name'])) {
                    $hostelName = reset($hostelDoc['fields']['name']);
                }

                // 4. Fetch Room Number if assigned
                if (!empty($studentData['roomID'])) {
                    $roomDoc = firestore_get('Rooms', $studentData['roomID'], $idToken);
                    if (isset($roomDoc['fields']['roomNumber'])) {
                        $roomNumber = reset($roomDoc['fields']['roomNumber']);
                    }
                }
            }
        } else {
            $error = "Failed to load student details. The student may not exist.";
        }
    } catch (Exception $e) {
        $error = "An error occurred: " . $e->getMessage();
    }
}
?>
<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/view_style.css">

<div class="view-container">
    <div class="view-header">
        <a href="student_main.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h2>Student Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($studentData): ?>
        <div class="profile-header-card">
            <?php
                $gender = strtolower($studentData['gender'] ?? 'unknown');
                $icon_path = ($gender === 'male') ? '../../assets/images/male_avatar.png' : '../../assets/images/female_avatar.png';
            ?>
            <img src="<?= $icon_path ?>" alt="<?= htmlspecialchars($gender) ?> icon" class="profile-icon">
            <div class="profile-info">
                <h3><?= htmlspecialchars($studentData['name'] ?? '-') ?></h3>
                <span><?= htmlspecialchars(ucfirst($studentData['role'] ?? '-')) ?></span>
            </div>
        </div>

        <div class="details-card">
            <div class="detail-row"><strong>Student ID:</strong> <?= htmlspecialchars($studentData['studentID'] ?? '-') ?></div>
            <div class="detail-row"><strong>Email:</strong> <?= htmlspecialchars($studentData['email'] ?? '-') ?></div>
            <div class="detail-row"><strong>IC Number:</strong> <?= htmlspecialchars($studentData['icNum'] ?? '-') ?></div>
            <div class="detail-row"><strong>Contact No:</strong> <?= htmlspecialchars($studentData['contactNo'] ?? '-') ?></div>
            <div class="detail-row"><strong>Gender:</strong> <?= htmlspecialchars($studentData['gender'] ?? '-') ?></div>
            <div class="detail-row"><strong>Hostel:</strong> <?= htmlspecialchars($hostelName) ?></div>
            <div class="detail-row"><strong>Room:</strong> <?= htmlspecialchars($roomNumber) ?></div>
        </div>
    <?php else: ?>
        <p style="text-align:center; color:#aaa;">No details found for this student.</p>
    <?php endif; ?>
</div>

<?php include('../../inc/footer_private.php'); ?>
