<?php
$page_title = "Manage Maintenance Staff";
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


$error = '';
$staff_members = [];

// 🟣 Fetch all maintenance staff from Firestore
try {
    $collection = firestore_get_collection('Users', $_SESSION['idToken']);
    if (!isset($collection['error'])) {
        foreach ($collection as $doc) {
            if (($doc['role'] ?? '') === 'maintenance_staff') {
                $staff_members[] = $doc;
            }
        }
    } else {
        // The error from firestore_get_collection might be a string or an array.
        // Check if it's an array with a 'message' key before accessing it.
        $errorMessage = is_array($collection['error']) && isset($collection['error']['message'])
            ? $collection['error']['message']
            : 'An unknown error occurred.';
        $error = "Failed to load maintenance staff: " . $errorMessage;
    }
} catch (Exception $e) {
    $error = "Error loading maintenance staff: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">
<link rel="stylesheet" href="../../assets/popup_style.css">
<h2>Maintenance Staff Management</h2>

<div class="table-container">
    <div class="table-actions">
        <div class="search-wrapper">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search Maintenance Staff...">
        </div>
        <a href="staff_add.php"><i class="fa-solid fa-plus"></i> Add Maintenance Staff</a>
    </div>

    <?php if ($error): ?>
        <div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:8px; margin-bottom:10px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <table id="staffTable">
        <thead>
            <tr>
                <th data-col="0">Staff ID</th>
                <th data-col="1">Name</th>
                <th data-col="2">Email</th>
                <th data-col="3">Staff Role</th>
                <th data-col="4">Status</th>
                <th data-col="5">Assigned Hostel</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($staff_members) > 0): ?>
                <?php foreach ($staff_members as $staff): ?>
                    <tr>
                        <td><?= htmlspecialchars($staff['staffID'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($staff['name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($staff['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($staff['staffRole'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($staff['status'] ?? 'Inactive') ?></td>
                        <td><?= htmlspecialchars($staff['hostelID'] ?? 'N/A') ?></td>
                        <td class="action-icons">
                            <a href="staff_view.php?id=<?= urlencode($staff['userID']) ?>" title="View">
                                <i class="fa-solid fa-eye view"></i>
                            </a>
                            <a href="staff_update.php?id=<?= urlencode($staff['userID']) ?>" title="Edit">
                                <i class="fa-solid fa-pen-to-square edit"></i>
                            </a>
                            <a href="#" onclick="openDeletePopup('<?= $staff['userID'] ?>')" title="Delete">
                                <i class="fa-solid fa-trash delete"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; color:#ccc;">No maintenance staff found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 🟣 Custom confirmation popup -->
<div id="confirmPopup" class="popup-overlay" style="display:none;">
    <div class="popup-box">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this maintenance staff member?</p>
        <div class="popup-buttons">
            <button id="confirmDeleteBtn" class="btn-confirm">Yes, Delete</button>
            <button id="cancelDeleteBtn" class="btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<script>
// 🔍 Search filter
document.getElementById("searchInput").addEventListener("keyup", function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll("#staffTable tbody tr");
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? "" : "none";
    });
});

// ↕️ Sorting by header
document.querySelectorAll("#staffTable th[data-col]").forEach(th => {
    th.addEventListener("click", () => {
        const table = document.getElementById("staffTable");
        const tbody = table.querySelector("tbody");
        const index = th.getAttribute("data-col");
        const rows = Array.from(tbody.querySelectorAll("tr"));
        const isAsc = th.classList.toggle("asc");

        rows.sort((a, b) => {
            const aText = a.children[index].textContent.trim().toLowerCase();
            const bText = b.children[index].textContent.trim().toLowerCase();
            return isAsc ? aText.localeCompare(bText) : bText.localeCompare(aText);
        });

        tbody.innerHTML = "";
        rows.forEach(r => tbody.appendChild(r));
    });
});

// 🗑️ Custom delete confirmation popup
let deleteStaffID = null;

function openDeletePopup(userID) {
    deleteStaffID = userID;
    document.getElementById("confirmPopup").style.display = "flex";
}

document.getElementById("cancelDeleteBtn").addEventListener("click", () => {
    document.getElementById("confirmPopup").style.display = "none";
    deleteStaffID = null;
});

document.getElementById("confirmDeleteBtn").addEventListener("click", () => {
    if (deleteStaffID) {
        window.location.href = "staff_delete.php?id=" + encodeURIComponent(deleteStaffID);
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
