<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");
include_once("functions.php");

date_default_timezone_set('Asia/Manila');

// Goes back to the form with the message and what was typed
function fail($message, $redirect = 'vaccination_form.php') {
    $_SESSION['vax_error'] = $message;
    $_SESSION['vax_old'] = $_POST;
    header("Location: " . $redirect);
    exit;
}

function nullable($value) {
    $value = trim($value ?? '');
    return $value === '' ? null : $value;
}

function readVaccination($redirect) {
    $d = [
        'vaccine' => trim($_POST['vaccine_name'] ?? ''),
        'date' => trim($_POST['vaccination_date'] ?? ''),
        'start' => trim($_POST['start_time'] ?? ''),
        'end' => trim($_POST['end_time'] ?? ''),
        'capacity' => (int)($_POST['capacity'] ?? 0),
        'location' => trim($_POST['location'] ?? ''),
        'description' => nullable($_POST['description'] ?? ''),
        'target' => nullable($_POST['target_beneficiaries'] ?? ''),
        'dose' => nullable($_POST['dose_information'] ?? ''),
        'requirements' => nullable($_POST['requirements'] ?? ''),
        'notes' => nullable($_POST['additional_notes'] ?? '')
    ];

    if ($d['vaccine'] === '' || $d['date'] === '' || $d['start'] === '' || $d['end'] === '' || $d['location'] === '') {
        fail('Please fill in all required fields.', $redirect);
    }
    if ($d['date'] < date('Y-m-d')) {
        fail('The vaccination date cannot be in the past.', $redirect);
    }
    if ($d['end'] <= $d['start']) {
        fail('The end time must be later than the start time.', $redirect);
    }
    if ($d['capacity'] < 1) {
        fail('Capacity must be at least 1.', $redirect);
    }
    if (mb_strlen($d['vaccine']) > 150 || mb_strlen($d['location']) > 255 ||
        mb_strlen($d['target'] ?? '') > 255 || mb_strlen($d['dose'] ?? '') > 100) {
        fail('One of the fields is too long.', $redirect);
    }
    return $d;
}

// ADD
if (isset($_POST['add_vaccination'])) {
    $d = readVaccination('vaccination_form.php');

    try {
        $sql = $conn->prepare("INSERT INTO vaccination_schedules
            (vaccine_name, description, target_beneficiaries, dose_information,
             vaccination_date, start_time, end_time, capacity, location,
             requirements, additional_notes, availability_status)
            VALUES
            (:vaccine, :description, :target, :dose,
             :date, :start, :end, :capacity, :location,
             :requirements, :notes, :status)");
        $sql->execute([
            ':vaccine' => $d['vaccine'],
            ':description' => $d['description'],
            ':target' => $d['target'],
            ':dose' => $d['dose'],
            ':date' => $d['date'],
            ':start' => $d['start'],
            ':end' => $d['end'],
            ':capacity' => $d['capacity'],
            ':location' => $d['location'],
            ':requirements' => $d['requirements'],
            ':notes' => $d['notes'],
            ':status' => 'Available'
        ]);
        logActivity($conn, 'vaccination', 'Added vaccination session', $d['vaccine'] . ' on ' . date('M d, Y', strtotime($d['date'])));
        $_SESSION['vax_success'] = 'Vaccination session added successfully.';
    } catch (PDOException $e) {
        fail('Could not save the session: ' . $e->getMessage(), 'vaccination_form.php');
    }
    header("Location: vaccination_offered.php");
    exit;
}

// UPDATE
if (isset($_POST['update_vaccination'])) {
    $id = (int)($_POST['vaccination_id'] ?? 0);
    $redirect = 'vaccination_form.php?id=' . $id;
    $d = readVaccination($redirect);

    try {
        $sql = $conn->prepare("UPDATE vaccination_schedules SET
            vaccine_name = :vaccine, description = :description, target_beneficiaries = :target,
            dose_information = :dose, vaccination_date = :date,
            start_time = :start, end_time = :end, capacity = :capacity, location = :location,
            requirements = :requirements, additional_notes = :notes
            WHERE vaccination_id = :id");
        $sql->execute([
            ':vaccine' => $d['vaccine'],
            ':description' => $d['description'],
            ':target' => $d['target'],
            ':dose' => $d['dose'],
            ':date' => $d['date'],
            ':start' => $d['start'],
            ':end' => $d['end'],
            ':capacity' => $d['capacity'],
            ':location' => $d['location'],
            ':requirements' => $d['requirements'],
            ':notes' => $d['notes'],
            ':id' => $id
        ]);
        logActivity($conn, 'vaccination', 'Edited vaccination session', $d['vaccine'] . ' on ' . date('M d, Y', strtotime($d['date'])));
        $_SESSION['vax_success'] = 'Vaccination session updated successfully.';
    } catch (PDOException $e) {
        fail('Could not update the session: ' . $e->getMessage(), $redirect);
    }
    header("Location: vaccination_offered.php");
    exit;
}

// CANCEL
if (isset($_POST['cancel_vaccination'])) {
    $id = (int)($_POST['vaccination_id'] ?? 0);

    $info = $conn->prepare("SELECT vaccine_name, vaccination_date FROM vaccination_schedules WHERE vaccination_id = :id");
    $info->execute([':id' => $id]);
    $vax = $info->fetch(PDO::FETCH_ASSOC);

    $sql = $conn->prepare("UPDATE vaccination_schedules SET availability_status = 'Cancelled' WHERE vaccination_id = :id");
    $sql->execute([':id' => $id]);

    if ($vax) {
        logActivity($conn, 'vaccination', 'Cancelled vaccination session', $vax['vaccine_name'] . ' on ' . date('M d, Y', strtotime($vax['vaccination_date'])));
    }
    $_SESSION['vax_success'] = 'Vaccination session cancelled.';
    header("Location: vaccination_offered.php");
    exit;
}

// COMPLETE
if (isset($_POST['complete_vaccination'])) {
    $id = (int)($_POST['vaccination_id'] ?? 0);

    $info = $conn->prepare("SELECT vaccine_name, vaccination_date FROM vaccination_schedules WHERE vaccination_id = :id");
    $info->execute([':id' => $id]);
    $vax = $info->fetch(PDO::FETCH_ASSOC);

    $sql = $conn->prepare("UPDATE vaccination_schedules SET availability_status = 'Completed' WHERE vaccination_id = :id");
    $sql->execute([':id' => $id]);

    if ($vax) {
        logActivity($conn, 'vaccination', 'Completed vaccination session', $vax['vaccine_name'] . ' on ' . date('M d, Y', strtotime($vax['vaccination_date'])));
    }
    $_SESSION['vax_success'] = 'Vaccination session marked as completed.';
    header("Location: vaccination_offered.php");
    exit;
}

header("Location: vaccination_offered.php");
exit;
?>