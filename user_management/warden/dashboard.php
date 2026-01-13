<?php
$page_title = "Warden Dashboard";
include_once('../../inc/auth_check.php');
require_role('warden');
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

$warden = $_SESSION['user'];
$wardenUserID = $warden['userID'];
$idToken = $_SESSION['idToken'];

$hostelName = 'Not Assigned';
$error = '';

// Data counters
$total_students = 0;
$total_complaints = 0;
$resolved_complaints = 0;
$total_rooms = 0;
$available_rooms = 0; 
$occupied_rooms = 0;
$maintenance_rooms = 0;



try {
    // 1. Fetch Hostel Name if assigned
    if (!empty($warden['hostelID'])) {
        $hostelDoc = firestore_get('Hostels', $warden['hostelID'], $idToken);
        if (isset($hostelDoc['fields']['name'])) {
            $hostelName = reset($hostelDoc['fields']['name']);
        }

        // 2. Fetch and count students in the assigned hostel
        $students_collection = firestore_get_collection('Users', $idToken);
        if (!isset($students_collection['error'])) {
            foreach ($students_collection as $student) {
                if (($student['role'] ?? '') === 'student' && ($student['hostelID'] ?? '') === $warden['hostelID']) {
                    $total_students++;
                }
            }
        } else {
            $error = "Could not load student data.";
        }

        // 3. Fetch and count complaints related to the assigned hostel
        $complaints_collection = firestore_get_collection('Complaints', $idToken);
        if (!isset($complaints_collection['error'])) {
            foreach ($complaints_collection as $complaint) {
                if (($complaint['hostelID'] ?? '') === $warden['hostelID']) {
                    $total_complaints++;
                    if (strtolower($complaint['status'] ?? '') === 'resolved') {
                        $resolved_complaints++;
                    }
                }
            }
        } else {
            $error = "Could not load complaint data.";
        }

        // 4. Fetch and count rooms by status in the assigned hostel
        $rooms_collection = firestore_get_collection('Rooms', $idToken);
        if (!isset($rooms_collection['error'])) {
            foreach ($rooms_collection as $room) {
                if (($room['hostelID'] ?? '') === $warden['hostelID']) {
                    $total_rooms++;
                    $status = strtolower($room['status'] ?? '');
                    switch ($status) {
                        case 'available':
                            $available_rooms++;
                            break;
                        case 'occupied':
                            $occupied_rooms++;
                            break;
                        case 'maintenance':
                            $maintenance_rooms++;
                            break;
                    }
                }
            }
        }
    } else {
        $error = "Warden is not assigned to any hostel.";
    }
} catch (Exception $e) {
    $error = "An error occurred while loading dashboard data: " . $e->getMessage();
}
?>
<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/dashboard_style.css">

<div class="dashboard-header">
    <h2>Welcome, Warden <?php echo htmlspecialchars($warden['name']); ?>!</h2>
    <p>Here's a summary of your hostel.</p>
</div>

<?php if ($error): ?>
    <div class="message error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Hostel Analytics -->
<h3 class="dashboard-section-title"><i class="fa-solid fa-building-user"></i> Hostel Overview</h3>
<div class="dashboard-grid">
    <div class="dashboard-card card-hostels">
        <div class="card-icon"><i class="fa-solid fa-building"></i></div>
        <div class="card-content">
            <p class="card-value"><?= htmlspecialchars($hostelName) ?></p>
            <p class="card-title">Assigned Hostel</p>
        </div>
    </div>
    <div class="dashboard-card card-students">
        <div class="card-icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $total_students ?></p>
            <p class="card-title">Total Students</p>
        </div>
    </div>
    <div class="dashboard-card card-complaints">
        <div class="card-icon"><i class="fa-solid fa-flag"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $total_complaints ?></p>
            <p class="card-title">Total Complaints</p>
        </div>
    </div>
    <div class="dashboard-card card-resolved">
        <div class="card-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $resolved_complaints ?></p>
            <p class="card-title">Resolved Complaints</p>
        </div>
    </div>
</div>

<!-- Room Analytics -->
<h3 class="dashboard-section-title"><i class="fa-solid fa-door-open"></i> Room Status Overview</h3>
<div class="dashboard-grid">
    <div class="dashboard-card card-hostels">
        <div class="card-icon"><i class="fa-solid fa-person-shelter"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $total_rooms ?></p>
            <p class="card-title">Total Rooms</p>
        </div>
    </div>
    <div class="dashboard-card card-resolved">
        <div class="card-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $available_rooms ?></p>
            <p class="card-title">Available Rooms</p>
        </div>
    </div>
    <div class="dashboard-card card-progress">
        <div class="card-icon"><i class="fa-solid fa-users"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $occupied_rooms ?></p>
            <p class="card-title">Occupied Rooms</p>
        </div>
    </div>
    <div class="dashboard-card card-complaints"><div class="card-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div><div class="card-content"><p class="card-value"><?= $maintenance_rooms ?></p><p class="card-title">Maintenance</p></div></div>
</div>

<!-- Quick Actions -->
<h3 class="dashboard-section-title"><i class="fa-solid fa-bolt"></i> Quick Actions</h3>
<div class="quick-actions">
    <a href="/user_management/warden/student_main.php" class="action-btn">
        <i class="fa-solid fa-graduation-cap"></i> Manage Students
    </a>
    <a href="/user_management/warden/report.php" class="action-btn">
        <i class="fa-solid fa-flag"></i> View Complaints
    </a>
    <a href="/user_management/warden/workorders.php" class="action-btn">
        <i class="fa-solid fa-clipboard-list"></i> View Work Orders
    </a>
    <a href="/user_management/warden/manage_profile.php" class="action-btn">
        <i class="fa-solid fa-user-circle"></i> Manage Profile
    </a>
</div>

<?php include('../../inc/footer_private.php'); ?>

