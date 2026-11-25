<?php
include_once("session.php");
require_role(['resident']);
include_once("connection.php");

date_default_timezone_set('Asia/Manila');
$rid = (int)$_SESSION['userID'];

$sql = $conn->prepare("SELECT * FROM resident_tbl WHERE residentid = :id");
$sql->execute([':id' => $rid]);
$r = $sql->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    header("Location: logout.php");
    exit;
}

$error = '';

if (isset($_POST['update_profile'])) {
    $fname = trim($_POST['fname'] ?? '');
    $mname = trim($_POST['mname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $uname = trim($_POST['uname'] ?? '');
    $birthday = trim($_POST['birthday'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $contact = trim($_POST['contactnum'] ?? '');

    $bd = DateTime::createFromFormat('Y-m-d', $birthday);
    $validBirthday = $bd && $bd->format('Y-m-d') === $birthday && $birthday <= date('Y-m-d');

    if ($fname === '' || $lname === '' || $email === '' || $uname === '' ||
        $birthday === '' || $address === '' || $sex === '' || $contact === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $uname)) {
        $error = 'Username must be 4 to 30 characters: letters, numbers, underscore, or period.';
    } elseif (strcasecmp($uname, 'admin') === 0) {
        $error = 'That username is reserved.';
    } elseif (!$validBirthday) {
        $error = 'Please enter a valid birthday.';
    } elseif (!in_array($sex, ['male', 'female'])) {
        $error = 'Invalid selection for sex.';
    } elseif (!preg_match('/^\+?[0-9]{10,13}$/', $contact)) {
        $error = 'Contact number must be 10 to 13 digits.';
    } elseif (mb_strlen($fname) > 50 || mb_strlen($mname) > 50 || mb_strlen($lname) > 50 ||
              mb_strlen($email) > 100 || mb_strlen($address) > 100) {
        $error = 'One of the fields is too long.';
    }

    // Username and email must not belong to another resident or to any staff
    if ($error === '') {
        $check = $conn->prepare("SELECT residentid FROM resident_tbl
                                 WHERE (uname = :u1 OR email = :e1) AND residentid <> :id
                                 UNION ALL
                                 SELECT staffid FROM staff_tbl WHERE uname = :u2 OR email = :e2
                                 LIMIT 1");
        $check->execute([':u1' => $uname, ':e1' => $email, ':id' => $rid, ':u2' => $uname, ':e2' => $email]);
        if ($check->fetch()) {
            $error = 'That username or email is already registered.';
        }
    }

    // Optional new photo
    $newPhoto = null;
    if ($error === '' && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'The photo could not be uploaded.';
        } else {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $error = 'Only jpg, png and gif photos are allowed.';
            } elseif ($_FILES['photo']['size'] >= 1000000) {
                $error = 'The photo is too large (maximum 1 MB).';
            } elseif (getimagesize($_FILES['photo']['tmp_name']) === false) {
                $error = 'The file is not a valid image.';
            } else {
                $newPhoto = random_int(1000, 99999999) . '.' . $ext;
                if (!move_uploaded_file($_FILES['photo']['tmp_name'], 'pictures/' . $newPhoto)) {
                    $error = 'The photo could not be saved. Check that the pictures folder exists.';
                    $newPhoto = null;
                }
            }
        }
    }

    if ($error === '') {
        $age = (new DateTime($birthday))->diff(new DateTime('today'))->y;
        $photoToSave = $newPhoto ?? $r['photo'];

        try {
            $sql = $conn->prepare("UPDATE resident_tbl SET
                    fname = :fname, mname = :mname, lname = :lname, email = :email, uname = :uname,
                    age = :age, birthday = :birthday, address = :address, sex = :sex,
                    contactnum = :contact, photo = :photo
                WHERE residentid = :id");
            $sql->execute([
                ':fname' => $fname,
                ':mname' => $mname,
                ':lname' => $lname,
                ':email' => $email,
                ':uname' => $uname,
                ':age' => $age,
                ':birthday' => $birthday,
                ':address' => $address,
                ':sex' => $sex,
                ':contact' => $contact,
                ':photo' => $photoToSave,
                ':id' => $rid
            ]);

            // Remove the old photo file after the new one is saved
            if ($newPhoto !== null && $r['photo'] !== '') {
                $old = 'pictures/' . basename($r['photo']);
                if (is_file($old)) { unlink($old); }
            }

            $_SESSION['profile_success'] = 'Your profile was updated.';
            header("Location: resident_profile_page.php");
            exit;
        } catch (PDOException $e) {
            $error = 'Could not update your profile: ' . $e->getMessage();
        }
    }

    // On error, keep what the resident typed
    foreach (['fname', 'mname', 'lname', 'email', 'uname', 'birthday', 'address', 'sex', 'contactnum'] as $key) {
        if (isset($_POST[$key])) { $r[$key] = trim($_POST[$key]); }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Edit Profile</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Edit Profile</h2>
<h3>Only the details needed for scheduling are stored. No medical information is stored.</h3>

<div class="card">
    <h4>Personal details</h4>

    <?php if ($error !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="resident_profile_update.php" method="post" enctype="multipart/form-data">
        <div class="grid-2">
            <div class="field">
                <label for="fname">First name</label>
                <input type="text" id="fname" name="fname" maxlength="50" value="<?php echo htmlspecialchars($r['fname']); ?>" required>
            </div>
            <div class="field">
                <label for="mname">Middle name</label>
                <input type="text" id="mname" name="mname" maxlength="50" value="<?php echo htmlspecialchars($r['mname']); ?>">
            </div>
            <div class="field">
                <label for="lname">Last name</label>
                <input type="text" id="lname" name="lname" maxlength="50" value="<?php echo htmlspecialchars($r['lname']); ?>" required>
            </div>
            <div class="field">
                <label for="uname">Username</label>
                <input type="text" id="uname" name="uname" maxlength="30" value="<?php echo htmlspecialchars($r['uname']); ?>" required>
            </div>
            <div class="field span-2">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="100" value="<?php echo htmlspecialchars($r['email']); ?>" required>
            </div>
            <div class="field">
                <label for="birthday">Birthdate</label>
                <input type="date" id="birthday" name="birthday" value="<?php echo htmlspecialchars($r['birthday']); ?>" required>
            </div>
            <div class="field">
                <label for="sex">Sex</label>
                <select id="sex" name="sex" required>
                    <option value="male" <?php echo ($r['sex'] === 'male') ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo ($r['sex'] === 'female') ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            <div class="field">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" maxlength="100" value="<?php echo htmlspecialchars($r['address']); ?>" required>
            </div>
            <div class="field">
                <label for="contactnum">Contact number</label>
                <input type="text" id="contactnum" name="contactnum" maxlength="15" value="<?php echo htmlspecialchars($r['contactnum']); ?>" required>
            </div>
            <div class="field span-2">
                <label for="photo">New photo (optional)</label>
                <input type="file" id="photo" name="photo" accept="image/*">
            </div>
        </div>
        <div class="button-row" style="margin-top: 16px;">
            <input type="submit" name="update_profile" value="Save changes">
            <a class="btn btn-outline" href="resident_profile_page.php">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>