<?php
$page_title = "Manage Students";
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



$warden = $_SESSION['user'];
$wardenHostelID = $warden['hostelID'] ?? null;
$idToken = $_SESSION['idToken'];

$error = '';
$students = [];
$hostel_map = [];
$room_map = [];

if (empty($wardenHostelID)) {
    $error = "You are not assigned to a hostel and cannot view student data.";
} else {
    try {
        // Step 1: Fetch all users and filter for students in the warden's hostel
        $users_collection = firestore_get_collection('Users', $idToken);
        if (!isset($users_collection['error'])) {
            foreach ($users_collection as $doc) {
                if (($doc['role'] ?? '') === 'student' && ($doc['hostelID'] ?? '') === $wardenHostelID) {
                    $students[] = $doc;
                }
            }
        } else {
            $error = "Failed to load student data.";
        }

        // Step 2: Fetch all hostels to map hostelID to hostelName
        $hostels_collection = firestore_get_collection('Hostels', $idToken);
        if (!isset($hostels_collection['error'])) {
            foreach ($hostels_collection as $hostel) {
                $hostel_map[$hostel['hostelID']] = $hostel['name'];
            }
        }

        // Step 3: Fetch all rooms to map roomID to roomNumber
        $rooms_collection = firestore_get_collection('Rooms', $idToken);
        if (!isset($rooms_collection['error'])) {
            foreach ($rooms_collection as $room) {
                $room_map[$room['roomID']] = $room['roomNumber'];
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred while loading data: " . $e->getMessage();
    }
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">

<h2>Student Management</h2>
<p>Viewing students assigned to your hostel.</p>

<div class="table-container">
    <div class="table-actions">
        <div class="search-wrapper">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by name, email, student ID...">
        </div>
    </div>

    <?php if ($error): ?>
        <div class="message error" style="margin-bottom: 15px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <table id="studentTable">
        <thead>
            <tr>
                <th data-col="0">Name</th>
                <th data-col="1">Student ID</th>
                <th data-col="2">Email</th>
                <th data-col="3">Hostel</th>
                <th data-col="4">Room</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($students) > 0): ?>
                <?php foreach ($students as $student): ?>
                    <?php
                        $hostel_id = $student['hostelID'] ?? '';
                        $hostel_name = $hostel_map[$hostel_id] ?? 'N/A';
                        $room_id = $student['roomID'] ?? '';
                        $room_number = $room_map[$room_id] ?? 'N/A';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($student['name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($student['studentID'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($student['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($hostel_name) ?></td>
                        <td><?= htmlspecialchars($room_number) ?></td>
                        <td class="action-icons">
                            <a href="student_view.php?id=<?= urlencode($student['userID']) ?>" title="View">
                                <i class="fa-solid fa-eye view"></i>
                            </a>
                            <a href="student_update.php?id=<?= urlencode($student['userID']) ?>" title="Edit">
                                <i class="fa-solid fa-pen-to-square edit"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; color:#ccc;">No students found in your hostel</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Search filter
document.getElementById("searchInput").addEventListener("keyup", function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll("#studentTable tbody tr");
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? "" : "none";
    });
});

// Sorting by header
document.querySelectorAll("#studentTable th[data-col]").forEach(th => {
    th.addEventListener("click", () => {
        const table = document.getElementById("studentTable");
        const tbody = table.querySelector("tbody");
        const index = th.getAttribute("data-col");
        const rows = Array.from(tbody.querySelectorAll("tr"));
        const isAsc = th.classList.toggle("asc");

        // Clear sorting indicators from other headers
        document.querySelectorAll("#studentTable th[data-col]").forEach(header => {
            if (header !== th) header.classList.remove('asc');
        });

        rows.sort((a, b) => {
            const aText = a.children[index].textContent.trim().toLowerCase();
            const bText = b.children[index].textContent.trim().toLowerCase();
            return isAsc ? aText.localeCompare(bText) : bText.localeCompare(aText);
        });

        tbody.innerHTML = "";
        rows.forEach(r => tbody.appendChild(r));
    });
});
</script>

<?php include('../../inc/footer_private.php'); ?>
