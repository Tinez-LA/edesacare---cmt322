<?php
$page_title = "Manage Hostels";
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
$hostels = [];
$warden_map = []; // To map Warden ID to Warden Name

// 🟣 Step 1: Fetch all users to create a warden map (ID -> Name)
try {
    $users_collection = firestore_get_collection('Users', $_SESSION['idToken']);
    if (!isset($users_collection['error'])) {
        foreach ($users_collection as $doc) {
            if (($doc['role'] ?? '') === 'warden') {
                $warden_map[$doc['userID']] = $doc['name'];
            }
        }
    } else {
        $error = "Warning: Could not load warden names for mapping.";
    }
} catch (Exception $e) {
    $error = "Error loading user data: " . $e->getMessage();
}

// 🟣 Step 2: Fetch all hostels from Firestore
try {
    $hostels_collection = firestore_get_collection('Hostels', $_SESSION['idToken']);
    if (!isset($hostels_collection['error'])) {
        $hostels = $hostels_collection;
    } else {
        $errorMessage = is_array($hostels_collection['error']) && isset($hostels_collection['error']['message'])
            ? $hostels_collection['error']['message']
            : 'An unknown error occurred.';
        $error .= " Failed to load hostels: " . $errorMessage;
    }
} catch (Exception $e) {
    $error .= " Error loading hostels: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">
<link rel="stylesheet" href="../../assets/popup_style.css">
<h2>Hostel Management</h2>

<div class="table-container">
    <div class="table-actions">
        <div class="search-wrapper">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search hostels...">
        </div>
        <a href="hostel_add.php"><i class="fa-solid fa-plus"></i> Add Hostel</a>
    </div>

    <?php if ($error): ?>
        <div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:8px; margin-bottom:10px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <table id="hostelTable">
        <thead>
            <tr>
                <th data-col="0">Hostel ID</th>
                <th data-col="1">Name</th>
                <th data-col="2">Warden</th>
                <th data-col="3">Location</th>
                <th data-col="4">Type</th>
                <th data-col="5">Total Rooms</th>
                <th data-col="6">Capacity</th>
                <th data-col="7">Total Floors</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($hostels) > 0): ?>
                <?php foreach ($hostels as $hostel): ?>
                    <?php
                        $warden_id = $hostel['wardenID'] ?? '';
                        $warden_name = isset($warden_map[$warden_id]) ? $warden_map[$warden_id] : 'Not Assigned';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($hostel['hostelID'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($hostel['name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($warden_name) ?></td>
                        <td><?= htmlspecialchars($hostel['location'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($hostel['hostelType'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($hostel['totalRooms'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($hostel['capacity'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($hostel['totalFloors'] ?? '-') ?></td>
                        <td class="action-icons">
                            <a href="hostel_view.php?id=<?= urlencode($hostel['hostelID']) ?>" title="View">
                                <i class="fa-solid fa-eye view"></i>
                            </a>
                            <a href="hostel_update.php?id=<?= urlencode($hostel['hostelID']) ?>" title="Edit">
                                <i class="fa-solid fa-pen-to-square edit"></i>
                            </a>
                            <a href="#" onclick="openDeletePopup('<?= $hostel['hostelID'] ?>')" title="Delete">
                                <i class="fa-solid fa-trash delete"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9" style="text-align:center; color:#ccc;">No hostels found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 🟣 Custom confirmation popup -->
<div id="confirmPopup" class="popup-overlay" style="display:none;">
    <div class="popup-box">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this hostel? This action cannot be undone.</p>
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
    const rows = document.querySelectorAll("#hostelTable tbody tr");
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? "" : "none";
    });
});

// ↕️ Sorting by header
document.querySelectorAll("#hostelTable th[data-col]").forEach(th => {
    th.addEventListener("click", () => {
        const table = document.getElementById("hostelTable");
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
let deleteHostelID = null;

function openDeletePopup(hostelID) {
    deleteHostelID = hostelID;
    document.getElementById("confirmPopup").style.display = "flex";
}

document.getElementById("cancelDeleteBtn").addEventListener("click", () => {
    document.getElementById("confirmPopup").style.display = "none";
    deleteHostelID = null;
});

document.getElementById("confirmDeleteBtn").addEventListener("click", () => {
    if (deleteHostelID) {
        window.location.href = "hostel_delete.php?id=" + encodeURIComponent(deleteHostelID);
    }
});
</script>

<?php include('../../inc/footer_private.php'); ?>
