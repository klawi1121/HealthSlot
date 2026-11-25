<?php
include_once("session.php");
require_role(['admin']);
include_once("connection.php");
include_once("functions.php");

// Goes back to the form with the message. Passwords are not kept.
function fail($message, $redirect = null) {
    if ($redirect === null) {
        $redirect = (($_POST['role'] ?? 'staff') === 'admin') ? 'admin_admin_account_form.php' : 'admin_staff_account_form.php';
    }
    $_SESSION['staff_error'] = $message;
    $old = $_POST;
    unset($old['pword'], $old['cpword']);
    $_SESSION['staff_old'] = $old;
    header("Location: " . $redirect);
    exit;
}

// Goes back to the accounts list with the message (nothing is kept for the form)
function failList($message, $role = 'staff') {
    $_SESSION['staff_error'] = $message;
    header("Location: " . listPage($role));
    exit;
}

// Staff and admins have separate list pages
function listPage($role) {
    return ($role === 'admin') ? 'admin_admin_accounts.php' : 'admin_staff_accounts.php';
}

// How many administrators can currently log in
function activeAdminCount($conn) {
    return (int)$conn->query("SELECT COUNT(*) FROM staff_tbl WHERE role = 'admin' AND status = 'active'")->fetchColumn();
}

// ADD STAFF OR ADMIN
if (isset($_POST['add_staff'])) {
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $uname = trim($_POST['uname'] ?? '');
    $contact = trim($_POST['contactnum'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    $pword = $_POST['pword'] ?? '';
    $cpword = $_POST['cpword'] ?? '';

    if ($fname === '' || $lname === '' || $email === '' || $uname === '' || $contact === '' || $pword === '') {
        fail('Please fill in all fields.');
    }
    if (!in_array($role, ['staff', 'admin'])) {
        fail('Invalid role.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('Please enter a valid email address.');
    }
    if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $uname)) {
        fail('Username must be 4 to 30 characters: letters, numbers, underscore, or period.');
    }
    // "admin" is the first administrator's username, so nobody else can take it
    if (strcasecmp($uname, 'admin') === 0) {
        fail('That username is reserved.');
    }
    if (!preg_match('/^\+?[0-9]{10,13}$/', $contact)) {
        fail('Contact number must be 10 to 13 digits.');
    }
    if (strlen($pword) < 8) {
        fail('Password must be at least 8 characters.');
    }
    if ($pword !== $cpword) {
        fail('Passwords do not match.');
    }

    // Username and email must be unused in BOTH staff_tbl and resident_tbl
    $check = $conn->prepare("SELECT uname FROM staff_tbl WHERE uname = :u1 OR email = :e1
                             UNION ALL
                             SELECT uname FROM resident_tbl WHERE uname = :u2 OR email = :e2
                             LIMIT 1");
    $check->execute([':u1' => $uname, ':e1' => $email, ':u2' => $uname, ':e2' => $email]);
    if ($check->fetch()) {
        fail('That username or email is already registered.');
    }

    $label = ($role === 'admin') ? 'admin' : 'staff';

    try {
        $sql = $conn->prepare("INSERT INTO staff_tbl (fname, lname, email, uname, contactnum, password, status, role)
                               VALUES (:fname, :lname, :email, :uname, :contact, :password, 'active', :role)");
        $sql->execute([
            ':fname' => $fname,
            ':lname' => $lname,
            ':email' => $email,
            ':uname' => $uname,
            ':contact' => $contact,
            ':password' => password_hash($pword, PASSWORD_DEFAULT),
            ':role' => $role
        ]);
        logActivity($conn, 'staff_account', 'Created ' . $label . ' account', $fname . ' ' . $lname . ' (' . $uname . ')');
        $_SESSION['staff_success'] = ucfirst($label) . ' account created.';
    } catch (PDOException $e) {
        fail('Could not create the account: ' . $e->getMessage());
    }
    header("Location: " . listPage($role));
    exit;
}

// ACTIVATE / DEACTIVATE
if (isset($_POST['toggle_staff'])) {
    $id = (int)($_POST['staffid'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($id < 1 || !in_array($status, ['active', 'inactive'])) {
        failList('Invalid request.');
    }

    $info = $conn->prepare("SELECT fname, lname, uname, role, status FROM staff_tbl WHERE staffid = :id");
    $info->execute([':id' => $id]);
    $person = $info->fetch(PDO::FETCH_ASSOC);
    if (!$person) {
        failList('Account not found.');
    }
    $page = listPage($person['role']);

    if ($status === 'inactive') {
        // The built-in admin account can never be deactivated
        if ($person['uname'] === 'admin') {
            failList('The main admin account cannot be deactivated.', 'admin');
        }
        if ($id === (int)$_SESSION['userID']) {
            failList('You cannot deactivate your own account.', $person['role']);
        }
        if ($person['role'] === 'admin' && $person['status'] === 'active' && activeAdminCount($conn) <= 1) {
            failList('At least one active administrator is required.', 'admin');
        }
    }

    $sql = $conn->prepare("UPDATE staff_tbl SET status = :status WHERE staffid = :id");
    $sql->execute([':status' => $status, ':id' => $id]);

    $label = ($person['role'] === 'admin') ? 'admin' : 'staff';
    logActivity($conn, 'staff_account', ($status === 'active') ? 'Activated ' . $label . ' account' : 'Deactivated ' . $label . ' account',
        $person['fname'] . ' ' . $person['lname'] . ' (' . $person['uname'] . ')');

    $_SESSION['staff_success'] = 'Account is now ' . $status . '.';
    header("Location: " . $page);
    exit;
}

// MAKE ADMIN / MAKE STAFF
if (isset($_POST['change_role'])) {
    $id = (int)($_POST['staffid'] ?? 0);
    $role = $_POST['role'] ?? '';

    if ($id < 1 || !in_array($role, ['staff', 'admin'])) {
        failList('Invalid request.');
    }

    $info = $conn->prepare("SELECT fname, lname, uname, role, status FROM staff_tbl WHERE staffid = :id");
    $info->execute([':id' => $id]);
    $person = $info->fetch(PDO::FETCH_ASSOC);
    if (!$person) {
        failList('Account not found.');
    }
    $page = listPage($person['role']);
    if ($person['role'] === $role) {
        failList('That account already has this role.', $person['role']);
    }

    if ($role === 'staff') {
        // The built-in admin account always stays an administrator
        if ($person['uname'] === 'admin') {
            failList('The main admin account cannot be changed to staff.', 'admin');
        }
        if ($id === (int)$_SESSION['userID']) {
            failList('You cannot remove your own admin access.', 'admin');
        }
        if ($person['status'] === 'active' && activeAdminCount($conn) <= 1) {
            failList('At least one active administrator is required.', 'admin');
        }
    }

    $sql = $conn->prepare("UPDATE staff_tbl SET role = :role WHERE staffid = :id");
    $sql->execute([':role' => $role, ':id' => $id]);

    logActivity($conn, 'staff_account', ($role === 'admin') ? 'Made account an admin' : 'Made account a staff',
        $person['fname'] . ' ' . $person['lname'] . ' (' . $person['uname'] . ')');

    $_SESSION['staff_success'] = ($role === 'admin') ? 'The account is now an administrator.' : 'The account is now a staff account.';
    header("Location: " . $page);
    exit;
}

header("Location: admin_staff_accounts.php");
exit;
?>
