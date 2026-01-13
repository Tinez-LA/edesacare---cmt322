<?php
$page_title = "My Complaints";
include_once('../../inc/auth_check.php');
require_role('student');
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


$student = $_SESSION['user'];
$studentUserID = $student['userID'];
$idToken = $_SESSION['idToken'];

$complaints = [];
$error = '';

try {
    // Fetch complaints for the student
    $complaints_collection = firestore_get_collection('Complaints', $idToken);
    if (!isset($complaints_collection['error'])) {
        foreach ($complaints_collection as $complaint) {
            if (($complaint['studentUserID'] ?? '') === $studentUserID) {
                $complaints[] = $complaint;
            }
        }
    } else {
        $error = "Could not load complaint data.";
    }
} catch (Exception $e) {
    $error = "An error occurred while loading complaints: " . $e->getMessage();
}

// Build a list of complaint IDs that already have feedback from this student
$feedbackComplaintIDs = [];
try {
    $feedbacks_collection = firestore_get_collection('Feedbacks', $idToken);
    if (!isset($feedbacks_collection['error'])) {
        foreach ($feedbacks_collection as $fb) {
            if (($fb['studentUserID'] ?? '') === $studentUserID) {
                $cid = $fb['complaintID'] ?? null;
                if ($cid) {
                    $feedbackComplaintIDs[$cid] = true;
                }
            }
        }
    }
} catch (Exception $e) {
    // Do not override complaint error; just silently ignore feedback lookup error
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">

<div class="dashboard-header">
    <h2>My Complaints</h2>
    <p>View and track the status of your submitted complaints.</p>
</div>

<?php if ($error): ?>
    <div class="message error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($complaints)): ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Image</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Date Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complaints as $complaint): ?>
                    <?php
                        $statusRaw = $complaint['status'] ?? 'pending';
                        $statusKey = strtolower(str_replace(' ', '-', $statusRaw));
                        $complaintID = $complaint['id'] ?? '';
                        $hasFeedback = isset($feedbackComplaintIDs[$complaintID]);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($complaint['location'] ?? '') ?></td>
                        <td><?= htmlspecialchars($complaint['type'] ?? '') ?></td>
                        <td><?= htmlspecialchars($complaint['description'] ?? '') ?></td>
                        <td><?= htmlspecialchars($complaint['imagePath'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($complaint['severity'] ?? '') ?></td>
                        <td>
                            <span class="status-<?= htmlspecialchars($statusKey) ?>">
                                <?= htmlspecialchars($statusRaw) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($complaint['dateSubmitted'] ?? '') ?></td>
                        <td>
                            <?php if (strtolower($statusRaw) === 'resolved'): ?>
                                <?php if (!$hasFeedback): ?>
                                    <a href="/user_management/student/submit-feedback.php?id=<?= urlencode($complaintID) ?>"
                                       class="action-button">
                                        Feedback
                                    </a>
                                <?php else: ?>
                                    <span style="color:#888; font-size:0.9rem;">
                                        Feedback submitted
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#888; font-size:0.9rem;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p>You have not submitted any complaints yet.</p>
<?php endif; ?>

<?php include('../../inc/footer_private.php'); ?>
