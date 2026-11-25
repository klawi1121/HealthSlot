<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");
include_once("functions.php");

date_default_timezone_set('Asia/Manila');

// Change these if your pages have different names
$bookPage = 'book_for_resident.php';
$requestsPage = 'booking_request.php';

if (!isset($_POST['book'])) {
    header("Location: " . $bookPage);
    exit;
}

$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$form = $bookPage . '?type=' . urlencode($type) . '&id=' . $id;

// The hardcoded admin has no row in staff_tbl, so encoded_by and handled_by stay empty for the admin
$staffId = ($_SESSION['role'] === 'staff') ? (int)$_SESSION['userID'] : null;

function backToForm($conn, $message, $form) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['sbook_error'] = $message;
    $_SESSION['sbook_old'] = $_POST;
    header("Location: " . $form);
    exit;
}

function backToList($conn, $message, $bookPage) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['sbook_error'] = $message;
    header("Location: " . $bookPage);
    exit;
}

if (!in_array($type, ['service', 'vaccination']) || $id < 1) {
    backToList($conn, 'Invalid booking request.', $bookPage);
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
$override = isset($_POST['allow_same_day']);

try {
    $conn->beginTransaction();

    $s = getSession($conn, $type, $id, true);

    if (!$s || !(int)$s['is_open']) {
        backToList($conn, 'That session is no longer available.', $bookPage);
    }

    if (!in_array($time, timeSlots($s['session_start'], $s['session_end']))) {
        backToForm($conn, 'Please choose one of the available times.', $form);
    }

    if (strtotime($s['session_date'] . ' ' . $time) <= time()) {
        backToForm($conn, 'That time has already passed. Please choose a later time.', $form);
    }

    if (countTaken($conn, $type, $id) >= (int)$s['capacity']) {
        backToList($conn, 'Sorry, that session is already full.', $bookPage);
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

    if (!$override && hasSameServiceSameDay($conn, $pname, $age, $type, $s['name'], $s['session_date'])) {
        backToForm($conn, 'This patient already has an appointment for this service on this day. If a second booking is really needed, tick the staff override box.', $form);
    }

    $conflict = findConflict($conn, $pname, $age, $s['session_date'], $time);
    if ($conflict === 'overlap') {
        backToForm($conn, 'This patient already has an appointment at that time.', $form);
    }
    if ($conflict === 'close') {
        backToForm($conn, 'This patient already has another appointment too close to that time. Please choose a time at least ' . BUFFER_MINUTES . ' minutes apart.', $form);
    }

    $queueNo = nextQueueNumber($conn, $type, $id, $time, $isPriority);

    // No resident account, entered by staff, and confirmed right away
    $column = ($type === 'service') ? 'serviceid' : 'vaccination_id';
    $sql = $conn->prepare("INSERT INTO appointment_tbl
        (residentid, booked_via, encoded_by, handled_by, status, $column, appointment_time, queue_no, is_priority,
         patient_name, patient_age, patient_address, patient_contact, companion_name, companion_contact)
        VALUES
        (NULL, 'staff', :enc, :handled, 'confirmed', :sid, :time, :queue, :prio,
         :pname, :age, :address, :contact, :cname, :ccontact)");
    $sql->execute([
        ':enc' => $staffId,
        ':handled' => $staffId,
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

    logActivity($conn, 'appointment', 'Booked appointment for a resident',
        $pname . ' - ' . $s['name'] . ' on ' . date('M d, Y', strtotime($s['session_date']))
        . ' (queue ' . queueLabel($queueNo, $isPriority) . ')' . ($override ? ' (same-day override)' : ''));

    $_SESSION['appt_success'] = 'Appointment booked for ' . $pname . '. Queue number: ' . queueLabel($queueNo, $isPriority)
        . ' at ' . date('g:i A', strtotime($time)) . '.';
    header("Location: " . $requestsPage);
    exit;

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    backToForm($conn, 'Could not complete the booking: ' . $e->getMessage(), $form);
}
?>