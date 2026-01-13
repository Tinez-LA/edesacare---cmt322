<?php
$page_title = "View Student Details";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$studentData = null;
$hostelName = 'Not Assigned';
$error = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = "Invalid Student User ID.";
} else {
    $studentUserID = $_GET['id'];

    try {
        $doc = firestore_get('Users', $studentUserID, $_SESSION['idToken']);
        if (!isset($doc['error']) && isset($doc['fields'])) {
            // Convert Firestore structure → normal array
            $studentData = [];
            foreach ($doc['fields'] as $key => $value) {
                $studentData[$key] = reset($value);
            }

            // Fetch Hostel Name
            if (!empty($studentData['hostelID'])) {
                $hostelDoc = firestore_get('Hostels', $studentData['hostelID'], $_SESSION['idToken']);
                if (isset($hostelDoc['fields']['name'])) {
                    $hostelName = reset($hostelDoc['fields']['name']);
                }
            }
        } else {
            $error = isset($doc['error']['message']) ? $doc['error']['message'] : "Failed to load student details.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
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
        <!-- New Profile Header Card -->
        <div class="profile-header-card">
            <?php
                $gender = strtolower($studentData['gender'] ?? 'unknown');
                $icon_path = '../../../assets/images/default_avatar.png'; // Fallback icon
                if ($gender === 'male') {
                    $icon_path = '../../../assets/images/male_avatar.png';
                } elseif ($gender === 'female') {
                    $icon_path = '../../../assets/images/female_avatar.png';
                }
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
            <div class="detail-row"><strong>Room ID:</strong> <?= htmlspecialchars($studentData['roomID'] ?? 'Not Assigned') ?></div>
        </div>
    <?php else: ?>
        <p style="text-align:center; color:#aaa;">No details found for this student.</p>
    <?php endif; ?>
</div>

<?php include('../../inc/footer_private.php'); ?>
