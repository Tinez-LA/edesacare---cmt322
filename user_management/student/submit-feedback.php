<?php
$page_title = "Submit Feedback";
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
$idToken = $_SESSION['idToken'] ?? '';

$complaintID = $_GET['id'] ?? '';
$complaint = null;
$error = '';
$success = '';
$alreadyHasFeedback = false;

if (empty($complaintID)) {
    $error = "No complaint specified for feedback.";
} else {
    try {
        // Load all complaints and find the one that belongs to this student
        $complaints_collection = firestore_get_collection('Complaints', $idToken);
        if (!isset($complaints_collection['error'])) {
            foreach ($complaints_collection as $c) {
                if (($c['id'] ?? '') === $complaintID && ($c['studentUserID'] ?? '') === $studentUserID) {
                    $complaint = $c;
                    break;
                }
            }
            if (!$complaint) {
                $error = "Complaint not found or you are not authorised to give feedback for this complaint.";
            }
        } else {
            $error = "Could not load complaint data.";
        }

        // Check if feedback already exists for this complaint from this student
        $feedbacks_collection = firestore_get_collection('Feedbacks', $idToken);
        if (!isset($feedbacks_collection['error'])) {
            foreach ($feedbacks_collection as $fb) {
                if (
                    ($fb['studentUserID'] ?? '') === $studentUserID &&
                    ($fb['complaintID'] ?? '') === $complaintID
                ) {
                    $alreadyHasFeedback = true;
                    break;
                }
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred while loading data: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && $complaint) {
    if ($alreadyHasFeedback) {
        $error = "You have already submitted feedback for this complaint.";
    } else {
        $resolveStatus = $_POST['resolveStatus'] ?? '';
        $rating = $_POST['rating'] ?? '';
        $comments = trim($_POST['comments'] ?? '');
        $dateSubmitted = date('Y-m-d');

        if (empty($resolveStatus) || empty($rating)) {
            $error = "Please select both the resolve status and rating.";
        } else {
            $feedbackData = [
                'complaintID'    => $complaintID,
                'studentUserID'  => $studentUserID,
                'resolveStatus'  => $resolveStatus,
                'rating'         => (int)$rating,
                'comments'       => $comments,
                'dateSubmitted'  => $dateSubmitted,
            ];

            try {
                $result = firestore_add('Feedbacks', $feedbackData, $idToken);
                if (isset($result['name'])) {
                    $success = "Feedback submitted successfully!";
                    $alreadyHasFeedback = true;
                } else {
                    $error = "Failed to submit feedback: " . ($result['error']['message'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                $error = "An error occurred while submitting feedback: " . $e->getMessage();
            }
        }
    }
}

// Determine if feedback is allowed based on complaint status and existing feedback
$allowFeedback = true;
$complaintStatus = '';

if ($complaint) {
    $complaintStatus = strtolower($complaint['status'] ?? '');
    if ($complaintStatus !== 'resolved') {
        $allowFeedback = false;
        if (!$error && !$success) {
            $error = "Feedback can only be submitted for complaints that are marked as resolved.";
        }
    }
}

if ($alreadyHasFeedback) {
    $allowFeedback = false;
    if (!$success && !$error) {
        $error = "You have already submitted feedback for this complaint.";
    }
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/form_style.css">

<div class="form-wrapper">
    <div class="form-header">
        <a href="/user_management/student/my-complaints.php" class="back-icon-link">
            &#8592; Back
        </a>
        <h2>Submit Feedback</h2>
    </div>

    <?php if ($complaint): ?>
        <div class="form-intro" style="margin-bottom: 20px;">
            <p><strong>Complaint ID:</strong> <?= htmlspecialchars($complaintID) ?></p>
            <p style="color:#666; font-size:0.95rem;">
                Note: This feedback is for a complaint marked as
                <strong><?= htmlspecialchars(ucfirst($complaintStatus) ?: 'Resolved') ?></strong>.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="message success" style="margin-bottom: 20px;"><?= htmlspecialchars($success) ?></div>

        <!-- After success: 3 action buttons -->
        <div style="margin-bottom: 25px; display:flex; gap:12px; flex-wrap:wrap;">
            <a href="/user_management/student/comp-form.php" class="btn-submit" style="text-decoration:none; text-align:center;">
                Submit New Complaint
            </a>
            <a href="/user_management/student/comp-form.php?reComplaint=1&complaintID=<?= urlencode($complaintID) ?>"
               class="btn-submit" style="text-decoration:none; text-align:center;">
                Submit Re-Complaint
            </a>
            <a href="/user_management/student/my-feedbacks.php" class="btn-submit" style="text-decoration:none; text-align:center;">
                View My Feedbacks
            </a>
        </div>
    <?php endif; ?>

    <?php if ($complaint && $allowFeedback): ?>
        <form action="" method="POST">
            <!-- Is the issue resolved -->
            <fieldset style="border:none; padding:0; margin:0 0 20px 0;">
                <legend style="font-weight:600; margin-bottom:10px;">Is the issue resolved</legend>
                <label style="display:inline-block; margin-right:20px;">
                    <input type="radio" name="resolveStatus" value="Yes" required
                        <?= (($_POST['resolveStatus'] ?? '') === 'Yes') ? 'checked' : '' ?>> Yes
                </label>
                <label style="display:inline-block; margin-right:20px;">
                    <input type="radio" name="resolveStatus" value="Partially"
                        <?= (($_POST['resolveStatus'] ?? '') === 'Partially') ? 'checked' : '' ?>> Partially
                </label>
                <label style="display:inline-block;">
                    <input type="radio" name="resolveStatus" value="No"
                        <?= (($_POST['resolveStatus'] ?? '') === 'No') ? 'checked' : '' ?>> No
                </label>
            </fieldset>

            <!-- Rating -->
            <fieldset style="border:none; padding:0; margin:0 0 20px 0;">
                <legend style="font-weight:600; margin-bottom:10px;">Rate the work done</legend>
                <label style="display:inline-block; margin-right:15px;">
                    <input type="radio" name="rating" value="1" required
                        <?= (($_POST['rating'] ?? '') === '1') ? 'checked' : '' ?>> 1 - Very Poor
                </label>
                <label style="display:inline-block; margin-right:15px;">
                    <input type="radio" name="rating" value="2"
                        <?= (($_POST['rating'] ?? '') === '2') ? 'checked' : '' ?>> 2 - Poor
                </label>
                <label style="display:inline-block; margin-right:15px;">
                    <input type="radio" name="rating" value="3"
                        <?= (($_POST['rating'] ?? '') === '3') ? 'checked' : '' ?>> 3 - Okay
                </label>
                <label style="display:inline-block; margin-right:15px;">
                    <input type="radio" name="rating" value="4"
                        <?= (($_POST['rating'] ?? '') === '4') ? 'checked' : '' ?>> 4 - Good
                </label>
                <label style="display:inline-block;">
                    <input type="radio" name="rating" value="5"
                        <?= (($_POST['rating'] ?? '') === '5') ? 'checked' : '' ?>> 5 - Very Good
                </label>
            </fieldset>

            <!-- Comments -->
            <label for="comments">Feedback Description</label>
            <textarea id="comments" name="comments" rows="4"
                placeholder="Share what was done well, or if there’s still some things not okay."><?= htmlspecialchars($_POST['comments'] ?? '') ?></textarea>

            <button type="submit" class="btn-submit">Submit Feedback</button>
        </form>
    <?php endif; ?>
</div>

<?php include('../../inc/footer_private.php'); ?>
