<?php
$page_title = "Manage Profile";
include_once('../../inc/auth_check.php');
require_role('student');
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


// 🟣 Ensure session is valid
if (empty($_SESSION['user']) || empty($_SESSION['idToken'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$user = $_SESSION['user'];
$idToken = $_SESSION['idToken'];
$hostelName = 'Not Assigned';
$roomNumber = 'Not Assigned';

$message = "";
$message_type = "";

// 🟣 Handle AJAX request for password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_reset_email') {
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email address cannot be empty.']);
        exit;
    }

    // Use the existing firebase function to send the reset email
    $response = firebase_send_reset($email);

    if (isset($response['error'])) {
        $errorMessage = $response['error']['message'] ?? 'An unknown error occurred.';
        echo json_encode(['success' => false, 'message' => "Failed to send reset email: {$errorMessage}"]);
    } else {
        echo json_encode(['success' => true, 'message' => "✅ Password reset link successfully sent to {$email}"]);
    }
    exit; // Stop further script execution
}

// 🟣 Fetch Hostel and Room Info
if (!empty($user['hostelID'])) {
    try {
        $hostelDoc = firestore_get('Hostels', $user['hostelID'], $idToken);
        if (isset($hostelDoc['fields']['name'])) {
            $hostelName = reset($hostelDoc['fields']['name']);
        }
    } catch (Exception $e) { /* Ignore */ }
}
if (!empty($user['roomID'])) {
    try {
        $roomDoc = firestore_get('Rooms', $user['roomID'], $idToken);
        if (isset($roomDoc['fields']['roomNumber'])) {
            $roomNumber = reset($roomDoc['fields']['roomNumber']);
        }
    } catch (Exception $e) { /* Ignore */ }
}


// 🟣 Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $new_name = trim($_POST['name']);
    $new_contact = trim($_POST['contactNo']);
    $new_icnum = trim($_POST['icnum']);

    // Use Firebase UID as primary key (document ID)
    $userID = $user['userID'] ?? ($user['uid'] ?? ''); // ✅ Use userID field, fallback to uid

    if (empty($userID)) {
        $message = "⚠️ User ID not found in session.";
        $message_type = "error";
    } elseif (empty($new_name)) {
        $message = "⚠️ Name cannot be empty.";
        $message_type = "error";
    } else {
        // ✅ Build an array with only the fields that are being updated.
        $updateData = [
            'name' => $new_name,
            'contactNo' => $new_contact,
            'icnum' => $new_icnum,
        ];

        // 🔹 Update Firestore document
        $update = firestore_set('Users', $userID, $updateData, $idToken);

        if (isset($update['error'])) {
            $message = "❌ Failed to update profile. Try again later.";
            $message_type = "error";
        } else {
            // ✅ Update local session values
            $_SESSION['user']['name'] = $new_name;
            $_SESSION['user']['contactNo'] = $new_contact;
            $_SESSION['user']['icnum'] = $new_icnum;

            $message = "✅ Profile updated successfully.";
            $message_type = "success";
        }
    }
}
?>

<!-- Styles -->
<link rel="stylesheet" href="../../assets/profile_style.css">
<link rel="stylesheet" href="../../assets/header_style.css">

<div class="profile-container">
    <div class="profile-header">
        <h2>Manage Profile</h2>
        <button type="button" id="resetPasswordBtn" class="btn-reset-password">
           🔑 Reset Password
        </button>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="profile-section">
            <h3>Personal Information</h3>

            <div class="form-group">
                <label for="userID">User ID:</label>
                <input type="text" id="userID" 
                       value="<?= htmlspecialchars($user['userID'] ?? ($user['uid'] ?? '-')) ?>" 
                       disabled>
            </div>

            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" 
                       value="<?= htmlspecialchars($user['name'] ?? '') ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" 
                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                       disabled>
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <input type="text" id="role" 
                       value="<?= htmlspecialchars(ucfirst($user['role'] ?? '')) ?>" 
                       disabled>
            </div>

            <div class="form-group">
                <label for="contactNo">Contact Number:</label>
                <input type="text" id="contactNo" name="contactNo" 
                       value="<?= htmlspecialchars($user['contactNo'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="studentID">Student ID:</label>
                <input type="text" id="studentID" 
                       value="<?= htmlspecialchars($user['studentID'] ?? '-') ?>" 
                       disabled>
            </div>

            <div class="form-group">
                <label for="icnum">IC Number:</label>
                <input type="text" id="icnum" name="icnum" 
                       value="<?= htmlspecialchars($user['icnum'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="hostel">Hostel:</label>
                <input type="text" id="hostel" 
                       value="<?= htmlspecialchars($hostelName) ?>" 
                       disabled>
            </div>

            <div class="form-group">
                <label for="room">Room Number:</label>
                <input type="text" id="room" 
                       value="<?= htmlspecialchars($roomNumber) ?>" disabled>
            </div>
        </div>

        <button type="submit" name="save_profile" class="btn-update">💾 Save Changes</button>
    </form>
</div>

<!-- 🟣 New Password Reset Popup -->
<div id="resetPasswordPopup" class="popup-overlay">
    <div class="popup-box">
        <h3>Reset Your Password</h3>
        <p>Enter your email address below to receive a password reset link.</p>
        <form id="resetPasswordForm">
            <div class="form-group">
                <label for="resetEmail">Email Address:</label>
                <input type="email" id="resetEmail" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>">
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

    // Show the popup
    openBtn.addEventListener('click', () => {
        popup.style.display = 'flex';
        popupMessage.textContent = ''; // Clear previous messages
        popupMessage.className = 'popup-message';
    });

    // Hide the popup
    function closePopup() {
        popup.style.display = 'none';
    }
    cancelBtn.addEventListener('click', closePopup);
    popup.addEventListener('click', function(e) {
        if (e.target === popup) {
            closePopup();
        }
    });

    // Handle form submission via AJAX
    resetForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'send_reset_email');

        fetch('', { // Post to the same page
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            popupMessage.textContent = data.message;
            popupMessage.className = data.success ? 'popup-message success' : 'popup-message error';
        })
        .catch(error => {
            popupMessage.textContent = 'A network error occurred. Please try again.';
            popupMessage.className = 'popup-message error';
        });
    });
});
</script>

<?php include('../../inc/footer_private.php'); ?>
