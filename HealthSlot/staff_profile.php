<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");

$isAdmin = ($_SESSION['role'] === 'admin');

$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_success']);

// Admins and staff are both rows in staff_tbl
$sql = $conn->prepare("SELECT * FROM staff_tbl WHERE staffid = :id");
$sql->execute([':id' => (int)$_SESSION['userID']]);
$r = $sql->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    header("Location: logout.php");
    exit;
}

$fullName = trim($r['fname'] . ' ' . $r['mname'] . ' ' . $r['lname']);
if ($fullName === '') { $fullName = 'Administrator'; }

$initial = strtoupper(mb_substr($r['fname'] !== '' ? $r['fname'] : $fullName, 0, 1));

$notSet = 'Not set';

// Age is computed from the birthday so it never goes out of date
$age = $notSet;
$birthdayText = $notSet;
if (!empty($r['birthday'])) {
    $age = (new DateTime($r['birthday']))->diff(new DateTime('today'))->y;
    $birthdayText = date('F j, Y', strtotime($r['birthday']));
}

$photoPath = 'pictures/' . basename((string)$r['photo']);
$hasPhoto = ($r['photo'] !== '' && is_file($photoPath));

// Left column, under the photo
$account = [
    'Full Name' => $fullName,
    'Username' => $r['uname'],
    'Email' => $r['email'] !== '' ? $r['email'] : $notSet,
    'Role' => $isAdmin ? 'Administrator' : 'Staff'
];
$account['Status'] = ucfirst($r['status']);

// Right column
$details = [
    'Age' => $age,
    'Birthday' => $birthdayText,
    'Sex' => $r['sex'] !== '' ? ucfirst($r['sex']) : $notSet,
    'Address' => $r['address'] !== '' ? $r['address'] : $notSet,
    'Contact No.' => $r['contactnum'] !== '' ? $r['contactnum'] : $notSet
];

// Prints rows with the label on the left and the value on the right
function infoRows($items) {
    echo '<div class="profile-info">';
    foreach ($items as $label => $value) {
        echo '<div class="info-row">';
        echo '<span class="info-label">' . htmlspecialchars($label) . '</span>';
        echo '<span class="info-value">' . htmlspecialchars((string)$value) . '</span>';
        echo '</div>';
    }
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>My Profile</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<div class="page-head">
    <div>
        <h2>My Profile</h2>
        <h3>Your personal information</h3>
    </div>
    <div class="head-actions">
        <a class="btn" href="staff_profile_update.php">Edit Profile</a>
        <a class="btn btn-outline" href="staff_password.php">Change Password</a>
    </div>
</div>

<?php if ($success !== ''): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>

<div class="card">
    <div class="profile-split">

        <div class="col-photo">
            <?php if ($hasPhoto): ?>
                <img class="profile-photo" src="<?php echo htmlspecialchars($photoPath); ?>" alt="Profile photo">
            <?php else: ?>
                <div class="profile-photo profile-photo-empty"><?php echo htmlspecialchars($initial); ?></div>
            <?php endif; ?>
            <h4>Account Information</h4>
            <?php infoRows($account); ?>
        </div>

        <div class="col-details">
            <h4>Personal Details</h4>
            <?php infoRows($details); ?>
        </div>

    </div>

    <p class="muted" style="margin: 22px 0 0;">
        <?php echo $isAdmin
            ? 'Staff accounts are created and managed from Staff Accounts.'
            : 'Your name, username, email and contact number are managed by the administrator. Ask the administrator if they need to change.'; ?>
    </p>
</div>

</body>
</html>
