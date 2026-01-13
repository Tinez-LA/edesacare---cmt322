<?php
$page_title = "Maintenance Reports";
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
$complaints = [];

$error = '';
$success = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaintID']) && isset($_POST['status'])) {
    $complaintID = $_POST['complaintID'];
    $newStatus = $_POST['status'];

    try {
        $updateData = [
            'status' => $newStatus
        ];
        firestore_set('Complaints', $complaintID, $updateData, $idToken);
        $success = "Complaint status updated successfully.";
    } catch (Exception $e) {
        $error = "Failed to update complaint status: " . $e->getMessage();
    }
}



try {
    if (!empty($hostelID)) {
        // Fetch complaints for the warden's hostel
        $complaints_collection = firestore_get_collection('Complaints', $idToken);
        if (!isset($complaints_collection['error'])) {
            foreach ($complaints_collection as $complaint) {
                if (($complaint['hostelID'] ?? '') === $hostelID) {
                    // Fetch student name
                    $studentUserID = $complaint['studentUserID'] ?? '';
                    $studentName = 'Unknown';
                    if (!empty($studentUserID)) {
                        $studentDoc = firestore_get('Users', $studentUserID, $idToken);
                        if (isset($studentDoc['fields']['name'])) {
                            $studentName = reset($studentDoc['fields']['name']);
                        }
                    }
                    $complaint['studentName'] = $studentName;

                    // Fetch assigned staff name
                    $assignedStaffID = $complaint['assignedStaffID'] ?? '';
                    $assignedStaffName = '';
                    if (!empty($assignedStaffID)) {
                        $staffDoc = firestore_get('Users', $assignedStaffID, $idToken);
                        if (isset($staffDoc['fields']['name'])) {
                            $assignedStaffName = reset($staffDoc['fields']['name']);
                        }
                    }
                    $complaint['assignedStaffName'] = $assignedStaffName;

                    $complaints[] = $complaint;
                }
            }
        } else {
            $error = "Could not load complaint data.";
        }
    } else {
        $error = "Warden is not assigned to any hostel.";
    }
} catch (Exception $e) {
    $error = "An error occurred while loading complaints: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">

<div class="dashboard-header">
    <h2>Maintenance Reports</h2>
    <p>View and manage complaints for your hostel.</p>
</div>

<?php if ($error): ?>
    <div class="message error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="message success" style="margin-bottom: 20px;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (!empty($complaints)): ?>
    <div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Room Number</th>
                <th>Location</th>
                <th>Type</th>
                <th>Description</th>
                <th>Severity</th>
                <th>Status</th>
                <th>Date Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($complaints as $complaint): ?>
                <tr>
                    <td><?= htmlspecialchars($complaint['studentName']) ?></td>
                    <td><?= htmlspecialchars($complaint['roomNumber'] ?? '') ?></td>
                    <td><?= htmlspecialchars($complaint['location'] ?? '') ?></td>
                    <td><?= htmlspecialchars($complaint['type'] ?? '') ?></td>
                    <td><?= htmlspecialchars($complaint['description'] ?? '') ?></td>
                    <td><?= htmlspecialchars($complaint['severity'] ?? '') ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="complaintID" value="<?= htmlspecialchars($complaint['id'] ?? '') ?>">
                            <select name="status" class="status-select" onchange="this.form.submit()">
                                <option value="pending" <?= ($complaint['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="in progress" <?= ($complaint['status'] ?? '') === 'in progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="resolved" <?= ($complaint['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            </select>
                        </form>
                    </td>
                
                    <td><?= htmlspecialchars($complaint['dateSubmitted'] ?? '') ?></td>
                    <td>
                        <!-- Additional actions if needed -->
                        <a href="workorder-create.php?complaintID=<?= htmlspecialchars($complaint['id'] ?? '') ?>" class="action-button">Create Work Order</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <p>No complaints found for your hostel.</p>
<?php endif; ?>

<?php include('../../inc/footer_private.php'); ?>
