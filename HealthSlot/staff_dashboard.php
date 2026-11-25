<?php
include_once("session.php");
require_role(['staff']);
include_once("connection.php");

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

$sql = $conn->prepare("SELECT fname, lname FROM staff_tbl WHERE staffid = :id");
$sql->execute([':id' => $_SESSION['userID']]);
$me = $sql->fetch(PDO::FETCH_ASSOC);
$name = $me ? $me['fname'] . ' ' . $me['lname'] : 'Staff';

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
$sessions = $sql->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Staff Dashboard</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Welcome, <?php echo htmlspecialchars($name); ?></h2>
<h3>Today's schedule and slot usage, <?php echo date('l, F d, Y'); ?>.</h3>

<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-number"><?php echo count($todayList); ?></div>
        <div class="summary-label">Appointments today</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo count($sessions); ?></div>
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
    <?php if (count($sessions) === 0): ?>
        <p class="muted">No upcoming sessions.</p>
    <?php endif; ?>
    <?php foreach (array_slice($sessions, 0, 8) as $r): ?>
        <?php
        $left = max(0, (int)$r['capacity'] - (int)$r['taken']);
        $percent = ((int)$r['capacity'] > 0) ? min(100, round((int)$r['taken'] / (int)$r['capacity'] * 100)) : 100;
        $barClass = ($left === 0) ? 'full' : (($percent >= 70) ? 'warn' : '');
        ?>
        <div class="session-row">
            <div>
                <div class="session-name"><?php echo htmlspecialchars($r['name']); ?></div>
                <div class="muted"><?php echo $r['kind'] . ' · ' . date('D, M d', strtotime($r['d'])) . ' · ' . date('g:i A', strtotime($r['st'])) . ' to ' . date('g:i A', strtotime($r['et'])); ?></div>
            </div>
            <div>
                <div class="slot-text"><strong><?php echo (int)$r['taken']; ?></strong> of <?php echo (int)$r['capacity']; ?> booked</div>
                <div class="bar"><div class="bar-fill <?php echo $barClass; ?>" style="width: <?php echo $percent; ?>%"></div></div>
            </div>
            <div class="muted"><?php echo ($left === 0) ? 'Full' : $left . ' left'; ?></div>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>