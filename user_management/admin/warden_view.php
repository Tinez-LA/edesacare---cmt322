<?php
$page_title = "View Warden Details";
include_once('../../inc/auth_check.php');
require_role('admin');
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

/* ===============================
   FETCH WARDEN DETAILS
   =============================== */
$wardenData = null;
$hostelName = 'Not Assigned';
$error = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = "Invalid Warden ID.";
} else {
    $wardenID = $_GET['id'];

    try {
        $doc = firestore_get('Users', $wardenID, $_SESSION['idToken']);
        if (!isset($doc['error']) && isset($doc['fields'])) {
            // Convert Firestore structure → normal array
            $wardenData = [];
            foreach ($doc['fields'] as $key => $value) {
                $wardenData[$key] = reset($value);
            }

            // Fetch Hostel Name if assigned
            if (!empty($wardenData['hostelID'])) {
                $hostelDoc = firestore_get('Hostels', $wardenData['hostelID'], $_SESSION['idToken']);
                if (isset($hostelDoc['fields']['name'])) {
                    $hostelName = reset($hostelDoc['fields']['name']);
                }
            }
        } else {
            $error = isset($doc['error']['message']) ? $doc['error']['message'] : "Failed to load warden details.";
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
        <a href="warden_main.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h2>Warden Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($wardenData): ?>
        <!-- New Profile Header Card -->
        <div class="profile-header-card">
            <?php
                $gender = strtolower($wardenData['gender'] ?? 'unknown');
                $icon_path = '../../../assets/images/default_avatar.png'; // Fallback icon
                if ($gender === 'male') {
                    $icon_path = '../../../assets/images/male_avatar.png';
                } elseif ($gender === 'female') {
                    $icon_path = '../../../assets/images/female_avatar.png';
                }
            ?>
            <img src="<?= $icon_path ?>" alt="<?= htmlspecialchars($gender) ?> icon" class="profile-icon">
            <div class="profile-info">
                <h3><?= htmlspecialchars($wardenData['name'] ?? '-') ?></h3>
                <span><?= htmlspecialchars(ucfirst($wardenData['role'] ?? '-')) ?></span>
            </div>
        </div>

        <div class="details-card">
            <div class="detail-row"><strong>Email:</strong> <?= htmlspecialchars($wardenData['email'] ?? '-') ?></div>
            <div class="detail-row"><strong>IC Number:</strong> <?= htmlspecialchars($wardenData['icNumber'] ?? '-') ?></div>
            <div class="detail-row"><strong>Contact No:</strong> <?= htmlspecialchars($wardenData['contactNo'] ?? '-') ?></div>
            <div class="detail-row"><strong>Gender:</strong> <?= htmlspecialchars($wardenData['gender'] ?? '-') ?></div>
            <div class="detail-row"><strong>Hostel:</strong> <?= htmlspecialchars($hostelName) ?></div>
            <div class="detail-row"><strong>Change Password Count:</strong> <?= htmlspecialchars($wardenData['changepasswordcount'] ?? '0') ?></div>
        </div>
    <?php else: ?>
        <p style="text-align:center; color:#aaa;">No details found for this warden.</p>
    <?php endif; ?>
</div>

<?php include('../../inc/footer_private.php'); ?>
