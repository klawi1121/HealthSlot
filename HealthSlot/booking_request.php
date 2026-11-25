<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");
include_once("pagination.php");

date_default_timezone_set('Asia/Manila');

$success = $_SESSION['appt_success'] ?? '';
$error = $_SESSION['appt_error'] ?? '';
unset($_SESSION['appt_success'], $_SESSION['appt_error']);

$validStatus = ['confirmed', 'completed', 'missed', 'cancelled'];

$search = trim($_GET['search'] ?? '');
$date = trim($_GET['date'] ?? '');
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';

if (!in_array($type, ['service', 'vaccination'])) { $type = ''; }
if (!in_array($status, $validStatus)) { $status = ''; }

$view = $_GET['view'] ?? 'upcoming';
if (!in_array($view, ['upcoming', 'past', 'all'])) { $view = 'upcoming'; }
$today = date('Y-m-d');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(CONCAT(r.fname, ' ', r.lname) LIKE :search1 OR a.patient_name LIKE :search2)";
    $params[':search1'] = '%' . $search . '%';
    $params[':search2'] = '%' . $search . '%';
}
if ($date !== '') {
    // A chosen date overrides the view
    $where[] = "COALESCE(s.servicedate, v.vaccination_date) = :date";
    $params[':date'] = $date;
} elseif ($view === 'upcoming') {
    // Today counts as upcoming, so the whole day's queue stays visible
    $where[] = "COALESCE(s.servicedate, v.vaccination_date) >= :today";
    $params[':today'] = $today;
} elseif ($view === 'past') {
    $where[] = "COALESCE(s.servicedate, v.vaccination_date) < :today";
    $params[':today'] = $today;
}
if ($type === 'service') {
    $where[] = "a.serviceid IS NOT NULL";
} elseif ($type === 'vaccination') {
    $where[] = "a.vaccination_id IS NOT NULL";
}
if ($status !== '') {
    $where[] = "a.status = :status";
    $params[':status'] = $status;
}

$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Upcoming and single-day lists run soonest first, past and all show the newest day first.
// Inside a day the queue order stays the same: time, then priority, then queue number.
$orderSql = ($date !== '' || $view === 'upcoming')
    ? "session_date ASC, a.appointment_time ASC, a.is_priority DESC, a.queue_no ASC"
    : "session_date DESC, a.appointment_time ASC, a.is_priority DESC, a.queue_no ASC";

// Pagination
$perPage = 10;
$countSql = $conn->prepare("SELECT COUNT(*)
    FROM appointment_tbl a
    LEFT JOIN resident_tbl r ON r.residentid = a.residentid
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
$filtered = ($search !== '' || $type !== '' || $status !== '' || $date !== '' || $view !== 'upcoming');

$sql = $conn->prepare("SELECT a.appointmentid, a.serviceid, a.status, a.appointment_time,
        a.queue_no, a.is_priority,
        a.patient_name, a.patient_age, a.patient_address, a.patient_contact,
        a.companion_name, a.companion_contact,
        CONCAT(r.fname, ' ', r.lname) AS booked_by,
        a.booked_via, CONCAT(e.fname, ' ', e.lname) AS encoder,
        COALESCE(s.servicename, v.vaccine_name) AS session_name,
        COALESCE(s.servicedate, v.vaccination_date) AS session_date
    FROM appointment_tbl a
    LEFT JOIN resident_tbl r ON r.residentid = a.residentid
    LEFT JOIN staff_tbl e ON e.staffid = a.encoded_by
    LEFT JOIN service_tbl s ON s.serviceid = a.serviceid
    LEFT JOIN vaccination_schedules v ON v.vaccination_id = a.vaccination_id
    $whereSql
    ORDER BY $orderSql
    LIMIT $perPage OFFSET $offset");
$sql->execute($params);
$appointments = $sql->fetchAll(PDO::FETCH_ASSOC);

// Used to return to the same filtered list after an action
$back = http_build_query(array_filter([
    'search' => $search,
    'date' => $date,
    'type' => $type,
    'status' => $status,
    'view' => $view,
    'page' => $page
]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Booking Requests</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Booking requests</h2>
<h3>Search, filter, and update every appointment. Within each hour, priority (P) numbers are served first.</h3>

<?php if ($success !== ''): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <form method="get" action="booking_request.php">
        <div class="filter-row">
            <div class="field">
                <label for="search">Patient or resident name</label>
                <input type="text" id="search" name="search" placeholder="Search name" value="<?php echo htmlspecialchars($search); ?>">
            </div>
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
                <a class="btn btn-outline" href="booking_request.php">Clear filters</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h4><?php echo htmlspecialchars($heading); ?></h4>
    <table class="records">
        <thead>
            <tr>
                <th>Date and time</th>
                <th>Queue</th>
                <th>Patient</th>
                <th>Service</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($appointments) === 0): ?>
            <tr><td colspan="6">
                <?php echo $filtered ? 'No appointments match your filters.' : 'No upcoming appointments. Choose Past or All in the View filter to see earlier ones.'; ?>
            </td></tr>
        <?php endif; ?>
        <?php foreach ($appointments as $a): ?>
            <tr>
                <td><?php echo date('M d', strtotime($a['session_date'])) . ' · ' . date('g:i A', strtotime($a['appointment_time'])); ?></td>
                <td>
                    <?php if ($a['queue_no'] === null): ?>
                        -
                    <?php elseif ((int)$a['is_priority'] === 1): ?>
                        <span class="pill pill-priority">P-<?php echo (int)$a['queue_no']; ?></span>
                    <?php else: ?>
                        <strong><?php echo (int)$a['queue_no']; ?></strong>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($a['patient_name']); ?></strong> (<?php echo (int)$a['patient_age']; ?>)
                    <span class="cell-sub"><?php echo htmlspecialchars($a['patient_contact']) . ' · ' . htmlspecialchars($a['patient_address']); ?></span>
                    <span class="cell-sub">
                        <?php if ($a['companion_name'] !== null): ?>
                            Companion: <?php echo htmlspecialchars($a['companion_name']); ?><?php echo ($a['companion_contact'] !== null) ? ' (' . htmlspecialchars($a['companion_contact']) . ')' : ''; ?>
                        <?php endif; ?>
                        <?php if ($a['booked_via'] === 'staff'): ?>
                            Booked by staff<?php echo ($a['encoder'] !== null && trim($a['encoder']) !== '') ? ': ' . htmlspecialchars($a['encoder']) : ' (administrator)'; ?>
                        <?php else: ?>
                            Booked by <?php echo htmlspecialchars($a['booked_by'] ?? ''); ?>
                        <?php endif; ?>
                    </span>
                </td>
                <td>
                    <?php echo htmlspecialchars($a['session_name']); ?>
                    <span class="cell-sub"><?php echo ($a['serviceid'] !== null) ? 'Service' : 'Vaccination'; ?></span>
                </td>
                <td><span class="pill pill-<?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span></td>
                <td>
                    <?php if ($a['status'] === 'confirmed' || $a['status'] === 'pending'): ?>
                        <form action="status_process.php" method="post">
                            <input type="hidden" name="appointmentid" value="<?php echo (int)$a['appointmentid']; ?>">
                            <input type="hidden" name="back" value="<?php echo htmlspecialchars($back); ?>">
                            <div class="action-buttons">
                                <?php if ($a['status'] === 'confirmed'): ?>
                                    <input type="submit" name="action" value="Complete">
                                    <input type="submit" name="action" value="Missed">
                                <?php endif; ?>
                                <input type="submit" name="action" value="Cancel" onclick="return confirm('Cancel this appointment? The slot will be released.');">
                            </div>
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