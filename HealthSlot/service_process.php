<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");
include_once("functions.php");

date_default_timezone_set('Asia/Manila');

$categories = [
    'Medical Consultation',
    'Maternal Health Care',
    'Child Health Care',
    'Family Planning',
    'Dental Health Care',
    'Nutrition Services',
    'Diagnostic & Laboratory Services',
    'Health Education & Promotion',
    'Disease Prevention & Control'
];

// Goes back to the form with the message and what was typed
function fail($message, $redirect = 'service_form.php') {
    $_SESSION['service_error'] = $message;
    $_SESSION['service_old'] = $_POST;
    header("Location: " . $redirect);
    exit;
}

// Reads and validates the form. Used by both add and update.
function readService($categories, $redirect) {
    $d = [
        'name' => trim($_POST['servicename'] ?? ''),
        'category' => trim($_POST['servicecategory'] ?? ''),
        'date' => trim($_POST['servicedate'] ?? ''),
        'start' => trim($_POST['starttime'] ?? ''),
        'end' => trim($_POST['endtime'] ?? ''),
        'capacity' => (int)($_POST['capacity'] ?? 0),
        'location' => trim($_POST['location'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'beneficiaries' => trim($_POST['beneficiaries'] ?? ''),
        'requirements' => trim($_POST['requirements'] ?? '')
    ];

    if ($d['requirements'] === '') { $d['requirements'] = 'None'; }

    if ($d['name'] === '' || $d['category'] === '' || $d['date'] === '' || $d['start'] === '' ||
        $d['end'] === '' || $d['location'] === '' || $d['description'] === '' || $d['beneficiaries'] === '') {
        fail('Please fill in all required fields.', $redirect);
    }
    if (!in_array($d['category'], $categories)) {
        fail('Invalid category selected.', $redirect);
    }
    if ($d['date'] < date('Y-m-d')) {
        fail('The service date cannot be in the past.', $redirect);
    }
    if ($d['end'] <= $d['start']) {
        fail('The end time must be later than the start time.', $redirect);
    }
    if ($d['capacity'] < 1) {
        fail('Capacity must be at least 1.', $redirect);
    }
    if (mb_strlen($d['name']) > 50 || mb_strlen($d['location']) > 50 || mb_strlen($d['description']) > 100 ||
        mb_strlen($d['beneficiaries']) > 100 || mb_strlen($d['requirements']) > 100) {
        fail('One of the fields is too long.', $redirect);
    }
    return $d;
}

// ADD
if (isset($_POST['add_service'])) {
    $d = readService($categories, 'service_form.php');

    try {
        $sql = $conn->prepare("INSERT INTO service_tbl
            (servicename, servicecategory, servicedate, starttime, endtime, capacity, location, description, beneficiaries, requirements, status)
            VALUES
            (:name, :category, :date, :start, :end, :capacity, :location, :description, :beneficiaries, :requirements, :status)");
        $sql->execute([
            ':name' => $d['name'],
            ':category' => $d['category'],
            ':date' => $d['date'],
            ':start' => $d['start'],
            ':end' => $d['end'],
            ':capacity' => $d['capacity'],
            ':location' => $d['location'],
            ':description' => $d['description'],
            ':beneficiaries' => $d['beneficiaries'],
            ':requirements' => $d['requirements'],
            ':status' => 'Available'
        ]);
        logActivity($conn, 'service', 'Added service', $d['name'] . ' (' . $d['category'] . ') on ' . date('M d, Y', strtotime($d['date'])));
        $_SESSION['service_success'] = 'Service added successfully.';
    } catch (PDOException $e) {
        fail('Could not save the service: ' . $e->getMessage(), 'service_form.php');
    }
    header("Location: services_offered.php");
    exit;
}

// UPDATE
if (isset($_POST['update_service'])) {
    $id = (int)($_POST['serviceid'] ?? 0);
    $redirect = 'service_form.php?id=' . $id;
    $d = readService($categories, $redirect);

    try {
        $sql = $conn->prepare("UPDATE service_tbl SET
            servicename = :name, servicecategory = :category, servicedate = :date,
            starttime = :start, endtime = :end, capacity = :capacity, location = :location,
            description = :description, beneficiaries = :beneficiaries, requirements = :requirements
            WHERE serviceid = :id");
        $sql->execute([
            ':name' => $d['name'],
            ':category' => $d['category'],
            ':date' => $d['date'],
            ':start' => $d['start'],
            ':end' => $d['end'],
            ':capacity' => $d['capacity'],
            ':location' => $d['location'],
            ':description' => $d['description'],
            ':beneficiaries' => $d['beneficiaries'],
            ':requirements' => $d['requirements'],
            ':id' => $id
        ]);
        logActivity($conn, 'service', 'Edited service', $d['name'] . ' on ' . date('M d, Y', strtotime($d['date'])));
        $_SESSION['service_success'] = 'Service updated successfully.';
    } catch (PDOException $e) {
        fail('Could not update the service: ' . $e->getMessage(), $redirect);
    }
    header("Location: services_offered.php");
    exit;
}

// CANCEL
if (isset($_POST['cancel_service'])) {
    $id = (int)($_POST['serviceid'] ?? 0);

    $info = $conn->prepare("SELECT servicename, servicedate FROM service_tbl WHERE serviceid = :id");
    $info->execute([':id' => $id]);
    $svc = $info->fetch(PDO::FETCH_ASSOC);

    $sql = $conn->prepare("UPDATE service_tbl SET status = 'Cancelled' WHERE serviceid = :id");
    $sql->execute([':id' => $id]);

    if ($svc) {
        logActivity($conn, 'service', 'Cancelled service', $svc['servicename'] . ' on ' . date('M d, Y', strtotime($svc['servicedate'])));
    }
    $_SESSION['service_success'] = 'Service cancelled.';
    header("Location: services_offered.php");
    exit;
}

// COMPLETE
if (isset($_POST['complete_service'])) {
    $id = (int)($_POST['serviceid'] ?? 0);

    $info = $conn->prepare("SELECT servicename, servicedate FROM service_tbl WHERE serviceid = :id");
    $info->execute([':id' => $id]);
    $svc = $info->fetch(PDO::FETCH_ASSOC);

    $sql = $conn->prepare("UPDATE service_tbl SET status = 'Completed' WHERE serviceid = :id");
    $sql->execute([':id' => $id]);

    if ($svc) {
        logActivity($conn, 'service', 'Completed service', $svc['servicename'] . ' on ' . date('M d, Y', strtotime($svc['servicedate'])));
    }
    $_SESSION['service_success'] = 'Service marked as completed.';
    header("Location: services_offered.php");
    exit;
}

header("Location: services_offered.php");
exit;
?>