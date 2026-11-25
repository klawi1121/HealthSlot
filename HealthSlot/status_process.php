<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");
include_once("functions.php");

// Where to go back to, keeping only the known filter keys
function backUrl() {
    $allowed = ['search', 'date', 'type', 'status'];
    parse_str($_POST['back'] ?? '', $parsed);
    $keep = [];
    foreach ($allowed as $key) {
        if (isset($parsed[$key]) && is_string($parsed[$key]) && $parsed[$key] !== '') {
            $keep[$key] = $parsed[$key];
        }
    }
    $query = http_build_query($keep);
    return 'booking_request.php' . ($query !== '' ? '?' . $query : '');
}

if (!isset($_POST['action'], $_POST['appointmentid'])) {
    header("Location: booking_request.php");
    exit;
}

$id = (int)$_POST['appointmentid'];
$action = strtolower(trim($_POST['action']));

// action => [new status, statuses it can change from]
$rules = [
    'confirm' => ['confirmed', ['pending']],
    'complete' => ['completed', ['confirmed']],
    'missed' => ['missed', ['confirmed']],
    'cancel' => ['cancelled', ['pending', 'confirmed']]
];

if (!isset($rules[$action]) || $id < 1) {
    $_SESSION['appt_error'] = 'Invalid action.';
    header("Location: " . backUrl());
    exit;
}

$newStatus = $rules[$action][0];
$fromList = $rules[$action][1];

// The hardcoded admin has no row in staff_tbl, so handled_by stays NULL for admin
$handledBy = ($_SESSION['role'] === 'staff') ? (int)$_SESSION['userID'] : null;

$placeholders = [];
$params = [':new' => $newStatus, ':handled' => $handledBy, ':id' => $id];
foreach ($fromList as $i => $from) {
    $key = ':from' . $i;
    $placeholders[] = $key;
    $params[$key] = $from;
}

// A slot is taken only when staff confirm, so make sure the session still has room
if ($action === 'confirm') {
    $check = $conn->prepare("SELECT serviceid, vaccination_id FROM appointment_tbl WHERE appointmentid = :id");
    $check->execute([':id' => $id]);
    $appt = $check->fetch(PDO::FETCH_ASSOC);

    if ($appt) {
        $type = ($appt['serviceid'] !== null) ? 'service' : 'vaccination';
        $sid = (int)($appt['serviceid'] ?? $appt['vaccination_id']);
        $s = getSession($conn, $type, $sid);

        if (!$s || !(int)$s['is_open']) {
            $_SESSION['appt_error'] = 'This session is no longer open, so the booking cannot be confirmed.';
            header("Location: " . backUrl());
            exit;
        }
        if (countTaken($conn, $type, $sid) >= (int)$s['capacity']) {
            $_SESSION['appt_error'] = 'This session is already full. Cancel another booking first, or ask the resident to choose another session.';
            header("Location: " . backUrl());
            exit;
        }
    }
}

try {
    $sql = $conn->prepare("UPDATE appointment_tbl
        SET status = :new, handled_by = :handled
        WHERE appointmentid = :id AND status IN (" . implode(',', $placeholders) . ")");
    $sql->execute($params);

    if ($sql->rowCount() > 0) {
        $_SESSION['appt_success'] = 'Appointment ' . $newStatus . '.';
            $info = $conn->prepare("SELECT a.patient_name, COALESCE(s.servicename, v.vaccine_name) AS session_name
            FROM appointment_tbl a
            LEFT JOIN service_tbl s ON s.serviceid = a.serviceid
            LEFT JOIN vaccination_schedules v ON v.vaccination_id = a.vaccination_id
            WHERE a.appointmentid = :id");
        $info->execute([':id' => $id]);
        $appt = $info->fetch(PDO::FETCH_ASSOC);

        $labels = [
            'confirm' => 'Confirmed appointment',
            'complete' => 'Completed appointment',
            'missed' => 'Marked appointment as missed',
            'cancel' => 'Cancelled appointment'
        ];
        logActivity($conn, 'appointment', $labels[$action],
            $appt ? $appt['patient_name'] . ' - ' . $appt['session_name'] : 'Appointment #' . $id);
    } else {
        $_SESSION['appt_error'] = 'This appointment could not be updated. It may have already changed.';
    }
} catch (PDOException $e) {
    $_SESSION['appt_error'] = 'Could not update the appointment.';
}

header("Location: " . backUrl());
exit;
?>