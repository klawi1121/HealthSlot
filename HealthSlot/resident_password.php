<?php
include_once("session.php");
require_role(['resident']);
include_once("connection.php");

$rid = (int)$_SESSION['userID'];

$sql = $conn->prepare("SELECT password FROM resident_tbl WHERE residentid = :id");
$sql->execute([':id' => $rid]);
$r = $sql->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    header("Location: logout.php");
    exit;
}

$error = '';

if (isset($_POST['update_password'])) {
    $current = $_POST['current_pword'] ?? '';
    $newPass = $_POST['new_pword'] ?? '';
    $confirm = $_POST['confirm_pword'] ?? '';

    if ($current === '' || $newPass === '' || $confirm === '') {
        $error = 'Please fill in all password fields.';
    } elseif (!password_verify($current, $r['password'])) {
        $error = 'Your current password is incorrect.';
    } elseif (strlen($newPass) < 8) {
        $error = 'The new password must be at least 8 characters.';
    } elseif ($newPass !== $confirm) {
        $error = 'The new passwords do not match.';
    } else {
        $sql = $conn->prepare("UPDATE resident_tbl SET password = :p WHERE residentid = :id");
        $sql->execute([':p' => password_hash($newPass, PASSWORD_DEFAULT), ':id' => $rid]);
        $_SESSION['profile_success'] = 'Your password was updated.';
        header("Location: resident_profile_page.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Change Password</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Change Password</h2>
<h3>Use at least 8 characters for your new password.</h3>

<div class="card">
    <?php if ($error !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="resident_password.php" method="post">
        <div class="grid-2">
            <div class="field span-2">
                <label for="current_pword">Current password</label>
                <input type="password" id="current_pword" name="current_pword" required>
            </div>
            <div class="field">
                <label for="new_pword">New password</label>
                <input type="password" id="new_pword" name="new_pword" required>
            </div>
            <div class="field">
                <label for="confirm_pword">Confirm new password</label>
                <input type="password" id="confirm_pword" name="confirm_pword" required>
            </div>
        </div>
        <div class="button-row" style="margin-top: 16px;">
            <input type="submit" name="update_password" value="Update password">
            <a class="btn btn-outline" href="resident_profile_page.php">Back</a>
        </div>
    </form>
</div>

</body>
</html>