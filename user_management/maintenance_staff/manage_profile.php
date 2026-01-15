<?php
$page_title = "Manage Profile";
include_once('../../inc/auth_check.php');
require_role('maintenance_staff');
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
   SESSION VALIDATION
   =============================== */
if (empty($_SESSION['user']) || empty($_SESSION['idToken'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$user = $_SESSION['user'];
$idToken = $_SESSION['idToken'];
$hostelName = 'Not Assigned';

$message = "";
$message_type = "";

/* ===============================
   AJAX PASSWORD RESET
   =============================== */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'send_reset_email'
) {
    header('Content-Type: application/json');

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email address cannot be empty.']);
        exit;
    }

    $response = firebase_send_reset($email);

    if (isset($response['error'])) {
        echo json_encode(['success' => false, 'message' => 'Failed to send reset email.']);
    } else {
        echo json_encode(['success' => true, 'message' => "✅ Password reset link sent to {$email}"]);
    }
    exit;
}

/* ===============================
   FETCH HOSTEL NAME
   =============================== */
if (!empty($user['hostelID'])) {
    try {
        $hostelDoc = firestore_get('Hostels', $user['hostelID'], $idToken);
        if (isset($hostelDoc['fields']['name'])) {
            $hostelName = reset($hostelDoc['fields']['name']);
        }
    } catch (Exception $e) {}
}

/* ===============================
   PROFILE UPDATE
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {

    $new_name    = trim($_POST['name'] ?? '');
    $new_contact = trim($_POST['contactNo'] ?? '');
    $new_icnum   = trim($_POST['icnum'] ?? '');

    $userID = $user['userID'] ?? ($user['uid'] ?? '');

    if (empty($userID)) {
        $message = "⚠️ User ID not found in session.";
        $message_type = "error";
    }
    elseif (empty($new_name)) {
        $message = "⚠️ Name cannot be empty.";
        $message_type = "error";
    }
    else {

        /* ===============================
           🔒 FORCE IC- / TEL-
           =============================== */
        if (!empty($new_icnum) && !str_starts_with($new_icnum, 'IC-')) {
            $new_icnum = 'IC-' . $new_icnum;
        }

        if (!empty($new_contact) && !str_starts_with($new_contact, 'TEL-')) {
            $new_contact = 'TEL-' . $new_contact;
        }

        /* ===============================
           UPDATE ONLY SAFE FIELDS
           =============================== */
        $updateData = [
            'name'      => $new_name,
            'contactNo'=> $new_contact,
            'icnum'    => $new_icnum
        ];

        $update = firestore_set('Users', $userID, $updateData, $idToken);

        if (isset($update['error'])) {
            $message = "❌ Failed to update profile. Try again later.";
            $message_type = "error";
        } else {
            // Update session
            $_SESSION['user']['name'] = $new_name;
            $_SESSION['user']['contactNo'] = $new_contact;
            $_SESSION['user']['icnum'] = $new_icnum;

            $message = "✅ Profile updated successfully.";
            $message_type = "success";
        }
    }
}
?>

<link rel="stylesheet" href="../../assets/profile_style.css">
<link rel="stylesheet" href="../../assets/header_style.css">

<div class="profile-container">
    <div class="profile-header">
        <h2>Manage Profile</h2>
        <button type="button" id="resetPasswordBtn" class="btn-reset-password">
            🔑 Reset Password
        </button>
    </div>

    <?php if ($message): ?>
        <div class="message <?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="profile-section">
            <h3>Personal Information</h3>

            <div class="form-group">
                <label>User ID</label>
                <input type="text" value="<?= htmlspecialchars($user['userID'] ?? ($user['uid'] ?? '-')) ?>" disabled>
            </div>

            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
            </div>

            <div class="form-group">
                <label>Role</label>
                <input type="text" value="<?= htmlspecialchars(ucfirst($user['role'] ?? '')) ?>" disabled>
            </div>

            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contactNo" value="<?= htmlspecialchars($user['contactNo'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Staff ID</label>
                <input type="text" value="<?= htmlspecialchars($user['staffID'] ?? '-') ?>" disabled>
            </div>

            <div class="form-group">
                <label>IC Number</label>
                <input type="text" name="icnum" value="<?= htmlspecialchars($user['icnum'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Staff Role</label>
                <input type="text" value="<?= htmlspecialchars($user['staffRole'] ?? '-') ?>" disabled>
            </div>

            <div class="form-group">
                <label>Status</label>
                <input type="text" value="<?= htmlspecialchars($user['status'] ?? '-') ?>" disabled>
            </div>

            <div class="form-group">
                <label>Assigned Hostel</label>
                <input type="text" value="<?= htmlspecialchars($hostelName) ?>" disabled>
            </div>
        </div>

        <button type="submit" name="save_profile" class="btn-update">💾 Save Changes</button>
    </form>
</div>

<!-- Reset Password Popup -->
<div id="resetPasswordPopup" class="popup-overlay">
    <div class="popup-box">
        <h3>Reset Your Password</h3>
        <form id="resetPasswordForm">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            </div>
            <div id="popupMessage" class="popup-message"></div>
            <div class="popup-buttons">
                <button type="button" id="cancelResetBtn" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-update">Send Reset Link</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const popup = document.getElementById('resetPasswordPopup');
    const openBtn = document.getElementById('resetPasswordBtn');
    const cancelBtn = document.getElementById('cancelResetBtn');
    const resetForm = document.getElementById('resetPasswordForm');
    const popupMessage = document.getElementById('popupMessage');

    openBtn.onclick = () => {
        popup.style.display = 'flex';
        popupMessage.textContent = '';
        popupMessage.className = 'popup-message';
    };

    cancelBtn.onclick = () => popup.style.display = 'none';
    popup.onclick = e => e.target === popup && (popup.style.display = 'none');

    resetForm.onsubmit = e => {
        e.preventDefault();
        const data = new FormData(resetForm);
        data.append('action', 'send_reset_email');

        fetch('', { method: 'POST', body: data })
            .then(res => res.json())
            .then(res => {
                popupMessage.textContent = res.message;
                popupMessage.className = res.success ? 'popup-message success' : 'popup-message error';
            })
            .catch(() => {
                popupMessage.textContent = 'Network error.';
                popupMessage.className = 'popup-message error';
            });
    };
});
</script>

<?php include('../../inc/footer_private.php'); ?>
