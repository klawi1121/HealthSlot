<?php
include_once("session.php");
require_role(['admin']);

// If the last submit failed, show what was typed (passwords are never kept)
$old = $_SESSION['staff_old'] ?? [];
$error = $_SESSION['staff_error'] ?? '';
unset($_SESSION['staff_old'], $_SESSION['staff_error']);

function val($key, $old) {
    return htmlspecialchars((string)($old[$key] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Add Admin Account</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Add admin account</h2>
<h3>Administrators have full access to the system. Use at least 8 characters for the password.</h3>

<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <form action="staff_account_process.php" method="post">
        <input type="hidden" name="role" value="admin">
        <div class="grid-2">
            <div class="field">
                <label for="fname">First name</label>
                <input type="text" id="fname" name="fname" maxlength="50" value="<?php echo val('fname', $old); ?>" required>
            </div>
            <div class="field">
                <label for="lname">Last name</label>
                <input type="text" id="lname" name="lname" maxlength="50" value="<?php echo val('lname', $old); ?>" required>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="100" value="<?php echo val('email', $old); ?>" required>
            </div>
            <div class="field">
                <label for="uname">Username</label>
                <input type="text" id="uname" name="uname" maxlength="30" value="<?php echo val('uname', $old); ?>" required>
            </div>
            <div class="field span-2">
                <label for="contactnum">Contact number</label>
                <input type="text" id="contactnum" name="contactnum" maxlength="15" value="<?php echo val('contactnum', $old); ?>" required>
            </div>
            <div class="field">
                <label for="pword">Password</label>
                <input type="password" id="pword" name="pword" required>
            </div>
            <div class="field">
                <label for="cpword">Confirm password</label>
                <input type="password" id="cpword" name="cpword" required>
            </div>
        </div>

        <div class="button-row" style="margin-top: 16px;">
            <input type="submit" name="add_staff" value="Create account">
            <a class="btn btn-outline" href="admin_admin_accounts.php">Back</a>
        </div>
    </form>
</div>

</body>
</html>