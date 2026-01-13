<?php
$page_title = "Manage Wardens";
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
$wardens = [];
$hostel_map = []; // To map Hostel ID to Hostel Name

// 🟣 Step 1: Fetch all hostels to create a map (ID -> Name)
try {
    $hostels_collection = firestore_get_collection('Hostels', $_SESSION['idToken']);
    if (!isset($hostels_collection['error'])) {
        foreach ($hostels_collection as $doc) {
            $hostel_map[$doc['hostelID']] = $doc['name'];
        }
    } else {
        $error = "Warning: Could not load hostel names for mapping.";
    }
} catch (Exception $e) {
    $error = "Error loading hostel data: " . $e->getMessage();
}

// 🟣 Fetch all wardens from Firestore
try {
    $collection = firestore_get_collection('Users', $_SESSION['idToken']);
    if (!isset($collection['error'])) {
        foreach ($collection as $doc) {
            if (($doc['role'] ?? '') === 'warden') {
                $wardens[] = $doc;
            }
        }
    } else {
        // The error from firestore_get_collection might be a string or an array.
        // Check if it's an array with a 'message' key before accessing it.
        $errorMessage = is_array($collection['error']) && isset($collection['error']['message'])
            ? $collection['error']['message']
            : 'An unknown error occurred.';
        $error = "Failed to load wardens: " . $errorMessage;
    }
} catch (Exception $e) {
    $error = "Error loading wardens: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">
<link rel="stylesheet" href="../../assets/popup_style.css">
<h2>Warden Management</h2>

<div class="table-container">
    <div class="table-actions">
        <div class="search-wrapper">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search wardens...">
        </div>
        <a href="warden_add.php"><i class="fa-solid fa-plus"></i> Add Warden</a>
    </div>

    <?php if ($error): ?>
        <div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:8px; margin-bottom:10px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <table id="wardenTable">
        <thead>
            <tr>
                <th data-col="0">Name</th>
                <th data-col="1">Email</th>
                <th data-col="2">IC Number</th>
                <th data-col="3">Contact No</th>
                <th data-col="4">Hostel</th>
                <th data-col="5">Change Pwd Count</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($wardens) > 0): ?>
                <?php foreach ($wardens as $warden): ?>
                    <?php
                        $hostel_id = $warden['hostelID'] ?? '';
                        $hostel_name = isset($hostel_map[$hostel_id]) ? $hostel_map[$hostel_id] : 'Not Assigned';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($warden['name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($warden['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($warden['icNumber'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($warden['contactNo'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($hostel_name) ?></td>
                        <td><?= htmlspecialchars($warden['changepasswordcount'] ?? 0) ?></td>
                        <td class="action-icons">
                            <a href="warden_view.php?id=<?= urlencode($warden['userID']) ?>" title="View">
                                <i class="fa-solid fa-eye view"></i>
                            </a>
                            <a href="warden_update.php?id=<?= urlencode($warden['userID']) ?>" title="Edit">
                                <i class="fa-solid fa-pen-to-square edit"></i>
                            </a>
                            <a href="#" onclick="openDeletePopup('<?= $warden['userID'] ?>')" title="Delete">
                                <i class="fa-solid fa-trash delete"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; color:#ccc;">No wardens found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 🟣 Custom confirmation popup -->
<div id="confirmPopup" class="popup-overlay" style="display:none;">
    <div class="popup-box">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this warden?</p>
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
    const rows = document.querySelectorAll("#wardenTable tbody tr");
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? "" : "none";
    });
});

// ↕️ Sorting by header
document.querySelectorAll("#wardenTable th[data-col]").forEach(th => {
    th.addEventListener("click", () => {
        const table = document.getElementById("wardenTable");
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
let deleteUserID = null;

function openDeletePopup(userID) {
    deleteUserID = userID;
    document.getElementById("confirmPopup").style.display = "flex";
}

document.getElementById("cancelDeleteBtn").addEventListener("click", () => {
    document.getElementById("confirmPopup").style.display = "none";
    deleteUserID = null;
});

document.getElementById("confirmDeleteBtn").addEventListener("click", () => {
    if (deleteUserID) {
        window.location.href = "warden_delete.php?id=" + encodeURIComponent(deleteUserID);
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
