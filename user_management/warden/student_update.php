<?php
$page_title = "Update Student";
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


$message = '';
$error = '';

// Get warden's details from session
$warden = $_SESSION['user'];
$wardenHostelID = $warden['hostelID'] ?? null;
$idToken = $_SESSION['idToken'];

$studentUserID = $_GET['id'] ?? null;
$studentData = [];
$hostelName = 'Not Assigned';
$available_rooms = [];

// Main security and data loading logic
if (empty($wardenHostelID)) {
    $error = "Access Denied: You are not assigned to a hostel.";
} elseif (empty($studentUserID)) {
    $error = "Invalid Student User ID provided.";
} else {
    try {
        // 1. Fetch the student's document
        $doc = firestore_get('Users', $studentUserID, $idToken);
        if (!isset($doc['error']) && isset($doc['fields'])) {
            foreach ($doc['fields'] as $key => $value) {
                $studentData[$key] = reset($value);
            }

            // 2. SECURITY CHECK: Ensure the student belongs to the warden's hostel
            if (($studentData['hostelID'] ?? null) !== $wardenHostelID) {
                $error = "Access Denied: This student is not in your assigned hostel.";
                $studentData = []; // Clear data to prevent form display
            } else {
                // 3. Fetch Hostel Name
                $hostelDoc = firestore_get('Hostels', $wardenHostelID, $idToken);
                if (isset($hostelDoc['fields']['name'])) {
                    $hostelName = reset($hostelDoc['fields']['name']);
                }

                // 4. Fetch available rooms in the warden's hostel
                $rooms_collection = firestore_get_collection('Rooms', $idToken);
                if (!isset($rooms_collection['error'])) {
                    foreach ($rooms_collection as $room) {
                        // A room is available if its status is 'Available' OR it's the student's current room
                        if ($room['hostelID'] === $wardenHostelID && (strtolower($room['status'] ?? '') === 'available' || $room['roomID'] === ($studentData['roomID'] ?? ''))) {
                            $available_rooms[] = $room;
                        }
                    }
                } else {
                    $error .= " Warning: Could not load rooms list.";
                }
            }
        } else {
            $error = "Failed to load student data. The student may not exist.";
        }
    } catch (Exception $e) {
        $error = "An error occurred while loading data: " . $e->getMessage();
    }
}

// Initialize form values
$name = $_POST['name'] ?? ($studentData['name'] ?? '');
$studentID = $_POST['studentID'] ?? ($studentData['studentID'] ?? '');
$icNum = $_POST['icNum'] ?? ($studentData['icNum'] ?? '');
$gender = $_POST['gender'] ?? ($studentData['gender'] ?? '');
$contactNo = $_POST['contactNo'] ?? ($studentData['contactNo'] ?? '');
$roomID = $_POST['roomID'] ?? ($studentData['roomID'] ?? '');

// Handle form submission for update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $name = trim($name);
    $studentID = trim($studentID);
    $icNum = trim($icNum);
    $gender = trim($gender);
    $contactNo = trim($contactNo);
    $roomID = trim($roomID);

    if (empty($name) || empty($studentID) || empty($icNum) || empty($gender)) {
        $error = "Please fill in all required fields (Name, Student ID, IC Number, Gender).";
    } else {
        $updateData = [
            'name' => $name,
            'studentID' => $studentID,
            'icNum' => $icNum,
            'gender' => $gender,
            'contactNo' => $contactNo,
            'roomID' => $roomID,
        ];

        $res = firestore_set('Users', $studentUserID, $updateData, $idToken);

        if (isset($res['error'])) {
            $error = "Failed to update student: " . ($res['error']['message'] ?? 'Unknown error');
        } else {
            $message = "Student updated successfully!";
            echo "<script>window.successMessage = true;</script>";
        }
    }
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/form_style.css">
<link rel="stylesheet" href="../../assets/pop_style.css">

<div class="form-wrapper">
    <div class="form-header">
        <a href="student_main.php" class="back-icon-link" title="Back to Student List"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Update Student Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($studentData)): ?>
    <form method="POST">
        <label for="studentID">Student ID <span style="color:red">*</span>:</label>
        <input id="studentID" name="studentID" type="text" required value="<?= htmlspecialchars($studentID) ?>">

        <label for="name">Name <span style="color:red">*</span>:</label>
        <input id="name" name="name" type="text" required value="<?= htmlspecialchars($name) ?>">

        <label for="email">Email:</label>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($studentData['email'] ?? '') ?>" readonly disabled>

        <label for="icNum">IC Number <span style="color:red">*</span>:</label>
        <input id="icNum" name="icNum" type="text" required value="<?= htmlspecialchars($icNum) ?>">

        <label for="gender">Gender <span style="color:red">*</span>:</label>
        <select id="gender" name="gender" required>
            <option value="">-- Select Gender --</option>
            <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>

        <label for="contactNo">Contact No:</label>
        <input id="contactNo" name="contactNo" type="text" value="<?= htmlspecialchars($contactNo) ?>">

        <label for="hostelName">Hostel:</label>
        <input id="hostelName" name="hostelName" type="text" value="<?= htmlspecialchars($hostelName) ?>" readonly disabled>

        <label for="roomID">Assign Room:</label>
        <select id="roomID" name="roomID">
            <option value="">-- Unassigned --</option>
            <?php foreach ($available_rooms as $room): ?>
                <option value="<?= htmlspecialchars($room['roomID']) ?>" <?= ($roomID === $room['roomID']) ? 'selected' : '' ?>>
                    Room <?= htmlspecialchars($room['roomNumber']) ?> (<?= htmlspecialchars($room['roomType']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-submit">Update Student</button>
    </form>
    <?php endif; ?>
</div>

<!-- Success Popup -->
<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Student Updated Successfully</h3>
        <p>All details have been saved.</p>
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
            window.location.href = "student_main.php"; // Redirect back to the list
        });
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>