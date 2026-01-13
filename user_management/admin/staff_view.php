<?php
$page_title = "View Maintenance Staff Details";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$staffData = null;
$error = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = "Invalid Staff User ID.";
} else {
    $staffUserID = $_GET['id'];

    try {
        $doc = firestore_get('Users', $staffUserID, $_SESSION['idToken']);
        if (!isset($doc['error']) && isset($doc['fields'])) {
            // Convert Firestore structure → normal array
            $staffData = [];
            foreach ($doc['fields'] as $key => $value) {
                $staffData[$key] = reset($value);
            }
        } else {
            $error = isset($doc['error']['message']) ? $doc['error']['message'] : "Failed to load staff details.";
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
        <a href="staff_main.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h2>Maintenance Staff Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($staffData): ?>
        <!-- New Profile Header Card -->
        <div class="profile-header-card">
            <?php
                $gender = strtolower($staffData['gender'] ?? 'unknown');
                $icon_path = '../../../assets/images/default_avatar.png'; // Fallback icon
                if ($gender === 'male') {
                    $icon_path = '../../../assets/images/male_avatar.png';
                } elseif ($gender === 'female') {
                    $icon_path = '../../../assets/images/female_avatar.png';
                }
            ?>
            <img src="<?= $icon_path ?>" alt="<?= htmlspecialchars($gender) ?> icon" class="profile-icon">
            <div class="profile-info">
                <h3><?= htmlspecialchars($staffData['name'] ?? '-') ?></h3>
                <span><?= htmlspecialchars($staffData['staffRole'] ?? 'Staff') ?></span>
            </div>
        </div>

        <div class="details-card">
            <div class="detail-row"><strong>Staff ID:</strong> <?= htmlspecialchars($staffData['staffID'] ?? '-') ?></div>
            <div class="detail-row"><strong>Email:</strong> <?= htmlspecialchars($staffData['email'] ?? '-') ?></div>
            <div class="detail-row"><strong>IC Number:</strong> <?= htmlspecialchars($staffData['icNum'] ?? '-') ?></div>
            <div class="detail-row"><strong>Contact No:</strong> <?= htmlspecialchars($staffData['contactNo'] ?? '-') ?></div>
            <div class="detail-row"><strong>Gender:</strong> <?= htmlspecialchars($staffData['gender'] ?? '-') ?></div>
            <div class="detail-row"><strong>Staff Role:</strong> <?= htmlspecialchars($staffData['staffRole'] ?? '-') ?></div>
            <div class="detail-row"><strong>Status:</strong> <?= htmlspecialchars($staffData['status'] ?? '-') ?></div>
            <div class="detail-row"><strong>Assigned Hostel:</strong> <?= htmlspecialchars($staffData['hostelID'] ?? 'Not Assigned') ?></div>
        </div>
    <?php else: ?>
        <p style="text-align:center; color:#aaa;">No details found for this maintenance staff member.</p>
    <?php endif; ?>
</div>

<?php include('../../inc/footer_private.php'); ?>
