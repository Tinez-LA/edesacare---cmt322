# Warden's Work Order Creation Form Implementation

## Tasks to Complete

- [x] Create user_management/warden/workorder-create.php
  - Accept complaintID via GET parameter
  - Fetch complaint details, student name, hostel name, room details
  - Display complaint summary (ID, student name, location, description, image if available)
  - Form with hidden fields: complaintID, wardenID, resolutionStatus=false, createAt=current timestamp, imagePath=''
  - Dropdown for MainStaffID: fetch active maintenance_staff from Users collection
  - On submit: Add new document to 'WorkOrders' collection, update complaint status to 'work order created'

- [x] Edit user_management/warden/report.php
  - Change the "View" button to link to workorder-create.php?complaintID=...

- [x] Test the form submission and Firestore operations
- [x] Verify that WorkOrders are created correctly and complaint status is updated
