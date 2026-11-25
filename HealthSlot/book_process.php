<?php
include_once("session.php");
require_role(['resident']);
include_once("connection.php");
include_once("functions.php");

date_default_timezone_set('Asia/Manila');

if (!isset($_POST['book'])) {
    header("Location: resident_services.php");
    exit;
}

$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$residentid = (int)$_SESSION['userID'];

$list = ($type === 'vaccination') ? 'resident_vaccination.php' : 'resident_services.php';
$form = 'resident_book.php?type=' . urlencode($type) . '&id=' . $id;

// Goes back to the booking form with a message and keeps what the resident typed
function backToForm($conn, $message, $form) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['book_error'] = $message;
    $_SESSION['book_old'] = $_POST;
    header("Location: " . $form);
    exit;
}

// Goes back to the list of sessions with a message
function backToList($conn, $message, $list) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['res_error'] = $message;
    header("Location: " . $list);
    exit;
}

if (!in_array($type, ['service', 'vaccination']) || $id < 1) {
    backToList($conn, 'Invalid booking request.', 'resident_services.php');
}

$time = trim($_POST['appointment_time'] ?? '');
$pname = preg_replace('/\s+/', ' ', trim($_POST['patient_name'] ?? ''));
$ageRaw = trim($_POST['patient_age'] ?? '');
$address = trim($_POST['patient_address'] ?? '');
$contact = trim($_POST['patient_contact'] ?? '');
$cname = trim($_POST['companion_name'] ?? '');
$ccontact = trim($_POST['companion_contact'] ?? '');

if ($time === '' || $pname === '' || $ageRaw === '' || $address === '' || $contact === '') {
    backToForm($conn, 'Please fill in all required fields.', $form);
}
if (!ctype_digit($ageRaw) || (int)$ageRaw > 120) {
    backToForm($conn, 'Please enter a valid age.', $form);
}
$age = (int)$ageRaw;

if (mb_strlen($pname) < 2 || mb_strlen($pname) > 100) {
    backToForm($conn, 'Please enter the full name of the patient.', $form);
}
if (mb_strlen($address) > 255) {
    backToForm($conn, 'The address is too long.', $form);
}
if (!preg_match('/^\+?[0-9]{10,13}$/', $contact)) {
    backToForm($conn, 'Contact number must be 10 to 13 digits.', $form);
}
if ($age < 18 && $cname === '') {
    backToForm($conn, 'A companion is required for patients under 18.', $form);
}
if (mb_strlen($cname) > 100) {
    backToForm($conn, 'The companion name is too long.', $form);
}
if ($ccontact !== '' && !preg_match('/^\+?[0-9]{10,13}$/', $ccontact)) {
    backToForm($conn, 'Companion contact number must be 10 to 13 digits.', $form);
}

$cname = ($cname === '') ? null : $cname;
$ccontact = ($ccontact === '') ? null : $ccontact;

try {
    // The transaction and the row lock handle one booking per session at a time.
    // This keeps the slot counts and the queue numbers correct when two people book together.
    $conn->beginTransaction();

    $s = getSession($conn, $type, $id, true);

    if (!$s || !(int)$s['is_open']) {
        backToList($conn, 'This session is no longer available.', $list);
    }

    if (!in_array($time, timeSlots($s['session_start'], $s['session_end']))) {
        backToForm($conn, 'Please choose one of the available times.', $form);
    }

    if (strtotime($s['session_date'] . ' ' . $time) <= time()) {
        backToForm($conn, 'That time has already passed. Please choose a later time.', $form);
    }

    if (countTaken($conn, $type, $id) >= (int)$s['capacity']) {
        backToList($conn, 'Sorry, this session is already full.', $list);
    }

    // Hourly limit and the slots held for senior citizens
    $isPriority = ($age >= PRIORITY_AGE) ? 1 : 0;
    $av = slotAvailability($conn, $type, $id, $s, $time);
    $room = $isPriority ? $av['left_priority'] : $av['left_regular'];
    if ($room <= 0) {
        if (!$isPriority && $av['left_priority'] > 0) {
            backToForm($conn, 'The remaining slots for that hour are held for senior citizens. Please choose another hour.', $form);
        }
        backToForm($conn, 'That time is already full. Please choose another time.', $form);
    }

    if (hasSameServiceSameDay($conn, $pname, $age, $type, $s['name'], $s['session_date'])) {
        backToForm($conn, 'This patient already has an appointment for this service on this day. Please contact the health center if you need to reschedule or add another concern.', $form);
    }

    $conflict = findConflict($conn, $pname, $age, $s['session_date'], $time);
    if ($conflict === 'overlap') {
        backToForm($conn, 'This patient already has an appointment at that time.', $form);
    }
    if ($conflict === 'close') {
        backToForm($conn, 'This patient already has another appointment too close to that time. Please choose a time at least ' . BUFFER_MINUTES . ' minutes apart.', $form);
    }

    $queueNo = nextQueueNumber($conn, $type, $id, $time, $isPriority);

    // Confirmed right away, so it takes a slot immediately
    $column = ($type === 'service') ? 'serviceid' : 'vaccination_id';
    $sql = $conn->prepare("INSERT INTO appointment_tbl
        (residentid, status, $column, appointment_time, queue_no, is_priority,
         patient_name, patient_age, patient_address, patient_contact, companion_name, companion_contact)
        VALUES
        (:rid, 'confirmed', :sid, :time, :queue, :prio,
         :pname, :age, :address, :contact, :cname, :ccontact)");
    $sql->execute([
        ':rid' => $residentid,
        ':sid' => $id,
        ':time' => $time,
        ':queue' => $queueNo,
        ':prio' => $isPriority,
        ':pname' => $pname,
        ':age' => $age,
        ':address' => $address,
        ':contact' => $contact,
        ':cname' => $cname,
        ':ccontact' => $ccontact
    ]);

    $conn->commit();

    $_SESSION['res_success'] = 'Your booking is confirmed. Your queue number is ' . queueLabel($queueNo, $isPriority)
        . ' for ' . date('g:i A', strtotime($time)) . ($isPriority ? ' (priority lane).' : '.');
    header("Location: resident_appointments.php");
    exit;

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    backToForm($conn, 'Could not complete the booking: ' . $e->getMessage(), $form);
}
?>