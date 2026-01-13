<?php
// Include necessary files for authentication and header
include '../../inc/auth_check.php';
include '../../inc/header_private.php';
include '../../inc/firebase.php';

$user = $_SESSION['user'];
$userID = $user['userID'];
$idToken = $_SESSION['idToken'];

// Fetch user details to autofill hostel and room
$hostelName = '';
$roomNumber = '';
$hostelID = '';
$roomID = '';

try {
    $userDoc = firestore_get('Users', $userID, $idToken);
    if (isset($userDoc['fields'])) {
        $hostelID = $userDoc['fields']['hostelID']['stringValue'] ?? '';
        $roomID   = $userDoc['fields']['roomID']['stringValue'] ?? '';

        if (!empty($hostelID)) {
            $hostelDoc = firestore_get('Hostels', $hostelID, $idToken);
            if (isset($hostelDoc['fields']['name'])) {
                $hostelName = reset($hostelDoc['fields']['name']);
            }
        }

        if (!empty($roomID)) {
            $roomDoc = firestore_get('Rooms', $roomID, $idToken);
            if (isset($roomDoc['fields']['roomNumber'])) {
                $roomNumber = reset($roomDoc['fields']['roomNumber']);
            }
        }
    }
} catch (Exception $e) {
    // ignore
}

/* ---------- Default form values ---------- */
$location = '';
$type = '';
$description = '';
$severity = '';
$dateSubmitted = date('Y-m-d');

/* ---------- Handle form submission ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get inputs
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dateSubmitted = $_POST['dateSubmitted'] ?? $dateSubmitted;
    $severity = trim($_POST['severity'] ?? '');
    $imagePath = '';

    /* ---------- INPUT VALIDATION ---------- */

    // 1. Required field check
    if (empty($location) || empty($type) || empty($description) || empty($dateSubmitted) || empty($severity)) {
        $error = "All fields are required.";
    }

    // 2. Allowed values validation (prevents tampered POST requests)
    $allowedLocations = ['Room', 'Bathroom', 'Pantry', 'Study Room', 'Common Area', 'Other'];
    $allowedTypes = ['Electricity', 'Malfunction', 'Noise', 'Cleanliness', 'Pest Control', 'Other'];
    $allowedSeverities = ['low', 'medium', 'high', 'critical'];

    if (!isset($error) && !in_array($location, $allowedLocations, true)) {
        $error = "Invalid location selected.";
    }

    if (!isset($error) && !in_array($type, $allowedTypes, true)) {
        $error = "Invalid complaint type selected.";
    }

    if (!isset($error) && !in_array($severity, $allowedSeverities, true)) {
        $error = "Invalid severity level selected.";
    }

    // 3. Description length validation
    if (!isset($error) && (strlen($description) < 10 || strlen($description) > 500)) {
        $error = "Description must be between 10 and 500 characters.";
    }

    // 4. Date validation
    if (!isset($error) && !DateTime::createFromFormat('Y-m-d', $dateSubmitted)) {
        $error = "Invalid date format.";
    }

    if (!isset($error) && $dateSubmitted > date('Y-m-d')) {
        $error = "Date cannot be in the future.";
    }

    // 5. Image upload validation (if image selected)
    if (!isset($error) && !empty($_FILES['imagePath']['name'])) {
        $allowedTypes = ['image/jpeg', 'image/png'];
        $fileType = $_FILES['imagePath']['type'];
        $fileSize = $_FILES['imagePath']['size'];

        if (!in_array($fileType, $allowedTypes)) {
            $error = "Only JPG and PNG images are allowed.";
        }

        if ($fileSize > 2 * 1024 * 1024) {
            $error = "Image size must not exceed 2MB.";
        }
    }

    /* ---------- Save to Firestore ---------- */
    if (!isset($error)) {

        $complaintData = [
            'studentUserID' => $userID,
            'hostelID'      => $hostelID,
            'roomID'        => $roomID,
            'roomNumber'    => $roomNumber,
            'location'      => $location,
            'type'          => $type,
            'description'   => $description,
            'severity'      => $severity,
            'dateSubmitted' => $dateSubmitted,
            'status'        => 'pending',
            'imagePath'     => $imagePath
        ];

        try {
            $result = firestore_add('Complaints', $complaintData, $idToken);
            if (isset($result['name'])) {
                $success = "Complaint submitted successfully!";
                $location = $type = $description = $severity = '';
                $dateSubmitted = date('Y-m-d');
            } else {
                $error = "Failed to submit complaint.";
            }
        } catch (Exception $e) {
            $error = "An error occurred.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <h1 class="page-title">Submit Complaint</h1>
    <link rel="stylesheet" href="../../assets/form_style.css">
    <link rel="stylesheet" href="../../assets/header_style.css">
</head>
<body>

<div class="form-wrapper">

    <?php if (isset($success)): ?>
        <div class="message success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label>Hostel Name</label>
        <input type="text" value="<?php echo htmlspecialchars($hostelName); ?>" readonly>

        <label>Room Number</label>
        <input type="text" value="<?php echo htmlspecialchars($roomNumber); ?>" readonly>

        <label>Location of Issue</label>
        <select name="location" required>
            <option value="">Select Location</option>
            <?php foreach (['Room','Bathroom','Pantry','Study Room','Common Area','Other'] as $opt): ?>
                <option value="<?php echo $opt; ?>" <?php if ($location === $opt) echo 'selected'; ?>>
                    <?php echo $opt; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Type of Complaint</label>
        <select name="type" required>
            <option value="">Select Type</option>
            <?php foreach (['Electricity','Malfunction','Noise','Cleanliness','Pest Control','Other'] as $opt): ?>
                <option value="<?php echo $opt; ?>" <?php if ($type === $opt) echo 'selected'; ?>>
                    <?php echo $opt; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Description</label>
        <textarea name="description" rows="4" required><?php echo htmlspecialchars($description); ?></textarea>

        <label>Date Submitted</label>
        <input type="date" name="dateSubmitted" value="<?php echo htmlspecialchars($dateSubmitted); ?>" required>

        <label>Severity</label>
        <select name="severity" required>
            <option value="">Select Severity</option>
            <?php foreach (['low','medium','high','critical'] as $opt): ?>
                <option value="<?php echo $opt; ?>" <?php if ($severity === $opt) echo 'selected'; ?>>
                    <?php echo ucfirst($opt); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Image</label>
        <input type="file" name="imagePath" accept="image/*">

         <button type="submit" class="btn-submit">Submit Complaint</button>

    </form>
</div>

</body>
</html>
