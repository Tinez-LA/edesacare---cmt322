<?php
$page_title = "Student Dashboard";
include_once('../../inc/auth_check.php');
require_role('student');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php'); // Use header_private for consistent styling


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

$hostelName = 'Not Assigned';
$roomNumber = 'Not Assigned';
$wardenName = 'Not Assigned';
$wardenContact = '';
$error = '';

// Complaint counters
$total_complaints = 0;
$pending_complaints = 0;
$progress_complaints = 0;
$resolved_complaints = 0;

try {
    // 1. Fetch Hostel Name
    if (!empty($student['hostelID'])) {
        $hostelDoc = firestore_get('Hostels', $student['hostelID'], $idToken);
        if (isset($hostelDoc['fields']['name'])) {
            $hostelName = reset($hostelDoc['fields']['name']);

        // 1a. Fetch Warden Info (from Users collection)
        $users_collection = firestore_get_collection('Users', $idToken);
        if (!isset($users_collection['error'])) {
            foreach ($users_collection as $user) {
                if (
                    ($user['role'] ?? '') === 'warden' &&
                    ($user['hostelID'] ?? '') === $student['hostelID']
                ) {
                    $wardenName = $user['name'] ?? 'Not Assigned';
                    $wardenContact = $user['contactNo'] ?? '';
                    break; // Only one warden per hostel
                }
            }
        }

        }
    }

    // 2. Fetch Room Number
    if (!empty($student['roomID'])) {
        $roomDoc = firestore_get('Rooms', $student['roomID'], $idToken);
        if (isset($roomDoc['fields']['roomNumber'])) {
            $roomNumber = reset($roomDoc['fields']['roomNumber']);
        }
    }

    // 3. Fetch and process complaints
    $complaints_collection = firestore_get_collection('Complaints', $idToken);
    if (!isset($complaints_collection['error'])) {
        foreach ($complaints_collection as $complaint) {
            if (($complaint['studentUserID'] ?? '') === $studentUserID) {
                $total_complaints++;
                $status = strtolower($complaint['status'] ?? 'pending');
                switch ($status) {
                    case 'resolved':
                        $resolved_complaints++;
                        break;
                    case 'in progress':
                        $progress_complaints++;
                        break;
                    case 'pending':
                    default:
                        $pending_complaints++;
                        break;
                }
            }
        }
    } else {
        $error = "Could not load complaint data.";
    }
} catch (Exception $e) {
    $error = "An error occurred while loading dashboard data: " . $e->getMessage();
}
?>
<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/dashboard_style.css">

<div class="dashboard-header">
    <h2>Welcome, <?php echo htmlspecialchars($student['name']); ?>!</h2>
    <p>Here's a summary of your activities and accommodation details.</p>
</div>

<?php if ($error): ?> 
    <div class="message error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Accommodation Details -->
<h3 class="dashboard-section-title"><i class="fa-solid fa-house-user"></i> My Accommodation</h3>
<div class="dashboard-grid">
    <div class="dashboard-card card-hostels">
        <div class="card-icon"><i class="fa-solid fa-building"></i></div>
        <div class="card-content"><p class="card-value"><?= htmlspecialchars($hostelName) ?></p><p class="card-title">Current Hostel</p></div>
    </div>
    <div class="dashboard-card card-students">
        <div class="card-icon"><i class="fa-solid fa-door-open"></i></div>
        <div class="card-content"><p class="card-value"><?= htmlspecialchars($roomNumber) ?></p><p class="card-title">Room Number</p></div>
    </div>
    <div class="dashboard-card card-wardens">
        <div class="card-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="card-content">
            <p class="card-value" style="font-size: 1.4rem;"><?= htmlspecialchars($wardenName) ?></p>
            <p class="card-title">Hostel Warden <?= !empty($wardenContact) ? '('.htmlspecialchars($wardenContact).')' : '' ?></p>
        </div>
    </div>
</div>

<!-- Complaint Analytics -->
<h3 class="dashboard-section-title"><i class="fa-solid fa-chart-simple"></i> My Complaints</h3>
<div class="dashboard-grid">
    <div class="dashboard-card card-complaints"><div class="card-icon"><i class="fa-solid fa-flag"></i></div><div class="card-content"><p class="card-value"><?= $total_complaints ?></p><p class="card-title">Total Submitted</p></div></div>
    <div class="dashboard-card card-progress"><div class="card-icon"><i class="fa-solid fa-hourglass-half"></i></div><div class="card-content"><p class="card-value"><?= $pending_complaints ?></p><p class="card-title">Pending Review</p></div></div>
    <div class="dashboard-card card-wardens"><div class="card-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div><div class="card-content"><p class="card-value"><?= $progress_complaints ?></p><p class="card-title">In Progress</p></div></div>
    <div class="dashboard-card card-resolved"><div class="card-icon"><i class="fa-solid fa-check-circle"></i></div><div class="card-content"><p class="card-value"><?= $resolved_complaints ?></p><p class="card-title">Resolved</p></div></div>
</div>

<!-- Quick Actions -->
<h3 class="dashboard-section-title"><i class="fa-solid fa-bolt"></i> Quick Actions</h3>
<div class="quick-actions">
    <a href="/user_management/student/comp-form.php" class="action-btn"><i class="fa-solid fa-paper-plane"></i> Report a New Issue</a>
    <a href="/user_management/student/my-complaints.php" class="action-btn"><i class="fa-solid fa-list-check"></i> Track My Complaints</a>
</div>

<?php include('../../inc/footer_private.php'); ?>
