<?php
include_once("session.php");
require_role(['admin']);
include_once("connection.php");

$success = $_SESSION['staff_success'] ?? '';
$error = $_SESSION['staff_error'] ?? '';
unset($_SESSION['staff_success'], $_SESSION['staff_error']);

$myId = (int)$_SESSION['userID'];

// The built-in admin (username "admin") is listed first
$admins = $conn->query("SELECT staffid, fname, lname, email, uname, contactnum, status
                        FROM staff_tbl WHERE role = 'admin'
                        ORDER BY (uname = 'admin') DESC, lname, fname")->fetchAll(PDO::FETCH_ASSOC);

// Used to protect the last active admin from being locked out
$activeAdmins = 0;
foreach ($admins as $a) {
    if ($a['status'] === 'active') { $activeAdmins++; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Admin Accounts</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<div class="page-head">
    <div>
        <h2>Admin Accounts</h2>
        <h3>Administrators have full access to the system.</h3>
    </div>
    <a class="btn" href="admin_admin_account_form.php">Add admin account</a>
</div>

<?php if ($success !== ''): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <h4>All admin accounts</h4>
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
        <?php if (count($admins) === 0): ?>
            <tr><td colspan="6">No admin accounts yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($admins as $a): ?>
            <?php
            $newStatus = ($a['status'] === 'active') ? 'inactive' : 'active';
            $isMain = ($a['uname'] === 'admin');
            $isSelf = ((int)$a['staffid'] === $myId);
            $isLastAdmin = ($a['status'] === 'active' && $activeAdmins <= 1);

            if ($isMain) {
                $lockText = 'Main admin account';
            } elseif ($isSelf) {
                $lockText = 'Your own account';
            } elseif ($isLastAdmin) {
                $lockText = 'Last active admin';
            } else {
                $lockText = '';
            }
            ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($a['fname'] . ' ' . $a['lname']); ?></strong>
                    <?php if ($isSelf): ?><span class="cell-sub">This is you</span><?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($a['uname']); ?></td>
                <td><?php echo htmlspecialchars($a['email']); ?></td>
                <td><?php echo htmlspecialchars($a['contactnum']); ?></td>
                <td>
                    <span class="pill pill-<?php echo ($a['status'] === 'active') ? 'available' : 'cancelled'; ?>"><?php echo ucfirst($a['status']); ?></span>
                </td>
                <td>
                    <?php if ($lockText !== ''): ?>
                        <span class="muted"><?php echo $lockText; ?></span>
                    <?php else: ?>
                        <div class="action-buttons">
                            <form action="staff_account_process.php" method="post"
                                  onsubmit="return confirm('Change this account to <?php echo $newStatus; ?>?');">
                                <input type="hidden" name="staffid" value="<?php echo (int)$a['staffid']; ?>">
                                <input type="hidden" name="status" value="<?php echo $newStatus; ?>">
                                <input type="submit" name="toggle_staff" value="<?php echo ($newStatus === 'inactive') ? 'Deactivate' : 'Activate'; ?>">
                            </form>
                            <form action="staff_account_process.php" method="post"
                                  onsubmit="return confirm('Remove admin access and make this a staff account? It will move to Staff accounts.');">
                                <input type="hidden" name="staffid" value="<?php echo (int)$a['staffid']; ?>">
                                <input type="hidden" name="role" value="staff">
                                <input type="submit" name="change_role" value="Make staff">
                            </form>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
