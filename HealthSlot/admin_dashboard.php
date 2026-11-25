<?php
include_once("session.php");
require_role(['admin']);
include_once("connection.php");

date_default_timezone_set('Asia/Manila');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthLabel = date('F');

// Staff accounts
$staffTotal = 0;
$staffActive = 0;
foreach ($conn->query("SELECT status, COUNT(*) AS c FROM staff_tbl GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $staffTotal += (int)$row['c'];
    if ($row['status'] === 'active') { $staffActive = (int)$row['c']; }
}
$staffInactive = $staffTotal - $staffActive;

$residents = (int)$conn->query("SELECT COUNT(*) FROM resident_tbl")->fetchColumn();

$range = [':start' => $monthStart, ':end' => $monthEnd];

// Appointments this month (by session date)
$from = "FROM appointment_tbl a
    LEFT JOIN service_tbl s ON s.serviceid = a.serviceid
    LEFT JOIN vaccination_schedules v ON v.vaccination_id = a.vaccination_id
    WHERE COALESCE(s.servicedate, v.vaccination_date) BETWEEN :start AND :end";

$sql = $conn->prepare("SELECT COUNT(*) $from");
$sql->execute($range);
$appointments = (int)$sql->fetchColumn();

// Sessions this month (services and vaccination sessions that were not cancelled)
$sql = $conn->prepare("SELECT COUNT(*) FROM service_tbl
    WHERE servicedate BETWEEN :start AND :end AND status <> 'Cancelled'");
$sql->execute($range);
$sessions = (int)$sql->fetchColumn();

$sql = $conn->prepare("SELECT COUNT(*) FROM vaccination_schedules
    WHERE vaccination_date BETWEEN :start AND :end AND availability_status <> 'Cancelled'");
$sql->execute($range);
$sessions += (int)$sql->fetchColumn();

// Appointments per service this month (cancelled appointments are not counted)
$sql = $conn->prepare("SELECT COALESCE(s.servicename, v.vaccine_name) AS name, COUNT(*) AS c
    $from AND a.status <> 'cancelled'
    GROUP BY name ORDER BY c DESC");
$sql->execute($range);
$perService = $sql->fetchAll(PDO::FETCH_ASSOC);

$maxService = 1;
foreach ($perService as $p) {
    if ((int)$p['c'] > $maxService) { $maxService = (int)$p['c']; }
}

// ---------- Operational information (same rules as the staff dashboard) ----------
$today = date('Y-m-d');

// Today's appointments (cancelled ones are left out)
$sql = $conn->prepare("SELECT a.status, a.appointment_time, a.queue_no, a.is_priority, a.patient_name, a.patient_age, a.serviceid,
        COALESCE(s.servicename, v.vaccine_name) AS session_name
    FROM appointment_tbl a
    LEFT JOIN service_tbl s ON s.serviceid = a.serviceid
    LEFT JOIN vaccination_schedules v ON v.vaccination_id = a.vaccination_id
    WHERE COALESCE(s.servicedate, v.vaccination_date) = :today
      AND a.status <> 'cancelled'
    ORDER BY a.appointment_time, a.is_priority DESC, a.queue_no");
$sql->execute([':today' => $today]);
$todayList = $sql->fetchAll(PDO::FETCH_ASSOC);

// Upcoming sessions with slots taken (pending and confirmed count as taken)
$sql = $conn->prepare("SELECT 'Service' AS kind, s.servicename AS name, s.servicedate AS d,
        s.starttime AS st, s.endtime AS et, s.capacity,
        (SELECT COUNT(*) FROM appointment_tbl a
          WHERE a.serviceid = s.serviceid AND a.status IN ('confirmed', 'completed', 'missed')) AS taken
    FROM service_tbl s
    WHERE s.servicedate >= :t1 AND s.status IN ('Available', 'Full')
    UNION ALL
    SELECT 'Vaccination', v.vaccine_name, v.vaccination_date, v.start_time, v.end_time, v.capacity,
        (SELECT COUNT(*) FROM appointment_tbl a
          WHERE a.vaccination_id = v.vaccination_id AND a.status IN ('confirmed', 'completed', 'missed'))
    FROM vaccination_schedules v
    WHERE v.vaccination_date >= :t2 AND v.availability_status IN ('Available', 'Limited Slots', 'Full')
    ORDER BY d, st");
$sql->execute([':t1' => $today, ':t2' => $today]);
$upcomingSessions = $sql->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Administrator Dashboard</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Administrator dashboard</h2>
<h3>Manage staff accounts, review analytics, and add sessions.</h3>

<div class="summary-cards">
    <div class="summary-card">
        <div class="muted"><?php echo $staffActive; ?> active · <?php echo $staffInactive; ?> inactive</div>
        <div class="summary-number"><?php echo $staffTotal; ?></div>
        <div class="summary-label">Staff accounts</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo $residents; ?></div>
        <div class="summary-label">Registered residents</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo $appointments; ?></div>
        <div class="summary-label">Appointments this month</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo $sessions; ?></div>
        <div class="summary-label">Sessions this month</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo count($todayList); ?></div>
        <div class="summary-label">Appointments today</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo count($upcomingSessions); ?></div>
        <div class="summary-label">Upcoming sessions</div>
    </div>
</div>

<div class="card">
    <h4>Today's appointments</h4>
    <table class="records">
        <thead>
            <tr>
                <th>Time</th>
                <th>Queue</th>
                <th>Patient</th>
                <th>Service / vaccine</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($todayList) === 0): ?>
            <tr><td colspan="5">No appointments scheduled for today.</td></tr>
        <?php endif; ?>
        <?php foreach ($todayList as $a): ?>
            <tr>
                <td><?php echo date('g:i A', strtotime($a['appointment_time'])); ?></td>
                <td>
                    <?php if ($a['queue_no'] === null): ?>
                        -
                    <?php elseif ((int)$a['is_priority'] === 1): ?>
                        <span class="pill pill-priority">P-<?php echo (int)$a['queue_no']; ?></span>
                    <?php else: ?>
                        <strong><?php echo (int)$a['queue_no']; ?></strong>
                    <?php endif; ?>
                </td>
                <td><strong><?php echo htmlspecialchars($a['patient_name']); ?></strong> (<?php echo (int)$a['patient_age']; ?>)</td>
                <td>
                    <?php echo htmlspecialchars($a['session_name']); ?>
                    <span class="cell-sub"><?php echo ($a['serviceid'] !== null) ? 'Service' : 'Vaccination'; ?></span>
                </td>
                <td><span class="pill pill-<?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h4>Slot usage</h4>
    <?php if (count($upcomingSessions) === 0): ?>
        <p class="muted">No upcoming sessions.</p>
    <?php endif; ?>
    <?php foreach (array_slice($upcomingSessions, 0, 8) as $r): ?>
        <?php
        $capacity = (int)$r['capacity'];
        $taken = (int)$r['taken'];
        $left = max(0, $capacity - $taken);
        $percent = ($capacity > 0) ? min(100, (int)round($taken / $capacity * 100)) : 0;
        $barClass = ($capacity > 0 && $left === 0) ? 'full' : (($percent >= 70) ? 'warn' : '');
        ?>
        <div class="session-row">
            <div>
                <div class="session-name"><?php echo htmlspecialchars($r['name']); ?></div>
                <div class="muted"><?php echo $r['kind'] . ' · ' . date('D, M d', strtotime($r['d'])) . ' · ' . date('g:i A', strtotime($r['st'])) . ' to ' . date('g:i A', strtotime($r['et'])); ?></div>
            </div>
            <div>
                <div class="slot-text">
                    <?php if ($capacity > 0): ?>
                        <strong><?php echo $taken; ?></strong> of <?php echo $capacity; ?> booked
                    <?php else: ?>
                        No slots set
                    <?php endif; ?>
                </div>
                <div class="bar"><div class="bar-fill <?php echo $barClass; ?>" style="width: <?php echo $percent; ?>%"></div></div>
            </div>
            <div class="muted">
                <?php echo ($capacity === 0) ? 'No slots set' : (($left === 0) ? 'Full' : $left . ' left'); ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="page-head">
        <h4>Analytics: appointments per service (<?php echo $monthLabel; ?>)</h4>
        <a href="admin_reports.php?report=monthly&period=<?php echo date('Y-m'); ?>">Open full reports</a>
    </div>

    <?php if (count($perService) === 0): ?>
        <p class="muted">No appointments this month yet.</p>
    <?php endif; ?>
    <?php foreach ($perService as $p): ?>
        <div class="bar-row">
            <span><?php echo htmlspecialchars($p['name']); ?></span>
            <div class="bar"><div class="bar-fill" style="width: <?php echo round((int)$p['c'] / $maxService * 100); ?>%"></div></div>
            <strong><?php echo (int)$p['c']; ?></strong>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>