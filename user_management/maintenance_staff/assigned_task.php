<?php
$page_title = "Assigned Tasks";

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
$tasks = [
    'active' => [],
    'pending' => [],
    'completed' => []
];

/* ===============================
   GET LOGGED-IN STAFF
   (SAME PATTERN AS WARDEN FILE)
   =============================== */
$staff = $_SESSION['user'] ?? null;
$idToken = $_SESSION['idToken'] ?? '';

if (!$staff || empty($staff['userID'])) {
    $error = "Unable to identify logged-in staff.";
}

/* ===============================
   STATUS NORMALIZER (FLEXIBLE)
   =============================== */
function normalizeStatus($status) {
    $status = strtolower(trim($status));

    if (in_array($status, ['resolved', 'completed'])) {
        return 'completed';
    }
    if ($status === 'pending') {
        return 'pending';
    }
    return 'active'; // in progress, work order created, empty
}

/* ===============================
   FETCH & PROCESS TASKS
   =============================== */
if (!$error) {
    try {
        $workOrders = firestore_get_collection('WorkOrders', $idToken);
        $complaints = firestore_get_collection('Complaints', $idToken);

        if (isset($workOrders['error']) || isset($complaints['error'])) {
            $error = "Failed to load task data.";
        } else {

            // Map complaints by document ID
            $complaintMap = [];
            foreach ($complaints as $c) {
                if (isset($c['id'])) {
                    $complaintMap[$c['id']] = $c;
                }
            }

            // Match work orders to this staff
            foreach ($workOrders as $wo) {

                // 🔐 Only tasks assigned to THIS staff
                if (($wo['mainStaffID'] ?? '') !== $staff['userID']) continue;

                $cid = $wo['complaintID'] ?? null;
                if (!$cid || !isset($complaintMap[$cid])) continue;

                $group = normalizeStatus($wo['resolutionStatus'] ?? '');

                $tasks[$group][] = [$wo, $complaintMap[$cid]];
            }
        }
    } catch (Exception $e) {
        $error = "Unexpected error loading tasks.";
    }
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">

<style>
.tab-btn{
    padding:6px 16px;
    border-radius:18px;
    border:1px solid #ccc;
    background:#fff;
    font-size:13px;
    cursor:pointer;
}
.tab-btn.active{
    background:#ff7a30;
    color:white;
    border:none;
}
.task-card{
    border-radius:12px;
    background:#fff;
    padding:14px 18px;
    margin-bottom:18px;
}
.task-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.view-btn{
    background:#ff7a30;
    color:white;
    border:none;
    padding:8px 18px;
    border-radius:18px;
}
.status-pill{
    background:#2e7d32;
    color:white;
    padding:8px 18px;
    border-radius:18px;
}
</style>

<h2 style="color:#4b2aad;font-weight:700;">Assigned Tasks</h2>

<?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ===============================
     TABS
     =============================== -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <button class="tab-btn active" onclick="filterTasks('active')" id="btnActive">Active</button>
    <button class="tab-btn" onclick="filterTasks('pending')" id="btnPending">Pending</button>
    <button class="tab-btn" onclick="filterTasks('completed')" id="btnCompleted">Completed</button>
</div>

<!-- ===============================
     TASK LISTS
     =============================== -->
<?php foreach (['active','pending','completed'] as $status): ?>
<div class="task-group" data-status="<?= $status ?>" <?= $status !== 'active' ? 'style="display:none"' : '' ?>>
<?php foreach ($tasks[$status] as [$wo, $c]): ?>
    <div class="task-card">
        <div class="task-row">
            <div>
                <b><?= htmlspecialchars($c['type']) ?></b><br>
                <?= htmlspecialchars($c['hostelID']) ?> – Room <?= htmlspecialchars($c['roomNumber']) ?><br>
                <?= htmlspecialchars($c['dateSubmitted']) ?>
            </div>

            <?php if ($status === 'completed'): ?>
                <span class="status-pill">Completed</span>
            <?php else: ?>
                <a href="task_details.php?cid=<?= urlencode($wo['complaintID']) ?>">
                    <button class="view-btn">View</button>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>

<script>
function filterTasks(status){
    document.querySelectorAll('.task-group').forEach(g=>{
        g.style.display = g.dataset.status === status ? 'block' : 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById('btn'+status.charAt(0).toUpperCase()+status.slice(1)).classList.add('active');
}
</script>

<?php include('../../inc/footer_private.php'); ?>
