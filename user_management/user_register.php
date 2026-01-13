<?php
$page_title = "Add User";
include_once('inc/auth_check.php');
require_role('admin');
include_once('inc/firebase.php');
include('inc/header.php');


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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    // create Firestore user document (no signup)
    $uid = uniqid('user_');
    $idToken = $_SESSION['idToken'];

    firestore_set('Users', $uid, [
        'name' => $name,
        'email' => $email,
        'role' => $role,
    ], $idToken);

    echo "<p style='color:green'>✅ User added successfully! They can reset their password via 'Forgot Password' on login page.</p>";
}
?>

<h2>Add New User</h2>
<form method="POST">
    <label>Name:</label><br>
    <input name="name" required><br>
    <label>Email:</label><br>
    <input name="email" type="email" required><br>
    <label>Role:</label><br>
    <select name="role" required>
        <option value="student">Student</option>
        <option value="warden">Warden</option>
        <option value="staff">Maintenance Staff</option>
    </select><br><br>
    <button type="submit">Add User</button>
</form>
<?php include('inc/footer.php'); ?>
