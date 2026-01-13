<?php
$page_title = "Pending Tasks";

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
$pendingTasks = [];

$staff = $_SESSION['user'] ?? null;
$idToken = $_SESSION['idToken'] ?? '';

if (!$staff || empty($staff['userID'])) {
    $error = "Unable to identify logged-in staff.";
}

/* ===============================
   FLEXIBLE PENDING STATUS CHECK
   =============================== */
function isPendingStatus($rawStatus) {
    $status = strtolower(trim($rawStatus));

    return in_array($status, [
        'pending',
        'in progress',
        'work order created',
        'assigned',
        'processing',
        '' // empty = still pending
    ]);
}

/* ===============================
   FETCH & PROCESS TASKS
   =============================== */
if (!$error) {
    try {
        $workOrders = firestore_get_collection('WorkOrders', $idToken);
        $complaints = firestore_get_collection('Complaints', $idToken);

        if (isset($workOrders['error']) || isset($complaints['error'])) {
            $error = 'Unable to load tasks.';
        } else {

            // Map complaints by ID
            $complaintMap = [];
            foreach ($complaints as $c) {
                if (isset($c['id'])) {
                    $complaintMap[$c['id']] = $c;
                }
            }

            // Filter pending tasks for THIS staff
            foreach ($workOrders as $wo) {

                // ✅ Correct staff match
                if (($wo['mainStaffID'] ?? '') !== $staff['userID']) continue;

                // ✅ Flexible pending check
                if (!isPendingStatus($wo['resolutionStatus'] ?? '')) continue;

                $cid = $wo['complaintID'] ?? null;
                if (!$cid || !isset($complaintMap[$cid])) continue;

                $pendingTasks[] = [$wo, $complaintMap[$cid]];
            }
        }
    } catch (Exception $e) {
        $error = 'Error loading pending tasks.';
    }
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">

<style>
    /* ===== Action Buttons ===== */
.btn-view {
    background: #6a1b9a;          /* purple */
    color: #fff;
    border: none;
}
.view-btn {
    background: #6a1b9a;          /* purple */
     color:white;
     padding:6px 14px;
     border-radius:12px;
}

</style>
<h2 style="color:#4b2aad; font-weight:800;">Pending Tasks</h2>

<?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div style="
    border:2px solid #ff3b3b;
    border-radius:12px;
    padding:15px;
    margin-top:20px;
    background:white;
">

<table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr style="color:black; text-align:left;">
            <th style="padding:10px;">Location</th>
            <th style="padding:10px;">Description</th>
            <th style="padding:10px;">Severity</th>
            <th style="padding:10px;">Proof</th>
            <th style="padding:10px;">Status</th>
            <th style="padding:10px;">Remarks</th>
            <th style="padding:10px;">Action</th>
        </tr>
    </thead>

    <tbody>
    <?php if (!empty($pendingTasks)): ?>
        <?php foreach ($pendingTasks as [$wo, $c]): ?>
        <tr>

            <td style="padding:12px;">
                <?= htmlspecialchars($c['hostelID']) ?> – Room <?= htmlspecialchars($c['roomNumber']) ?>
            </td>

            <td style="padding:12px;">
                <?= htmlspecialchars($c['description']) ?>
            </td>

            <td style="padding:12px; font-weight:600;
                color:<?= $c['severity'] === 'high' ? '#d32f2f' :
                       ($c['severity'] === 'medium' ? '#f2b100' : '#0288d1') ?>;">
                <?= ucfirst(htmlspecialchars($c['severity'])) ?>
            </td>

            <td style="padding:12px;">
                <?php if (!empty($c['imagePath'])): ?>
                    <a href="<?= htmlspecialchars($c['imagePath']) ?>" target="_blank">
                        <button class="view-btn">View Image</button>
                    </a>
                <?php else: ?>
                    <span style="color:#aaa;">No Image</span>
                <?php endif; ?>
            </td>

            <td style="padding:12px;">
                <span style="
                    background:#f16419ff;
                    color:white;
                    padding:6px 14px;
                    border-radius:12px;
                ">
                    <?= ucwords($wo['resolutionStatus'] ?: 'Pending') ?>
                </span>
            </td>

            <td style="padding:12px;">
                <?= htmlspecialchars($wo['remarks'] ?? '-') ?>
            </td>

            <td style="padding:12px;">
                <a href="task_details.php?cid=<?= urlencode($wo['complaintID']) ?>">
                    <button class="view-btn">Mark as Resolve</button>
                </a>
            </td>

        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="7" style="text-align:center; color:#aaa; padding:20px;">
                No pending tasks assigned to you.
            </td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php include('../../inc/footer_private.php'); ?>