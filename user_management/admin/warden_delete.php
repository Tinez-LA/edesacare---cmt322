<?php
include_once('../../inc/auth_check.php');
require_role('admin');
include_once('../../inc/firebase.php');

if (!isset($_GET['id'])) {
    die("Invalid request");
}

$userID = $_GET['id'];

// Firestore DELETE API
$url = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents/Users/" . $userID;

$headers = [
    "Authorization: Bearer " . $_SESSION['idToken']
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
curl_close($ch);

header("Location: warden_manage.php?msg=deleted");
exit;
?>
