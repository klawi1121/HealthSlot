<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");

date_default_timezone_set('Asia/Manila');
$isAdmin = ($_SESSION['role'] === 'admin');
$sid = (int)($_SESSION['userID'] ?? 0);

// Admins and staff are both rows in staff_tbl
$sql = $conn->prepare("SELECT * FROM staff_tbl WHERE staffid = :id");
$sql->execute([':id' => $sid]);
$r = $sql->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    header("Location: logout.php");
    exit;
}

$error = '';

if (isset($_POST['update_profile'])) {
    $birthday = trim($_POST['birthday'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $sex = trim($_POST['sex'] ?? '');

    // The admin has no one above them, so the admin can also edit their own name, email and contact.
    // For staff these stay as the administrator set them.
    $fname = $r['fname'];
    $mname = $r['mname'];
    $lname = $r['lname'];
    $email = $r['email'];
    $contact = $r['contactnum'];
    if ($isAdmin) {
        $fname = trim($_POST['fname'] ?? '');
        $mname = trim($_POST['mname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contactnum'] ?? '');
    }

    $bd = DateTime::createFromFormat('Y-m-d', $birthday);
    $validBirthday = $bd && $bd->format('Y-m-d') === $birthday && $birthday <= date('Y-m-d');

    if ($birthday === '' || $address === '' || $sex === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!$validBirthday) {
        $error = 'Please enter a valid birthday.';
    } elseif (!in_array($sex, ['male', 'female'])) {
        $error = 'Invalid selection for sex.';
    } elseif (mb_strlen($address) > 100) {
        $error = 'The address is too long.';
    } elseif ($isAdmin && ($fname === '' || $lname === '' || $email === '' || $contact === '')) {
        $error = 'Please fill in all required fields.';
    } elseif ($isAdmin && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($isAdmin && !preg_match('/^\+?[0-9]{10,13}$/', $contact)) {
        $error = 'Contact number must be 10 to 13 digits.';
    } elseif ($isAdmin && (mb_strlen($fname) > 50 || mb_strlen($mname) > 50 || mb_strlen($lname) > 50 || mb_strlen($email) > 100)) {
        $error = 'One of the fields is too long.';
    }

    // The email must not belong to a resident or to another staff or admin account
    if ($error === '' && $isAdmin) {
        $check = $conn->prepare("SELECT residentid FROM resident_tbl WHERE email = :e1
                                 UNION ALL
                                 SELECT staffid FROM staff_tbl WHERE email = :e2 AND staffid <> :id
                                 LIMIT 1");
        $check->execute([':e1' => $email, ':e2' => $email, ':id' => $sid]);
        if ($check->fetch()) {
            $error = 'That email is already registered.';
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
        $photoToSave = $newPhoto ?? $r['photo'];

        try {
            if ($isAdmin) {
                $sql = $conn->prepare("UPDATE staff_tbl SET
                        fname = :fname, mname = :mname, lname = :lname, email = :email,
                        contactnum = :contact, birthday = :birthday, sex = :sex,
                        address = :address, photo = :photo
                    WHERE staffid = :id");
                $sql->execute([
                    ':fname' => $fname,
                    ':mname' => $mname,
                    ':lname' => $lname,
                    ':email' => $email,
                    ':contact' => $contact,
                    ':birthday' => $birthday,
                    ':sex' => $sex,
                    ':address' => $address,
                    ':photo' => $photoToSave,
                    ':id' => $sid
                ]);
            } else {
                $sql = $conn->prepare("UPDATE staff_tbl SET
                        birthday = :birthday, sex = :sex, address = :address, photo = :photo
                    WHERE staffid = :id");
                $sql->execute([
                    ':birthday' => $birthday,
                    ':sex' => $sex,
                    ':address' => $address,
                    ':photo' => $photoToSave,
                    ':id' => $sid
                ]);
            }

            // Remove the old photo file after the new one is saved
            if ($newPhoto !== null && $r['photo'] !== '') {
                $old = 'pictures/' . basename($r['photo']);
                if (is_file($old)) { unlink($old); }
            }

            $_SESSION['profile_success'] = 'Your profile was updated.';
            header("Location: staff_profile.php");
            exit;
        } catch (PDOException $e) {
            $error = 'Could not update your profile: ' . $e->getMessage();
        }
    }

    // On error, keep what was typed
    foreach (['birthday', 'address', 'sex'] as $key) {
        if (isset($_POST[$key])) { $r[$key] = trim($_POST[$key]); }
    }
    if ($isAdmin) {
        foreach (['fname', 'mname', 'lname', 'email', 'contactnum'] as $key) {
            if (isset($_POST[$key])) { $r[$key] = trim($_POST[$key]); }
        }
    }
}

// Fields the person cannot change are shown greyed out and read-only
$lockedStyle = 'background:#f1f5f9; color:#64748b; cursor:not-allowed;';
$nameLocked = $isAdmin ? '' : 'readonly';
$nameStyle = $isAdmin ? '' : 'style="' . $lockedStyle . '"';
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

    <?php if (!$isAdmin): ?>
        <p class="muted">Greyed-out fields are managed by the administrator.</p>
    <?php endif; ?>

    <form action="staff_profile_update.php" method="post" enctype="multipart/form-data">
        <div class="grid-2">
            <div class="field">
                <label for="fname">First name</label>
                <input type="text" id="fname" name="fname" maxlength="50" value="<?php echo htmlspecialchars($r['fname']); ?>" <?php echo $nameLocked; ?> <?php echo $nameStyle; ?> <?php echo $isAdmin ? 'required' : ''; ?>>
            </div>
            <div class="field">
                <label for="mname">Middle name</label>
                <input type="text" id="mname" name="mname" maxlength="50" value="<?php echo htmlspecialchars($r['mname']); ?>" <?php echo $nameLocked; ?> <?php echo $nameStyle; ?>>
            </div>
            <div class="field">
                <label for="lname">Last name</label>
                <input type="text" id="lname" name="lname" maxlength="50" value="<?php echo htmlspecialchars($r['lname']); ?>" <?php echo $nameLocked; ?> <?php echo $nameStyle; ?> <?php echo $isAdmin ? 'required' : ''; ?>>
            </div>
            <div class="field">
                <label for="uname">Username</label>
                <input type="text" id="uname" value="<?php echo htmlspecialchars($r['uname']); ?>" readonly style="<?php echo $lockedStyle; ?>">
            </div>
            <div class="field span-2">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="100" value="<?php echo htmlspecialchars($r['email']); ?>" <?php echo $nameLocked; ?> <?php echo $nameStyle; ?> <?php echo $isAdmin ? 'required' : ''; ?>>
            </div>
            <div class="field">
                <label for="birthday">Birthdate</label>
                <input type="date" id="birthday" name="birthday" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars((string)$r['birthday']); ?>" required>
            </div>
            <div class="field">
                <label for="sex">Sex</label>
                <select id="sex" name="sex" required>
                    <option value="" <?php echo ($r['sex'] === '') ? 'selected' : ''; ?> disabled>Select</option>
                    <option value="male" <?php echo ($r['sex'] === 'male') ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo ($r['sex'] === 'female') ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            <div class="field">
                <label for="address">Address or purok</label>
                <input type="text" id="address" name="address" maxlength="100" value="<?php echo htmlspecialchars($r['address']); ?>" required>
            </div>
            <div class="field">
                <label for="contactnum">Contact number</label>
                <input type="text" id="contactnum" name="contactnum" maxlength="15" value="<?php echo htmlspecialchars($r['contactnum']); ?>" <?php echo $nameLocked; ?> <?php echo $nameStyle; ?> <?php echo $isAdmin ? 'required' : ''; ?>>
            </div>
            <div class="field span-2">
                <label for="photo">New photo (optional)</label>
                <input type="file" id="photo" name="photo" accept="image/*">
            </div>
        </div>
        <div class="button-row" style="margin-top: 16px;">
            <input type="submit" name="update_profile" value="Save changes">
            <a class="btn btn-outline" href="staff_profile.php">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>
