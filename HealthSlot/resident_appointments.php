<?php
include_once("session.php");
require_role(['resident']);
include_once("connection.php");
include_once("pagination.php");

date_default_timezone_set('Asia/Manila');

$success = $_SESSION['res_success'] ?? '';
$error = $_SESSION['res_error'] ?? '';
unset($_SESSION['res_success'], $_SESSION['res_error']);

// ---------- Filters ----------
$validStatus = ['confirmed', 'completed', 'missed', 'cancelled'];

$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$date = trim($_GET['date'] ?? '');

if (!in_array($type, ['service', 'vaccination'])) { $type = ''; }
if (!in_array($status, $validStatus)) { $status = ''; }

$view = $_GET['view'] ?? 'upcoming';
if (!in_array($view, ['upcoming', 'past', 'all'])) { $view = 'upcoming'; }
$today = date('Y-m-d');
$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) { $date = ''; }

// The resident's own appointments only
$where = ["a.residentid = :rid"];
$params = [':rid' => (int)$_SESSION['userID']];

if ($type === 'service') {
    $where[] = "a.serviceid IS NOT NULL";
} elseif ($type === 'vaccination') {
    $where[] = "a.vaccination_id IS NOT NULL";
}
if ($status !== '') {
    $where[] = "a.status = :status";
    $params[':status'] = $status;
}
if ($date !== '') {
    // A chosen date overrides the view
    $where[] = "COALESCE(s.servicedate, v.vaccination_date) = :date";
    $params[':date'] = $date;
} elseif ($view === 'upcoming') {
    $where[] = "COALESCE(s.servicedate, v.vaccination_date) >= :today";
    $params[':today'] = $today;
} elseif ($view === 'past') {
    $where[] = "COALESCE(s.servicedate, v.vaccination_date) < :today";
    $params[':today'] = $today;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Upcoming and single-day lists run soonest first, past and all run newest first
$orderSql = ($date !== '' || $view === 'upcoming')
    ? "session_date ASC, a.appointment_time ASC"
    : "session_date DESC, a.appointment_time DESC";

// Pagination
$perPage = 10;
$countSql = $conn->prepare("SELECT COUNT(*)
    FROM appointment_tbl a
    LEFT JOIN service_tbl s ON s.serviceid = a.serviceid
    LEFT JOIN vaccination_schedules v ON v.vaccination_id = a.vaccination_id
    $whereSql");
$countSql->execute($params);
$total = (int)$countSql->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $perPage;

if ($date !== '') {
    $heading = 'Appointments on ' . date('M d, Y', strtotime($date));
} elseif ($view === 'upcoming') {
    $heading = 'Upcoming appointments';
} elseif ($view === 'past') {
    $heading = 'Past appointments';
} else {
    $heading = 'All appointments';
}
$filtered = ($type !== '' || $status !== '' || $date !== '' || $view !== 'upcoming');

$sql = $conn->prepare("SELECT a.appointmentid, a.serviceid, a.status, a.appointment_time,
        a.patient_name, a.patient_age, a.companion_name, a.companion_contact, a.queue_no, a.is_priority,
        COALESCE(s.servicename, v.vaccine_name) AS session_name,
        COALESCE(s.servicecategory, 'Immunization & Vaccination') AS category,
        COALESCE(s.servicedate, v.vaccination_date) AS session_date,
        COALESCE(s.location, v.location) AS location
    FROM appointment_tbl a
    LEFT JOIN service_tbl s ON s.serviceid = a.serviceid
    LEFT JOIN vaccination_schedules v ON v.vaccination_id = a.vaccination_id
    $whereSql
    ORDER BY $orderSql
    LIMIT $perPage OFFSET $offset");
$sql->execute($params);
$appointments = $sql->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>My Appointments</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>My Appointments</h2>
<h3>Track the status of your requests.</h3>

<?php if ($success !== ''): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <form method="get" action="resident_appointments.php">
        <div class="filter-row">
            <div class="field">
                <label for="type">Type</label>
                <select id="type" name="type">
                    <option value="">All types</option>
                    <option value="service" <?php echo ($type === 'service') ? 'selected' : ''; ?>>Service</option>
                    <option value="vaccination" <?php echo ($type === 'vaccination') ? 'selected' : ''; ?>>Vaccination</option>
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($validStatus as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($status === $st) ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="view">View</label>
                <select id="view" name="view">
                    <option value="upcoming" <?php echo ($view === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="past" <?php echo ($view === 'past') ? 'selected' : ''; ?>>Past</option>
                    <option value="all" <?php echo ($view === 'all') ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
            <div class="field">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
            </div>
            <div class="button-row">
                <input type="submit" value="Search">
                <a class="btn btn-outline" href="resident_appointments.php">Clear filters</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h4><?php echo htmlspecialchars($heading); ?></h4>
    <table class="records">
        <thead>
            <tr>
                <th>Patient</th>
                <th>Service / Vaccine</th>
                <th>Date and time</th>
                <th> Queue </th>
                <th>Location</th>
                <th>Companion</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($appointments) === 0): ?>
            <tr>
                <td colspan="8">
                    <?php echo $filtered
                        ? 'No appointments match your filters.'
                        : 'You have no upcoming appointments. Choose Past or All in the View filter to see earlier ones.'; ?>
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach ($appointments as $a): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($a['patient_name']); ?></strong> (<?php echo (int)$a['patient_age']; ?>)</td>
                <td>
                    <strong><?php echo htmlspecialchars($a['session_name']); ?></strong>
                    <span class="cell-sub"><?php echo htmlspecialchars($a['category']); ?></span>
                </td>
                <td>
                    <?php echo date('M d, Y', strtotime($a['session_date'])); ?>
                    <span class="cell-sub"><?php echo date('g:i A', strtotime($a['appointment_time'])); ?></span>
                </td>
                <td>
                    <?php if ($a['queue_no'] === null || $a['status'] === 'cancelled'): ?>
                        -
                    <?php elseif ((int)$a['is_priority'] === 1): ?>
                        <span class="pill pill-priority">P-<?php echo (int)$a['queue_no']; ?></span>
                    <?php else: ?>
                        <strong><?php echo (int)$a['queue_no']; ?></strong>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($a['location'] ?? ''); ?></td>
                <td>
                    <?php if ($a['companion_name'] !== null): ?>
                        <?php echo htmlspecialchars($a['companion_name']); ?>
                        <?php if ($a['companion_contact'] !== null): ?>
                            <span class="cell-sub"><?php echo htmlspecialchars($a['companion_contact']); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><span class="pill pill-<?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span></td>
                <td>
                    <?php if ($a['status'] === 'pending' || $a['status'] === 'confirmed'): ?>
                        <form action="cancel_process.php" method="post" onsubmit="return confirm('Cancel this appointment?');">
                            <input type="hidden" name="appointmentid" value="<?php echo (int)$a['appointmentid']; ?>">
                            <input type="submit" name="cancel_appointment" value="Cancel">
                        </form>
                    <?php else: ?>
                        <span class="muted">No action</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php renderPagination($page, $pages, $total, $perPage); ?>
</div>

</body>
</html>