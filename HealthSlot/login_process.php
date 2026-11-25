<?php
session_start();
include_once("connection.php");

if (!isset($_POST['login'])) {
    header("Location: login.php");
    exit;
}

$input = trim($_POST['uname']);
$pword = $_POST['pword'];

if ($input === "" || $pword === "") {
    header("Location: login.php?error=1");
    exit;
}

//admin and staff (both are in staff_tbl, the role column tells them apart)
$sql = $conn->prepare("SELECT staffid, password, status, role FROM staff_tbl WHERE uname = :login1 OR email = :login2 LIMIT 1");
$sql->execute([':login1' => $input, ':login2' => $input]);
$staff = $sql->fetch(PDO::FETCH_ASSOC);

if ($staff && $staff['status'] === 'active' && password_verify($pword, $staff['password'])) {
    session_regenerate_id(true);
    $isAdmin = ($staff['role'] === 'admin');
    $_SESSION['role'] = $isAdmin ? 'admin' : 'staff';
    $_SESSION['userID'] = $staff['staffid'];
    header("Location: " . ($isAdmin ? "admin_dashboard.php" : "staff_dashboard.php"));
    exit;
}

//resident
$sql = $conn->prepare("SELECT residentid, password FROM resident_tbl WHERE uname = :login1 OR email = :login2 LIMIT 1");
$sql->execute([':login1' => $input, ':login2' => $input]);
$resident = $sql->fetch(PDO::FETCH_ASSOC);

if ($resident && password_verify($pword, $resident['password'])) {
    session_regenerate_id(true);
    $_SESSION['role'] = 'resident';
    $_SESSION['userID'] = $resident['residentid'];
    header("Location: resident_dashboard.php");
    exit;
}

header("Location: login.php?error=1");
exit;
?>
