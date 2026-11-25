<?php
include_once("session.php");
require_role(['admin']);
include_once("connection.php");

$success = $_SESSION['staff_success'] ?? '';
$error = $_SESSION['staff_error'] ?? '';
unset($_SESSION['staff_success'], $_SESSION['staff_error']);

$staff = $conn->query("SELECT staffid, fname, lname, email, uname, contactnum, status
                       FROM staff_tbl WHERE role = 'staff' ORDER BY lname, fname")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Staff Accounts</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<div class="page-head">
    <div>
        <h2>Staff Accounts</h2>
        <h3>Create and manage health center staff accounts.</h3>
    </div>
    <a class="btn" href="admin_staff_account_form.php">Add staff account</a>
</div>

<?php if ($success !== ''): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <h4>All staff accounts</h4>
    <table class="records">
        <thead>
            <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Contact No.</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($staff) === 0): ?>
            <tr><td colspan="6">No staff accounts yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($staff as $s): ?>
            <?php $newStatus = ($s['status'] === 'active') ? 'inactive' : 'active'; ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($s['fname'] . ' ' . $s['lname']); ?></strong></td>
                <td><?php echo htmlspecialchars($s['uname']); ?></td>
                <td><?php echo htmlspecialchars($s['email']); ?></td>
                <td><?php echo htmlspecialchars($s['contactnum']); ?></td>
                <td>
                    <span class="pill pill-<?php echo ($s['status'] === 'active') ? 'available' : 'cancelled'; ?>"><?php echo ucfirst($s['status']); ?></span>
                </td>
                <td>
                    <div class="action-buttons">
                        <form action="staff_account_process.php" method="post"
                              onsubmit="return confirm('Change this account to <?php echo $newStatus; ?>?');">
                            <input type="hidden" name="staffid" value="<?php echo (int)$s['staffid']; ?>">
                            <input type="hidden" name="status" value="<?php echo $newStatus; ?>">
                            <input type="submit" name="toggle_staff" value="<?php echo ($newStatus === 'inactive') ? 'Deactivate' : 'Activate'; ?>">
                        </form>
                        <form action="staff_account_process.php" method="post"
                              onsubmit="return confirm('Give this account full administrator access? It will move to Admin accounts.');">
                            <input type="hidden" name="staffid" value="<?php echo (int)$s['staffid']; ?>">
                            <input type="hidden" name="role" value="admin">
                            <input type="submit" name="change_role" value="Make admin">
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
