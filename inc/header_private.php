<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/');
    session_start();
}

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$role = strtolower($user['role'] ?? '');
$page_title = $page_title ?? 'eDesaCare Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/header_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>
<body>

<div class="topnav">
    <div class="title">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQn1lTQINo3tWSbb8DW1aknywdJqss8xq5cHQ&s" alt="Logo">
        <h3>eDesaCare</h3>
    </div>

    <div class="profile-menu">
        <button class="profile-btn">
            <i class="fa-solid fa-user"></i>
            <?php echo htmlspecialchars($user['name'] ?? 'User'); ?> ▼
        </button>
        <div class="profile-dropdown">
            <?php
                // Generate the correct profile link based on the user's role
                $profile_path = "/user_management/{$role}/manage_profile.php"; // Default path
            ?>
            <a href="<?= $profile_path ?>">Profile</a>
            <a href="/auth/logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="sidebar" id="sidebar">
    <?php if ($role === 'admin'): ?>
        <a href="/user_management/admin/dashboard.php">
            <i class="fa-solid fa-house-user fa-fw"></i> &nbsp; Dashboard
        </a>
        <a href="/user_management/admin/warden_main.php">
            <i class="fa-solid fa-shield-halved fa-fw"></i> &nbsp; Manage Wardens
        </a>
        <a href="/user_management/admin/staff_main.php">
            <i class="fa-solid fa-screwdriver-wrench fa-fw"></i> &nbsp; Manage Staff
        </a>
        <a href="/user_management/admin/student_main.php">
            <i class="fa-solid fa-graduation-cap fa-fw"></i> &nbsp; Manage Students
        </a>
        <a href="/user_management/admin/hostel_main.php">
            <i class="fa-solid fa-building-user fa-fw"></i> &nbsp; Manage Hostel
        </a>
        <a href="/user_management/admin/room_main.php">
            <i class="fa-solid fa-door-open fa-fw"></i> &nbsp; Manage Rooms
        </a>
        <a href="/user_management/admin/assign_student_room.php">
            <i class="fa-solid fa-person-shelter fa-fw"></i> &nbsp; Assign Room
        </a>
        <a href="/user_management/admin/manage_profile.php">
            <i class="fa-solid fa-user-circle fa-fw"></i> &nbsp; Manage Profile
        </a>

    <?php elseif ($role === 'warden'): ?>
        <a href="/user_management/warden/dashboard.php">
            <i class="fa-solid fa-house-user fa-fw"></i> &nbsp; Dashboard
        </a>
        <a href="/user_management/warden/student_main.php">
            <i class="fa-solid fa-graduation-cap fa-fw"></i> &nbsp; Manage Students
        </a>
        <a href="/user_management/warden/hostel_view.php">
            <i class="fa-solid fa-building-user fa-fw"></i> &nbsp; My Hostel
        </a>
        <a href="/user_management/warden/assign_student_room.php">
            <i class="fa-solid fa-person-shelter fa-fw"></i> &nbsp; Assign Room
        </a>
        <a href="/user_management/warden/report.php">
            <i class="fa-solid fa-flag fa-fw"></i> &nbsp; Maintenance Reports
        </a>
        <a href="/user_management/warden/workorders.php">
            <i class="fa-solid fa-clipboard-list fa-fw"></i> &nbsp; Work Orders
        </a>
        <a href="/user_management/warden/manage_profile.php">
            <i class="fa-solid fa-user-circle fa-fw"></i> &nbsp; Profile
        </a>

    <?php elseif ($role === 'maintenance_staff'): ?>
        <a href="/user_management/maintenance_staff/dashboard.php">
            <i class="fa-solid fa-house-user fa-fw"></i> &nbsp; Dashboard
        </a>
        <a href="/user_management/maintenance_staff/assigned_task.php">
            <i class="fa-solid fa-list-check fa-fw"></i> &nbsp; My Tasks
        </a>
        <a href="/user_management/maintenance_staff/pending_task.php">
            <i class="fa-solid fa-hourglass-half fa-fw"></i> &nbsp; Pending Tasks
        </a>
        <a href="/user_management/maintenance_staff/manage_profile.php">
            <i class="fa-solid fa-user-circle fa-fw"></i> &nbsp; Profile
        </a>

    <?php elseif ($role === 'student'): ?>
        <a href="/user_management/student/dashboard.php">
            <i class="fa-solid fa-house-user fa-fw"></i> &nbsp; Dashboard
        </a>
        <a href="/user_management/student/comp-form.php">
            <i class="fa-solid fa-paper-plane fa-fw"></i> &nbsp; Submit Complaints
        </a>
        <a href="/user_management/student/my-complaints.php">
            <i class="fa-solid fa-list fa-fw"></i> &nbsp; My Complaints
        </a>
        <!-- NEW MENU ITEM: My Feedbacks -->
        <a href="/user_management/student/my-feedbacks.php">
            <i class="fa-solid fa-comments fa-fw"></i> &nbsp; My Feedbacks
        </a>
        <a href="/user_management/student/manage_profile.php">
            <i class="fa-solid fa-user-circle fa-fw"></i> &nbsp; Profile
        </a>
    <?php endif; ?>
</div>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Page-specific content starts here -->
