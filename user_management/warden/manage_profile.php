<?php
$page_title = "Manage Profile";
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
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'send_reset_email'
) {
    header('Content-Type: application/json');

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email cannot be empty.']);
        exit;
    }

    $response = firebase_send_reset($email);

    if (isset($response['error'])) {
        echo json_encode(['success' => false, 'message' => 'Failed to send reset email.']);
    } else {
        echo json_encode(['success' => true, 'message' => "✅ Reset link sent to {$email}"]);
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

    $new_name = trim($_POST['name'] ?? '');
    $new_contact = trim($_POST['contactNo'] ?? '');
    $new_icNumber = trim($_POST['icNumber'] ?? '');

    $userID = $user['userID'] ?? ($user['uid'] ?? '');

    if (empty($userID)) {
        $message = "⚠️ User ID not found.";
        $message_type = "error";
    }
    elseif (empty($new_name)) {
        $message = "⚠️ Name cannot be empty.";
        $message_type = "error";
    }
    else {

        /* ===============================
           NORMALIZE IC & TEL
           =============================== */
        if (!empty($new_icNumber) && !str_starts_with($new_icNumber, 'IC-')) {
            $new_icNumber = 'IC-' . $new_icNumber;
        }

        if (!empty($new_contact) && !str_starts_with($new_contact, 'TEL-')) {
            $new_contact = 'TEL-' . $new_contact;
        }

        $updateData = [
            'name' => $new_name,
            'contactNo' => $new_contact,
            'icNumber' => $new_icNumber
        ];

        $update = firestore_set('Users', $userID, $updateData, $idToken);

        if (isset($update['error'])) {
            $message = "❌ Failed to update profile.";
            $message_type = "error";
        } else {
            $_SESSION['user']['name'] = $new_name;
            $_SESSION['user']['contactNo'] = $new_contact;
            $_SESSION['user']['icNumber'] = $new_icNumber;

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
                <input value="<?= htmlspecialchars($user['userID'] ?? ($user['uid'] ?? '-')) ?>" disabled>
            </div>

            <div class="form-group">
                <label>Name</label>
                <input name="name" required value="<?= htmlspecialchars($user['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
            </div>

            <div class="form-group">
                <label>Role</label>
                <input value="<?= htmlspecialchars(ucfirst($user['role'] ?? '')) ?>" disabled>
            </div>

            <div class="form-group">
                <label>Contact Number</label>
                <input name="contactNo" value="<?= htmlspecialchars($user['contactNo'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>IC Number</label>
                <input name="icNumber" value="<?= htmlspecialchars($user['icNumber'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Assigned Hostel</label>
                <input value="<?= htmlspecialchars($hostelName) ?>" disabled>
            </div>
        </div>

        <button type="submit" name="save_profile" class="btn-update">
            💾 Save Changes
        </button>
    </form>
</div>

<!-- PASSWORD RESET POPUP -->
<div id="resetPasswordPopup" class="popup-overlay">
    <div class="popup-box">
        <h3>Reset Your Password</h3>

        <form id="resetPasswordForm">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>">
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
    const form = document.getElementById('resetPasswordForm');
    const msg = document.getElementById('popupMessage');

    openBtn.onclick = () => {
        popup.style.display = 'flex';
        msg.textContent = '';
        msg.className = 'popup-message';
    };

    cancelBtn.onclick = () => popup.style.display = 'none';
    popup.onclick = e => e.target === popup && (popup.style.display = 'none');

    form.onsubmit = e => {
        e.preventDefault();
        const data = new FormData(form);
        data.append('action', 'send_reset_email');

        fetch('', { method: 'POST', body: data })
            .then(res => res.json())
            .then(res => {
                msg.textContent = res.message;
                msg.className = res.success ? 'popup-message success' : 'popup-message error';
            })
            .catch(() => {
                msg.textContent = 'Network error.';
                msg.className = 'popup-message error';
            });
    };
});
</script>

<?php include('../../inc/footer_private.php'); ?>
