<?php
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');
include('../../inc/header_private.php');

$idToken = $_SESSION['idToken'];
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


/* ================= COUNTERS ================= */
$students = $wardens = $staff = 0;
$totalHostels = 0;

$totalComplaints = 0;
$resolvedComplaints = 0;
$inProgressComplaints = 0;

$complaintsByHostel = [];
$hostelNames = [];

/* ================= USERS ================= */
$users = firestore_get_collection('Users', $idToken);
foreach ($users as $u) {
    switch ($u['role'] ?? '') {
        case 'student': $students++; break;
        case 'warden': $wardens++; break;
        case 'maintenance_staff': $staff++; break;
    }
}

/* ================= HOSTELS ================= */
$hostels = firestore_get_collection('Hostels', $idToken);
foreach ($hostels as $h) {
    $totalHostels++;
    $hostelNames[$h['hostelID']] = $h['name'];
}

/* ================= COMPLAINTS ================= */
$complaints = firestore_get_collection('Complaints', $idToken);
foreach ($complaints as $c) {
    $totalComplaints++;
    $hid = $c['hostelID'] ?? 'Unknown';
    $complaintsByHostel[$hid] = ($complaintsByHostel[$hid] ?? 0) + 1;
}

/* ================= WORK ORDERS ================= */
$workOrders = firestore_get_collection('WorkOrders', $idToken);
foreach ($workOrders as $wo) {
    if (strtolower($wo['resolutionStatus'] ?? '') === 'resolved') {
        $resolvedComplaints++;
    } else {
        $inProgressComplaints++;
    }
}
?>

<link rel="stylesheet" href="../../assets/header_style.css">
<link rel="stylesheet" href="../../assets/dashboard_style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-header">
    <h2>Welcome, Admin <?= htmlspecialchars($_SESSION['user']['name']); ?>!</h2>
    <p>Here's a quick overview of the system's activity.</p>
</div>

<!-- USER OVERVIEW -->
<h3 class="dashboard-section-title"><i class="fa-solid fa-users"></i> User Overview</h3>
<div class="dashboard-grid">

    <div class="dashboard-card card-students">
        <div class="card-icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $students ?></p>
            <p class="card-title">Total Students</p>
        </div>
    </div>

    <div class="dashboard-card card-wardens">
        <div class="card-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $wardens ?></p>
            <p class="card-title">Total Wardens</p>
        </div>
    </div>

    <div class="dashboard-card card-staff">
        <div class="card-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $staff ?></p>
            <p class="card-title">Maintenance Staff</p>
        </div>
    </div>

    <div class="dashboard-card card-hostels">
        <div class="card-icon"><i class="fa-solid fa-building-user"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $totalHostels ?></p>
            <p class="card-title">Total Hostels</p>
        </div>
    </div>

</div>

<!-- COMPLAINT ANALYTICS -->
<h3 class="dashboard-section-title"><i class="fa-solid fa-chart-pie"></i> Complaint Analytics</h3>
<div class="dashboard-grid">

    <div class="dashboard-card card-complaints">
        <div class="card-icon"><i class="fa-solid fa-flag"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $totalComplaints ?></p>
            <p class="card-title">Total Complaints</p>
        </div>
    </div>

    <div class="dashboard-card card-resolved">
        <div class="card-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $resolvedComplaints ?></p>
            <p class="card-title">Resolved</p>
        </div>
    </div>

    <div class="dashboard-card card-progress">
        <div class="card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="card-content">
            <p class="card-value"><?= $inProgressComplaints ?></p>
            <p class="card-title">In Progress / Review</p>
        </div>
    </div>

</div>

<!-- CHARTS -->
<h3 class="dashboard-section-title"><i class="fa-solid fa-chart-line"></i> Visual Analytics</h3>
<div class="dashboard-grid">

    <div class="dashboard-chart-card">
        <h4>Complaints by Hostel</h4>
        <canvas id="complaintsByHostelChart"></canvas>
    </div>

    <div class="dashboard-chart-card">
        <h4>Complaint Status Breakdown</h4>
        <canvas id="complaintStatusChart"></canvas>
    </div>

</div>

<script>
new Chart(document.getElementById('complaintsByHostelChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_values($hostelNames)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($complaintsByHostel)) ?>,
            backgroundColor: 'rgba(66,39,106,0.7)',
            borderRadius: 6
        }]
    },
    options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});

new Chart(document.getElementById('complaintStatusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Resolved','In Progress'],
        datasets: [{
            data: [<?= $resolvedComplaints ?>, <?= $inProgressComplaints ?>],
            backgroundColor: ['#28a745','#ffc107']
        }]
    },
    options: { aspectRatio: 1.5 }
});
</script>

<?php include('../../inc/footer_private.php'); ?>

