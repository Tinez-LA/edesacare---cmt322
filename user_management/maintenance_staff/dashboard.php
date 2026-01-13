<?php
$page_title = "Maintenance Staff Dashboard";
include_once('../../inc/auth_check.php');
require_role('maintenance_staff');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

/* ===============================
   SESSION TIMEOUT
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
$staff = $_SESSION['user'];
$staffUserID = $staff['userID'];
$idToken = $_SESSION['idToken'];

$error = '';

$totalTasks = 0;
$inProgressTasks = 0;
$resolvedTasks = 0;

/* Store hostels from assigned work orders */
$assignedHostels = [];

try {

    /* ===============================
       FETCH WORK ORDERS
       =============================== */
    $workOrders = firestore_get_collection('WorkOrders', $idToken);
    if (isset($workOrders['error'])) {
        throw new Exception("Unable to load work orders.");
    }

    foreach ($workOrders as $order) {

        if (($order['mainStaffID'] ?? '') !== $staffUserID) continue;

        $totalTasks++;

        /* -------- STATUS -------- */
        $status = strtolower(trim($order['resolutionStatus'] ?? ''));
        if ($status === 'resolved') {
            $resolvedTasks++;
        } else {
            $inProgressTasks++;
        }

        /* -------- HOSTEL (via Complaint) -------- */
        if (!empty($order['complaintID'])) {
            $complaintDoc = firestore_get('Complaints', $order['complaintID'], $idToken);

            if (isset($complaintDoc['fields']['hostelID'])) {
                $hostelID = reset($complaintDoc['fields']['hostelID']);

                $hostelDoc = firestore_get('Hostels', $hostelID, $idToken);
                if (isset($hostelDoc['fields']['name'])) {
                    $assignedHostels[$hostelID] = reset($hostelDoc['fields']['name']);
                }
            }
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

/* Display logic */
$hostelDisplay = empty($assignedHostels)
    ? 'Not Assigned'
    : implode(', ', $assignedHostels);
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/dashboard_style.css">

<div class="dashboard-header">
    <h2>Welcome, <?= htmlspecialchars($staff['name']) ?>!</h2>
    <p>Here's a summary of your assigned maintenance tasks.</p>
</div>

<?php if ($error): ?>
    <div class="message error" style="margin-bottom:20px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<h3 class="dashboard-section-title">
    <i class="fa-solid fa-list-check"></i> My Task Overview
</h3>

<div class="dashboard-grid">

    <div class="dashboard-card card-complaints">
        <div class="card-icon"><i class="fa-solid fa-flag"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $totalTasks ?></p>
            <p class="card-title">Total Assigned Tasks</p>
        </div>
    </div>

    <div class="dashboard-card card-progress">
        <div class="card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $inProgressTasks ?></p>
            <p class="card-title">Tasks In Progress</p>
        </div>
    </div>

    <div class="dashboard-card card-resolved">
        <div class="card-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $resolvedTasks ?></p>
            <p class="card-title">Tasks Resolved</p>
        </div>
    </div>

    <div class="dashboard-card card-hostels">
        <div class="card-icon"><i class="fa-solid fa-building"></i></div>
        <div class="card-content">
            <p class="card-value"><?= htmlspecialchars($hostelDisplay) ?></p>
            <p class="card-title">Assigned Hostel(s)</p>
        </div>
    </div>

</div>

<h3 class="dashboard-section-title">
    <i class="fa-solid fa-bolt"></i> Quick Actions
</h3>

<div class="quick-actions">
    <a href="/user_management/maintenance_staff/tasks.php" class="action-btn">
        <i class="fa-solid fa-list-check"></i> View All My Tasks
    </a>
</div>

<?php include('../../inc/footer_private.php'); ?>

