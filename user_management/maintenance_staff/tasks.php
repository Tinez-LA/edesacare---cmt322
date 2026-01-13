<?php
$page_title = "My Tasks";

include_once('../../inc/auth_check.php');
require_role('maintenance_staff');
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


/* ===============================
   INIT
   =============================== */
$error = '';
$success = '';
$tasks = [];

$staff = $_SESSION['user'] ?? null;
$idToken = $_SESSION['idToken'] ?? '';

if (!$staff || empty($staff['userID'])) {
    $error = "Unable to identify logged-in staff.";
}

/* ===============================
   HANDLE STATUS UPDATE
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wid'], $_POST['status']) && !$error) {

    $wid = $_POST['wid'];
    $status = strtolower(trim($_POST['status']));

    if ($status === 'in progress') {
        $status = 'pending';
    }

    $update = firestore_set(
        'WorkOrders',
        $wid,
        ['resolutionStatus' => $status],
        $idToken
    );

    if (!isset($update['error'])) {
        $success = "Task status updated successfully.";
    } else {
        $error = "Failed to update task status.";
    }
}

/* ===============================
   LOAD TASKS
   =============================== */
if (!$error) {
    try {
        $workOrders = firestore_get_collection('WorkOrders', $idToken);
        $complaints = firestore_get_collection('Complaints', $idToken);

        if (isset($workOrders['error']) || isset($complaints['error'])) {
            $error = "Could not load task data.";
        } else {

            // Map complaints by ID
            $complaintMap = [];
            foreach ($complaints as $c) {
                if (isset($c['id'])) {
                    $complaintMap[$c['id']] = $c;
                }
            }

            foreach ($workOrders as $wo) {

                // ONLY this staff's tasks
                if (($wo['mainStaffID'] ?? '') !== $staff['userID']) continue;

                $cid = $wo['complaintID'] ?? null;
                if (!$cid || !isset($complaintMap[$cid])) continue;

                $task = $complaintMap[$cid];
                $task['workOrderID'] = $wo['id'];
                $task['resolutionStatus'] = $wo['resolutionStatus'] ?? '';

                $tasks[] = $task;
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred while loading tasks.";
    }
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">

<div class="dashboard-header">
    <h2>My Tasks</h2>
    <p>View and manage your assigned maintenance tasks.</p>
</div>

<?php if ($error): ?>
    <div class="message error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="message success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (!empty($tasks)): ?>
<div class="table-container">
<table class="data-table">
    <thead>
        <tr>
            <th>Room</th>
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
    <?php foreach ($tasks as $task): ?>
        <tr>
            <td><?= htmlspecialchars($task['roomNumber'] ?? '') ?></td>
            <td><?= htmlspecialchars($task['location'] ?? '') ?></td>
            <td><?= htmlspecialchars($task['type'] ?? '') ?></td>
            <td><?= htmlspecialchars($task['description'] ?? '') ?></td>
            <td><?= ucfirst(htmlspecialchars($task['severity'] ?? '')) ?></td>

            <td>
                <form method="post">
                    <input type="hidden" name="wid" value="<?= htmlspecialchars($task['workOrderID']) ?>">
                    <select name="status" onchange="this.form.submit()">
                        <option value="pending" <?= ($task['resolutionStatus'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="in progress" <?= ($task['resolutionStatus'] === '') ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= ($task['resolutionStatus'] === 'completed') ? 'selected' : '' ?>>Completed</option>
                    </select>
                </form>
            </td>

            <td><?= htmlspecialchars($task['dateSubmitted'] ?? '') ?></td>

            <td>
                <a href="task_details.php?cid=<?= urlencode($task['id']) ?>">
                    <button class="action-button">View</button>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php else: ?>
    <p>No tasks assigned to you.</p>
<?php endif; ?>

<?php include('../../inc/footer_private.php'); ?>
