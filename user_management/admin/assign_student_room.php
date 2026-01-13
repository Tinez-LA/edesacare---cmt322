<?php
$page_title = "Assign Student to Room";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';
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


// Ensure admin is logged in
if (empty($_SESSION['idToken'])) {
    die('<p style="color:red">Admin not authenticated. Please login again.</p>');
}

$idToken = $_SESSION['idToken'];
$students = [];
$hostels = [];
$rooms = [];

// 1. Fetch all unassigned students (or all students)
try {
    $users_collection = firestore_get_collection('Users', $idToken);
    if (!isset($users_collection['error'])) {
        foreach ($users_collection as $doc) {
            if (($doc['role'] ?? '') === 'student' && empty($doc['roomID'])) {
                $students[] = $doc;
            }
        }
    } else {
        $error .= " Warning: Could not load students list.";
    }
} catch (Exception $e) {
    $error .= " Error loading students: " . $e->getMessage();
}

// 2. Fetch all hostels
try {
    $hostels_collection = firestore_get_collection('Hostels', $idToken);
    if (!isset($hostels_collection['error'])) {
        $hostels = $hostels_collection;
    } else {
        $error .= " Warning: Could not load hostels list.";
    }
} catch (Exception $e) {
    $error .= " Error loading hostels: " . $e->getMessage();
}

// 3. Fetch all available rooms
try {
    $rooms_collection = firestore_get_collection('Rooms', $idToken);
    if (!isset($rooms_collection['error'])) {
        foreach ($rooms_collection as $doc) {
            if (strtolower($doc['status'] ?? '') === 'available') {
                $rooms[] = $doc;
            }
        }
    } else {
        $error .= " Warning: Could not load rooms list.";
    }
} catch (Exception $e) {
    $error .= " Error loading rooms: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentUserID = $_POST['studentUserID'] ?? '';
    $hostelID = $_POST['hostelID'] ?? '';
    $roomID = $_POST['roomID'] ?? '';

    if (empty($studentUserID) || empty($hostelID) || empty($roomID)) {
        $error = "Please select a student, hostel, and room.";
    } else {
        // Update student's record
        $studentUpdateRes = firestore_set('Users', $studentUserID, [
            'hostelID' => $hostelID,
            'roomID' => $roomID
        ], $idToken);

        // Update room's status to 'Occupied'
        $roomUpdateRes = firestore_set('Rooms', $roomID, ['status' => 'Occupied'], $idToken);

        if (isset($studentUpdateRes['error']) || isset($roomUpdateRes['error'])) {
            $error = "Failed to complete assignment. Student update error: " . ($studentUpdateRes['error']['message'] ?? 'OK') . ". Room update error: " . ($roomUpdateRes['error']['message'] ?? 'OK');
        } else {
            $message = "Student successfully assigned to room!";
            echo "<script>window.successMessage = true;</script>";
            // Refresh data to remove assigned student and room from lists
            header("Location: ".$_SERVER['PHP_SELF']); // Redirect to clear POST and refresh data
            exit;
        }
    }
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/form_style.css">
<link rel="stylesheet" href="../../assets/popup_style.css">

<div class="form-wrapper">
    <div class="form-header">
        <a href="dashboard.php" class="back-icon-link" title="Back to Dashboard"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Assign Student to Room</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars(trim($error)) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="studentUserID">Select Student <span style="color:red">*</span>:</label>
        <select id="studentUserID" name="studentUserID" required>
            <option value="">-- Select an Unassigned Student --</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= htmlspecialchars($student['userID']) ?>">
                    <?= htmlspecialchars($student['name']) ?> (<?= htmlspecialchars($student['studentID']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="hostelID">Select Hostel <span style="color:red">*</span>:</label>
        <select id="hostelID" name="hostelID" required>
            <option value="">-- Select a Hostel --</option>
            <?php foreach ($hostels as $hostel): ?>
                <option value="<?= htmlspecialchars($hostel['hostelID']) ?>">
                    <?= htmlspecialchars($hostel['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="roomID">Select Room <span style="color:red">*</span>:</label>
        <select id="roomID" name="roomID" required disabled>
            <option value="">-- First Select a Hostel --</option>
        </select>

        <br><br>
        <button type="submit" class="btn-submit">Assign Student</button>
    </form>
</div>

<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Assignment Successful</h3>
        <p>The student has been assigned to the selected room.</p>
        <button id="okBtn">Okay</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const allRooms = <?= json_encode($rooms) ?>;
    const hostelSelect = document.getElementById('hostelID');
    const roomSelect = document.getElementById('roomID');

    hostelSelect.addEventListener('change', function() {
        const selectedHostelID = this.value;
        roomSelect.innerHTML = '<option value="">-- Select an Available Room --</option>';
        roomSelect.disabled = true;

        if (selectedHostelID) {
            const filteredRooms = allRooms.filter(room => room.hostelID === selectedHostelID);
            if (filteredRooms.length > 0) {
                filteredRooms.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.roomID;
                    option.textContent = `Room ${room.roomNumber} (${room.roomType})`;
                    roomSelect.appendChild(option);
                });
                roomSelect.disabled = false;
            } else {
                roomSelect.innerHTML = '<option value="">-- No Available Rooms in this Hostel --</option>';
            }
        }
    });

    if (window.successMessage) {
        const popup = document.getElementById("successPopup");
        popup.style.display = "flex";
        document.getElementById("okBtn").addEventListener("click", function() {
            popup.style.display = "none";
            window.location.href = "assign_student_room.php";
        });
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>

