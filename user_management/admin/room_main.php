<?php
$page_title = "Manage Rooms";
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
$rooms = [];
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

// 🟣 Step 2: Fetch all rooms from Firestore
try {
    $rooms_collection = firestore_get_collection('Rooms', $_SESSION['idToken']);
    if (!isset($rooms_collection['error'])) {
        $rooms = $rooms_collection;
    } else {
        $errorMessage = is_array($rooms_collection['error']) && isset($rooms_collection['error']['message'])
            ? $rooms_collection['error']['message']
            : 'An unknown error occurred.';
        $error .= " Failed to load rooms: " . $errorMessage;
    }
} catch (Exception $e) {
    $error .= " Error loading rooms: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">
<link rel="stylesheet" href="../../assets/popup_style.css">
<h2>Room Management</h2>

<div class="table-container">
    <div class="table-actions">
        <div class="filters-wrapper">
            <div class="search-wrapper">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search rooms...">
            </div>
            <!-- 🟣 New Status Filter Dropdown -->
            <div class="filter-select-wrapper">
                <i class="fa-solid fa-filter"></i>
                <select id="statusFilter">
                    <option value="all">All Statuses</option>
                    <option value="Available">Available</option>
                    <option value="Occupied">Occupied</option>
                    <option value="Maintenance">Maintenance</option>
                </select>
            </div>
        </div>
        <a href="room_add.php"><i class="fa-solid fa-plus"></i> Add Room</a>
    </div>

    <?php if ($error): ?>
        <div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:8px; margin-bottom:10px;">
            <?= htmlspecialchars(trim($error)) ?>
        </div>
    <?php endif; ?>

    <table id="roomTable">
        <thead>
            <tr>
                <th data-col="0">Room ID</th>
                <th data-col="1">Hostel</th>
                <th data-col="2">Room Number</th>
                <th data-col="3">Room Type</th>
                <th data-col="4">Floor</th>
                <th data-col="4">ity</th>
                <th data-col="5">Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($rooms) > 0): ?>
                <?php foreach ($rooms as $room): ?>
                    <?php
                        $hostel_id = $room['hostelID'] ?? '';
                        $hostel_name = isset($hostel_map[$hostel_id]) ? $hostel_map[$hostel_id] : 'Not Assigned';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($room['roomID'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($hostel_name) ?></td>
                        <td><?= htmlspecialchars($room['roomNumber'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($room['roomType'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($room['floor'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($room['capacity'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(ucfirst($room['status'] ?? '-')) ?></td>
                        <td class="action-icons">
                            <a href="room_view.php?id=<?= urlencode($room['roomID']) ?>" title="View">
                                <i class="fa-solid fa-eye view"></i>
                            </a>
                            <a href="room_update.php?id=<?= urlencode($room['roomID']) ?>" title="Edit">
                                <i class="fa-solid fa-pen-to-square edit"></i>
                            </a>
                            <a href="#" onclick="openDeletePopup('<?= $room['roomID'] ?>')" title="Delete">
                                <i class="fa-solid fa-trash delete"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 🟣 Custom confirmation popup -->
<div id="confirmPopup" class="popup-overlay" style="display:none;">
    <div class="popup-box">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this room? This action cannot be undone.</p>
        <div class="popup-buttons">
            <button id="confirmDeleteBtn" class="btn-confirm">Yes, Delete</button>
            <button id="cancelDeleteBtn" class="btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");
    const rows = document.querySelectorAll("#roomTable tbody tr");

    // 🟣 Combined filter function
    function applyFilters() {
        const searchValue = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value.toLowerCase();

        rows.forEach(row => {
            const textContent = row.textContent.toLowerCase();
            const statusCell = row.children[5].textContent.trim().toLowerCase(); // Column 6 is Status

            const searchMatch = textContent.includes(searchValue);
            const statusMatch = (statusValue === 'all' || statusCell === statusValue);

            if (searchMatch && statusMatch) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

    // 🟣 Add event listeners to both filters
    searchInput.addEventListener("keyup", applyFilters);
    statusFilter.addEventListener("change", applyFilters);
// ↕️ Sorting by header
document.querySelectorAll("#roomTable th[data-col]").forEach(th => {
    th.addEventListener("click", () => {
        const table = document.getElementById("roomTable");
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
let deleteRoomID = null;

function openDeletePopup(roomID) {
    deleteRoomID = roomID;
    document.getElementById("confirmPopup").style.display = "flex";
}

document.getElementById("cancelDeleteBtn").addEventListener("click", () => {
    document.getElementById("confirmPopup").style.display = "none";
    deleteRoomID = null;
});

document.getElementById("confirmDeleteBtn").addEventListener("click", () => {
    if (deleteRoomID) {
        window.location.href = "room_delete.php?id=" + encodeURIComponent(deleteRoomID);
    }
});
});
</script>

<?php include('../../inc/footer_private.php'); ?>