<?php
include_once("session.php");
require_role(['resident']);
include_once("connection.php");

if (!isset($_POST['cancel_appointment'])) {
    header("Location: resident_appointments.php");
    exit;
}

$id = (int)($_POST['appointmentid'] ?? 0);
$residentid = (int)$_SESSION['userID'];

try {
    // residentid in the WHERE makes sure a resident can only cancel their own appointment
    $sql = $conn->prepare("UPDATE appointment_tbl SET status = 'cancelled'
        WHERE appointmentid = :id AND residentid = :rid AND status IN ('pending', 'confirmed')");
    $sql->execute([':id' => $id, ':rid' => $residentid]);

    if ($sql->rowCount() > 0) {
        $_SESSION['res_success'] = 'Your appointment was cancelled.';
    } else {
        $_SESSION['res_error'] = 'This appointment could not be cancelled.';
    }
} catch (PDOException $e) {
    $_SESSION['res_error'] = 'Could not cancel the appointment.';
}

header("Location: resident_appointments.php");
exit;
?>