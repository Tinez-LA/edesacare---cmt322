<?php
$page_title = "Create Work Order";
include_once('../../inc/auth_check.php');
require_role('warden');
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


$warden = $_SESSION['user'];
$wardenUserID = $warden['userID'];
$idToken = $_SESSION['idToken'];

$complaintID = $_GET['complaintID'] ?? '';
$complaint = [];
$studentName = '';
$hostelName = '';
$roomNumber = '';
$maintenanceStaff = [];
$error = '';
$success = '';

if (empty($complaintID)) {
    $error = "Complaint ID is required.";
} else {
    try {
        // Fetch complaint details
        $complaintDoc = firestore_get('Complaints', $complaintID, $idToken);
        if (isset($complaintDoc['fields'])) {
            $complaint = [];
            foreach ($complaintDoc['fields'] as $key => $value) {
                $complaint[$key] = reset($value);
            }

            // Fetch student name
            $studentUserID = $complaint['studentUserID'] ?? '';
            if (!empty($studentUserID)) {
                $studentDoc = firestore_get('Users', $studentUserID, $idToken);
                if (isset($studentDoc['fields']['name'])) {
                    $studentName = reset($studentDoc['fields']['name']);
                }
            }

            // Fetch hostel name
            $hostelID = $complaint['hostelID'] ?? '';
            if (!empty($hostelID)) {
                $hostelDoc = firestore_get('Hostels', $hostelID, $idToken);
                if (isset($hostelDoc['fields']['name'])) {
                    $hostelName = reset($hostelDoc['fields']['name']);
                }
            }

            // Fetch room number
            $roomID = $complaint['roomID'] ?? '';
            if (!empty($roomID)) {
                $roomDoc = firestore_get('Rooms', $roomID, $idToken);
                if (isset($roomDoc['fields']['roomNumber'])) {
                    $roomNumber = reset($roomDoc['fields']['roomNumber']);
                }
            }
        } else {
            $error = "Complaint not found.";
        }

        // Fetch maintenance staff
        $users_collection = firestore_get_collection('Users', $idToken);
        if (!isset($users_collection['error'])) {
            foreach ($users_collection as $user) {
                if (($user['role'] ?? '') === 'maintenance_staff') {
                    $maintenanceStaff[] = $user;
                }
            }
        } else {
            $error = "Could not load maintenance staff.";
        }
    } catch (Exception $e) {
        $error = "An error occurred while loading data: " . $e->getMessage();
    }
}

// Check if work order already exists for this complaint
$workOrderExists = false;
try {
    $workorders_collection = firestore_get_collection('WorkOrders', $idToken);
    if (!isset($workorders_collection['error'])) {
        foreach ($workorders_collection as $workOrder) {
            if (($workOrder['complaintID'] ?? '') === $complaintID) {
                $workOrderExists = true;
                break;
            }
        }
    }
} catch (Exception $e) {
    // Ignore error for now
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($complaintID)) {
    $mainStaffID = $_POST['mainStaffID'] ?? '';

    if (empty($mainStaffID)) {
        $error = "Please select a maintenance staff member.";
    } elseif ($workOrderExists) {
        $error = "A work order already exists for this complaint.";
    } else {
        try {
            // Create WorkOrder
            $workOrderData = [
                'complaintID' => $complaintID,
                'wardenID' => $wardenUserID,
                'resolutionStatus' => false,
                'createAt' => date('Y-m-d H:i:s'),
                'imagePath' => '',
                'mainStaffID' => $mainStaffID
            ];

            $result = firestore_add('WorkOrders', $workOrderData, $idToken);
            if (isset($result['name'])) {
                // Update complaint status
                $updateData = [
                    'status' => 'work order created'
                ];
                firestore_set('Complaints', $complaintID, $updateData, $idToken);
                $success = "Work Order created successfully!";
                $workOrderExists = true; // Update flag after successful creation
            } else {
                $error = "Failed to create Work Order: " . ($result['error']['message'] ?? 'Unknown error');
            }
        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<link rel="stylesheet" href="../../assets/form_style.css">
<link rel="stylesheet" href="../../assets/header_style.css">

<div class="form-wrapper">
    <div class="form-header">
        <a href="javascript:history.back()" class="back-icon-link">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2>Create New Work Order</h2>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="message success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (!empty($complaint)): ?>
        <!-- Complaint Summary -->
        <div class="complaint-summary">
            <h3>Complaint Summary</h3>
            <p><strong>Complaint ID:</strong> <?php echo htmlspecialchars($complaintID); ?></p>
            <p><strong>Student Name:</strong> <?php echo htmlspecialchars($studentName); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($hostelName . ' - Room ' . $roomNumber); ?></p>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($complaint['description'] ?? ''); ?></p>
            <?php if (!empty($complaint['imagePath'])): ?>
                <p><strong>Image:</strong> <img src="<?php echo htmlspecialchars($complaint['imagePath']); ?>" alt="Complaint Image" style="max-width: 200px;"></p>
            <?php endif; ?>
        </div>

        <?php if ($workOrderExists): ?>
            <div class="message info">
                <p>A work order has already been created for this complaint. You can view it in the <a href="workorders.php">Work Orders</a> page.</p>
            </div>
        <?php else: ?>
            <!-- Work Order Form -->
            <form action="" method="POST">
                <h3>Work Order Assignment</h3>

                <label for="mainStaffID">Assign to Staff</label>
                <select id="mainStaffID" name="mainStaffID" required>
                    <option value="">Select Maintenance Staff</option>
                    <?php foreach ($maintenanceStaff as $staff): ?>
                        <option value="<?php echo htmlspecialchars($staff['id'] ?? ''); ?>">
                            <?php echo htmlspecialchars($staff['name'] ?? 'Unknown'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-submit">Create Work Order</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include('../../inc/footer_private.php'); ?>
