<?php
$page_title = "My Feedbacks";
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

$feedbacks = [];
$error = '';

try {
    // Fetch all feedback documents
    $feedbacks_collection = firestore_get_collection('Feedbacks', $idToken);

    if (!isset($feedbacks_collection['error'])) {
        foreach ($feedbacks_collection as $feedback) {
            // Only keep feedbacks that belong to the logged-in student
            if (($feedback['studentUserID'] ?? '') === $studentUserID) {
                $feedbacks[] = $feedback;
            }
        }
    } else {
        $error = "Could not load feedback data.";
    }
} catch (Exception $e) {
    $error = "An error occurred while loading feedbacks: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/table_style.css">

<div class="dashboard-header">
    <h2>My Feedbacks</h2>
    <p>A record of feedback you have submitted for completed work orders.</p>
</div>

<?php if ($error): ?>
    <div class="message error" style="margin-bottom: 20px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($feedbacks)): ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Complaint ID</th>
                    <th>Date Submitted</th>
                    <th>Resolve Status</th>
                    <th>Rating</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feedbacks as $feedback): ?>
                    <tr>
                        <td><?= htmlspecialchars($feedback['complaintID'] ?? '') ?></td>
                        <td><?= htmlspecialchars($feedback['dateSubmitted'] ?? '') ?></td>
                        <td><?= htmlspecialchars($feedback['resolveStatus'] ?? '') ?></td>
                        <td>
                            <?php
                                $ratingValue = isset($feedback['rating']) ? (int)$feedback['rating'] : 0;
                                switch ($ratingValue) {
                                    case 1: $ratingLabel = '1 - Very Poor'; break;
                                    case 2: $ratingLabel = '2 - Poor'; break;
                                    case 3: $ratingLabel = '3 - Okay'; break;
                                    case 4: $ratingLabel = '4 - Good'; break;
                                    case 5: $ratingLabel = '5 - Very Good'; break;
                                    default: $ratingLabel = $ratingValue ?: '-';
                                }
                            ?>
                            <?= htmlspecialchars($ratingLabel) ?>
                        </td>
                        <td><?= nl2br(htmlspecialchars($feedback['comments'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p>You have not submitted any feedback yet.</p>
<?php endif; ?>

<?php include('../../inc/footer_private.php'); ?>
