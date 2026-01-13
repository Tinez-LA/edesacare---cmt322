<?php
$page_title = "Work Orders";
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
$wardenUserID = $warden['userID'];
$idToken = $_SESSION['idToken'];

$hostelID = $warden['hostelID'] ?? '';
$workOrders = [];
$error = '';

try {
    if (!empty($hostelID)) {
        // Fetch work orders - we'll need to get all work orders and filter by hostel via complaint
        $workorders_collection = firestore_get_collection('WorkOrders', $idToken);
        if (!isset($workorders_collection['error'])) {
            foreach ($workorders_collection as $workOrder) {
                $complaintID = $workOrder['complaintID'] ?? '';
                if (!empty($complaintID)) {
                    // Fetch complaint to check hostel
                    $complaintDoc = firestore_get('Complaints', $complaintID, $idToken);
                    if (isset($complaintDoc['fields']['hostelID'])) {
                        $complaintHostelID = reset($complaintDoc['fields']['hostelID']);
                        if ($complaintHostelID === $hostelID) {
                            // This work order belongs to warden's hostel
                            $workOrder['complaint'] = [];
                            foreach ($complaintDoc['fields'] as $key => $value) {
                                $workOrder['complaint'][$key] = reset($value);
                            }

                            // Fetch student name
                            $studentUserID = $workOrder['complaint']['studentUserID'] ?? '';
                            $studentName = 'Unknown';
                            if (!empty($studentUserID)) {
                                $studentDoc = firestore_get('Users', $studentUserID, $idToken);
                                if (isset($studentDoc['fields']['name'])) {
                                    $studentName = reset($studentDoc['fields']['name']);
                                }
                            }
                            $workOrder['studentName'] = $studentName;

                            // Fetch staff name
                            $staffID = $workOrder['mainStaffID'] ?? '';
                            $staffName = 'Unassigned';
                            if (!empty($staffID)) {
                                $staffDoc = firestore_get('Users', $staffID, $idToken);
                                if (isset($staffDoc['fields']['name'])) {
                                    $staffName = reset($staffDoc['fields']['name']);
                                }
                            }
                            $workOrder['staffName'] = $staffName;

                            // Fetch hostel and room for location
                            $hostelName = '';
                            $roomNumber = '';
                            $hostelID_from_complaint = $workOrder['complaint']['hostelID'] ?? '';
                            if (!empty($hostelID_from_complaint)) {
                                $hostelDoc = firestore_get('Hostels', $hostelID_from_complaint, $idToken);
                                if (isset($hostelDoc['fields']['name'])) {
                                    $hostelName = reset($hostelDoc['fields']['name']);
                                }
                            }
                            $roomID = $workOrder['complaint']['roomID'] ?? '';
                            if (!empty($roomID)) {
                                $roomDoc = firestore_get('Rooms', $roomID, $idToken);
                                if (isset($roomDoc['fields']['roomNumber'])) {
                                    $roomNumber = reset($roomDoc['fields']['roomNumber']);
                                }
                            }
                            $workOrder['location'] = $hostelName . ' - Room ' . $roomNumber;

                            $workOrders[] = $workOrder;
                        }
                    }
                }
            }
        } else {
            $error = "Could not load work orders.";
        }
    } else {
        $error = "Warden is not assigned to any hostel.";
    }
} catch (Exception $e) {
    $error = "An error occurred while loading work orders: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">

<div class="dashboard-header">
    <h2>Work Orders</h2>
    <p>View and manage work orders for your hostel.</p>
</div>

<?php if ($error): ?>
    <div class="message error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($workOrders)): ?>
    <div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Location</th>
                <th>Description</th>
                <th>Assigned Staff</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($workOrders as $workOrder): ?>
                <tr>
                    <td><?= htmlspecialchars($workOrder['location']) ?></td>
                    <td><?= htmlspecialchars($workOrder['complaint']['description'] ?? '') ?></td>
                    <td><?= htmlspecialchars($workOrder['staffName']) ?></td>
                    <td>
                        <?php
                        $resolutionStatus = $workOrder['resolutionStatus'] ?? false;
                        echo $resolutionStatus ? 'Resolved' : 'Pending';
                        ?>
                    </td>
                    <td><?= htmlspecialchars($workOrder['createAt'] ?? '') ?></td>
                    <td>
                        <button class="action-button" onclick="alert('View details for Work Order: <?= htmlspecialchars($workOrder['id'] ?? '') ?>')">View</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <p>No work orders found for your hostel.</p>
<?php endif; ?>

<?php include('../../inc/footer_private.php'); ?>
