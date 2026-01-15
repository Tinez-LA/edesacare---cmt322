<?php
$page_title = "Update Student";
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$message = '';
$error = '';

$idToken = $_SESSION['idToken'];
$studentUserID = $_GET['id'] ?? null;

$studentData = [];
$hostels = [];
$available_rooms = [];

/* =========================
   LOAD STUDENT + DATA
   ========================= */
if (empty($studentUserID)) {
    $error = "Invalid Student User ID.";
} else {
    try {
        $doc = firestore_get('Users', $studentUserID, $idToken);
        if (!isset($doc['error']) && isset($doc['fields'])) {
            foreach ($doc['fields'] as $key => $value) {
                $studentData[$key] = reset($value);
            }

            $hostels = firestore_get_collection('Hostels', $idToken) ?? [];

            $rooms = firestore_get_collection('Rooms', $idToken);
            if (!isset($rooms['error'])) {
                foreach ($rooms as $room) {
                    if (
                        strtolower($room['status'] ?? '') === 'available' ||
                        $room['roomID'] === ($studentData['roomID'] ?? '')
                    ) {
                        $available_rooms[] = $room;
                    }
                }
            }
        } else {
            $error = "Student not found.";
        }
    } catch (Exception $e) {
        $error = "Error loading student: " . $e->getMessage();
    }
}

/* =========================
   FORM VALUES
   ========================= */
$name = $_POST['name'] ?? ($studentData['name'] ?? '');
$studentID = $_POST['studentID'] ?? ($studentData['studentID'] ?? '');
$icNum = $_POST['icNum'] ?? ($studentData['icNum'] ?? '');
$gender = $_POST['gender'] ?? ($studentData['gender'] ?? '');
$contactNo = $_POST['contactNo'] ?? ($studentData['contactNo'] ?? '');
$hostelID = $_POST['hostelID'] ?? ($studentData['hostelID'] ?? '');
$roomID = $_POST['roomID'] ?? ($studentData['roomID'] ?? '');

/* =========================
   HANDLE UPDATE
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {

    $name = trim($name);
    $studentID = trim($studentID);
    $icNum = trim($icNum);
    $gender = trim($gender);
    $contactNo = trim($contactNo);
    $hostelID = trim($hostelID);
    $roomID = trim($roomID);

    if (empty($name) || empty($studentID) || empty($icNum) || empty($gender)) {
        $error = "Please fill in all required fields.";
    } else {

        /* =========================
           🔒 FORCE IC- / TEL-
           ========================= */
        if (!str_starts_with($icNum, 'IC-')) {
            $icNum = 'IC-' . $icNum;
        }

        if (!empty($contactNo) && !str_starts_with($contactNo, 'TEL-')) {
            $contactNo = 'TEL-' . $contactNo;
        }

        /* =========================
           UPDATE FIRESTORE
           ========================= */
        $updateData = [
            'name'      => $name,
            'studentID'=> $studentID,
            'icNum'     => $icNum,
            'gender'    => $gender,
            'contactNo'=> $contactNo,
            'hostelID'  => $hostelID,
            'roomID'    => $roomID
        ];

        $res = firestore_set('Users', $studentUserID, $updateData, $idToken);

        if (isset($res['error'])) {
            $error = "Failed to update student.";
        } else {
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
        <a href="student_main.php"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Update Student Details</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

<?php if (!empty($studentData)): ?>
<form method="POST">

    <label>Student ID *</label>
    <input type="text" name="studentID" required value="<?= htmlspecialchars($studentID) ?>">

    <label>Name *</label>
    <input type="text" name="name" required value="<?= htmlspecialchars($name) ?>">

    <label>Email</label>
    <input type="email" value="<?= htmlspecialchars($studentData['email'] ?? '') ?>" readonly>

    <label>IC Number *</label>
    <input type="text" name="icNum" required value="<?= htmlspecialchars($icNum) ?>">

    <label>Gender *</label>
    <select name="gender" required>
        <option value="">-- Select --</option>
        <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
        <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
    </select>

    <label>Contact Number</label>
    <input type="text" name="contactNo" value="<?= htmlspecialchars($contactNo) ?>">

    <label>Hostel</label>
    <select name="hostelID" id="hostelID">
        <option value="">-- Select Hostel --</option>
        <?php foreach ($hostels as $hostel): ?>
            <option value="<?= $hostel['hostelID'] ?>" <?= $hostelID === $hostel['hostelID'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($hostel['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Room</label>
    <select name="roomID" id="roomID" <?= empty($hostelID) ? 'disabled' : '' ?>>
        <option value="">-- Select Room --</option>
    </select>

    <button type="submit" class="btn-submit">Update Student</button>
</form>
<?php endif; ?>
</div>

<div class="success-popup" id="successPopup">
    <div class="popup-content">
        <h3>✅ Student Updated Successfully</h3>
        <button id="okBtn">OK</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const rooms = <?= json_encode($available_rooms) ?>;
    const hostelSelect = document.getElementById("hostelID");
    const roomSelect = document.getElementById("roomID");
    const currentRoom = "<?= htmlspecialchars($roomID) ?>";

    function loadRooms() {
        roomSelect.innerHTML = '<option value="">-- Select Room --</option>';
        roomSelect.disabled = true;

        if (!hostelSelect.value) return;

        rooms.filter(r => r.hostelID === hostelSelect.value).forEach(room => {
            const opt = document.createElement("option");
            opt.value = room.roomID;
            opt.textContent = `Room ${room.roomNumber} (${room.roomType})`;
            if (room.roomID === currentRoom) opt.selected = true;
            roomSelect.appendChild(opt);
        });

        roomSelect.disabled = false;
    }

    hostelSelect.addEventListener("change", loadRooms);
    loadRooms();

    if (window.successMessage) {
        document.getElementById("successPopup").style.display = "flex";
        document.getElementById("okBtn").onclick = () => {
            window.location.href = "student_main.php";
        };
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
