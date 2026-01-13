<?php
$page_title = "Task Details";

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
$idToken = $_SESSION['idToken'] ?? '';
$staff   = $_SESSION['user'] ?? null;

$error = '';
$success = '';

$complaint = null;
$workOrder = null;

/* ===============================
   VALIDATE STAFF
   =============================== */
if (!$staff || empty($staff['userID'])) {
    $error = 'Unable to identify logged-in staff.';
}

/* ===============================
   GET COMPLAINT ID
   =============================== */
$complaintID = $_GET['cid'] ?? '';
if (empty($complaintID)) {
    $error = 'Invalid task.';
}

/* ===============================
   HANDLE FORM SUBMIT
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {

    $status  = strtolower(trim($_POST['status'] ?? ''));
    $remarks = trim($_POST['remarks'] ?? '');

    // Normalize status
    if ($status === 'in progress') {
        $status = 'pending';
    }

    $update = firestore_set(
        'WorkOrders',
        $_POST['wid'],
        [
            'resolutionStatus' => $status,
            'remarks' => $remarks
        ],
        $idToken
    );

    if (!isset($update['error'])) {
        $success = 'Task updated successfully.';
    } else {
        $error = 'Failed to update task.';
    }
}

/* ===============================
   FETCH DATA
   =============================== */
if (empty($error)) {

    $workOrders = firestore_get_collection('WorkOrders', $idToken);
    $complaints = firestore_get_collection('Complaints', $idToken);

    if (isset($workOrders['error']) || isset($complaints['error'])) {
        $error = 'Unable to load task.';
    } else {

        // Find WorkOrder
        foreach ($workOrders as $wo) {
            if (($wo['complaintID'] ?? '') === $complaintID &&
                ($wo['mainStaffID'] ?? '') === $staff['userID']) {
                $workOrder = $wo;
                break;
            }
        }

        // Find Complaint
        foreach ($complaints as $c) {
            if (($c['id'] ?? '') === $complaintID) {
                $complaint = $c;
                break;
            }
        }

        if (!$workOrder || !$complaint) {
            $error = 'Task not found.';
        }
    }
}

/* ===============================
   PRIORITY UI
   =============================== */
function priorityUI($severity){
    if ($severity === 'high')   return ['#c62828', 'Highest Priority Assigned'];
    if ($severity === 'medium') return ['#f2b100', 'Medium Priority Assigned'];
    return ['#00a0e3', 'Low Priority Assigned'];
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">

<style>
.task-wrapper{max-width:650px;}
.detail-card{border-radius:12px;padding:18px;background:#fff;}
.task-header{display:flex;gap:15px;}
.alert-icon{width:45px;height:45px;border-radius:8px;color:white;font-size:26px;display:flex;align-items:center;justify-content:center;font-weight:bold;}
.task-title{font-weight:700;font-size:15px;}
.task-priority{font-weight:600;font-size:13px;}
.task-info{margin-top:5px;font-size:13px;color:#444;}
.task-body{margin-top:15px;display:flex;gap:20px;}
.task-body img{width:130px;height:100px;border-radius:8px;border:1px solid #ddd;object-fit:cover;}
.description{margin-top:15px;}
.resolution-section{margin-top:30px;max-width:650px;}
textarea, select{width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;}
.save-btn{background:#ff7a00;color:white;border:none;padding:10px 28px;border-radius:8px;cursor:pointer;}
.success-box{background:#e8f5e9;color:#2e7d32;padding:10px;border-radius:8px;margin-bottom:15px;}
.error-box{background:#fdecea;color:#c62828;padding:10px;border-radius:8px;margin-bottom:15px;}
</style>

<h2 style="color:#4b2aad;font-weight:700;">Task Details</h2>

<?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
<?php elseif ($complaint): ?>

<?php [$color, $label] = priorityUI($complaint['severity']); ?>

<div class="task-wrapper">

    <div class="detail-card" style="border:2px solid <?= $color ?>;">
        <div class="task-header">
            <div class="alert-icon" style="background:<?= $color ?>;">!</div>
            <div>
                <div class="task-title">
                    <?= htmlspecialchars($complaint['type']) ?> –
                    <?= htmlspecialchars($complaint['hostelID']) ?> /
                    <?= htmlspecialchars($complaint['roomNumber']) ?>
                </div>
                <div class="task-priority" style="color:<?= $color ?>;"><?= $label ?></div>
                <div class="task-info">
                    <?= ucfirst($complaint['severity']) ?> /
                    <?= ucfirst($workOrder['resolutionStatus'] ?: 'assigned') ?>
                </div>
            </div>
        </div>

        <div class="task-body">
            <img src="<?= !empty($complaint['imagePath']) ? htmlspecialchars($complaint['imagePath']) : '../../assets/no-image.png' ?>">
            <div>
                <strong>Date Submitted:</strong><br>
                <?= htmlspecialchars($complaint['dateSubmitted']) ?><br><br>
                <strong>Location:</strong><br>
                <?= htmlspecialchars($complaint['location']) ?>
            </div>
        </div>

        <div class="description">
            <strong>Description:</strong>
            <p><?= htmlspecialchars($complaint['description']) ?></p>
        </div>
    </div>

    <!-- RESOLUTION -->
    <div class="resolution-section">

        <h3 style="color:#4b2aad;">Resolution</h3>

        <?php if ($success): ?>
            <div class="success-box"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="wid" value="<?= htmlspecialchars($workOrder['id']) ?>">

            <div style="margin-bottom:15px;">
                <label><strong>Remarks / Notes:</strong></label>
                <textarea rows="4" name="remarks"><?= htmlspecialchars($workOrder['remarks'] ?? '') ?></textarea>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <label><strong>Status:</strong></label><br>
                    <select name="status">
                        <option value="pending" <?= ($workOrder['resolutionStatus'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="in progress" <?= ($workOrder['resolutionStatus'] === '') ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= ($workOrder['resolutionStatus'] === 'completed') ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <button type="submit" class="save-btn">Save</button>
            </div>
        </form>

    </div>
</div>

<?php endif; ?>

<?php include('../../inc/footer_private.php'); ?>
