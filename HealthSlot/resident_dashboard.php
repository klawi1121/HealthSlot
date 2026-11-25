<?php
include_once("session.php");
require_role(['resident']);
include_once("connection.php");

date_default_timezone_set('Asia/Manila');
$rid = (int)$_SESSION['userID'];
$today = date('Y-m-d');

$sql = $conn->prepare("SELECT fname FROM resident_tbl WHERE residentid = :id");
$sql->execute([':id' => $rid]);
$me = $sql->fetch(PDO::FETCH_ASSOC);
$name = $me ? $me['fname'] : 'Resident';

$base = "FROM appointment_tbl a
    LEFT JOIN service_tbl s ON s.serviceid = a.serviceid
    LEFT JOIN vaccination_schedules v ON v.vaccination_id = a.vaccination_id
    WHERE a.residentid = :rid
      AND COALESCE(s.servicedate, v.vaccination_date) >= :today";

// Next appointment
$sql = $conn->prepare("SELECT a.status, a.appointment_time,
        COALESCE(s.servicename, v.vaccine_name) AS session_name,
        COALESCE(s.servicedate, v.vaccination_date) AS session_date
    $base AND a.status = 'confirmed'
    ORDER BY session_date, a.appointment_time LIMIT 1");
$sql->execute([':rid' => $rid, ':today' => $today]);
$next = $sql->fetch(PDO::FETCH_ASSOC);

// Upcoming appointments
$sql = $conn->prepare("SELECT COUNT(*) $base AND a.status = 'confirmed'");
$sql->execute([':rid' => $rid, ':today' => $today]);
$upcomingCount = (int)$sql->fetchColumn();

// Upcoming sessions with the number of slots taken
$sql = $conn->prepare("SELECT 'service' AS kind, s.serviceid AS sid, s.servicename AS name,
        s.servicedate AS d, s.starttime AS st, s.endtime AS et, s.capacity,
        s.beneficiaries AS info,
        (SELECT COUNT(*) FROM appointment_tbl a
          WHERE a.serviceid = s.serviceid AND a.status IN ('confirmed', 'completed', 'missed')) AS taken
    FROM service_tbl s
    WHERE s.servicedate >= :t1 AND s.status IN ('Available', 'Full')
    UNION ALL
    SELECT 'vaccination', v.vaccination_id, v.vaccine_name,
        v.vaccination_date, v.start_time, v.end_time, v.capacity,
        v.target_beneficiaries,
        (SELECT COUNT(*) FROM appointment_tbl a
          WHERE a.vaccination_id = v.vaccination_id AND a.status IN ('confirmed', 'completed', 'missed'))
    FROM vaccination_schedules v
    WHERE v.vaccination_date >= :t2 AND v.availability_status IN ('Available', 'Limited Slots', 'Full')
    ORDER BY d, st");
$sql->execute([':t1' => $today, ':t2' => $today]);
$sessions = $sql->fetchAll(PDO::FETCH_ASSOC);

$vaccines = [];
$services = [];
$openCount = 0;
foreach ($sessions as $row) {
    if ((int)$row['capacity'] - (int)$row['taken'] > 0) { $openCount++; }
    if ($row['kind'] === 'vaccination') { $vaccines[] = $row; } else { $services[] = $row; }
}

// Prints one list of sessions with the slot bar and the Book button
function sessionList($rows) {
    if (count($rows) === 0) {
        echo '<p class="muted">No sessions available right now.</p>';
        return;
    }
    foreach (array_slice($rows, 0, 5) as $r) {
        $left = max(0, (int)$r['capacity'] - (int)$r['taken']);
        $percent = ((int)$r['capacity'] > 0) ? min(100, round((int)$r['taken'] / (int)$r['capacity'] * 100)) : 100;
        $barClass = ($left === 0) ? 'full' : (($percent >= 70) ? 'warn' : '');
        ?>
        <div class="session-row">
            <div>
                <div class="session-name"><?php echo htmlspecialchars($r['name']); ?></div>
                <div class="muted">
                    <?php echo date('D, M d', strtotime($r['d'])) . ' · ' . date('g:i A', strtotime($r['st'])) . ' to ' . date('g:i A', strtotime($r['et'])); ?>
                    <?php if (!empty($r['info'])): ?> · <?php echo htmlspecialchars($r['info']); ?><?php endif; ?>
                </div>
            </div>
            <div>
                <div class="slot-text"><strong><?php echo $left; ?></strong> of <?php echo (int)$r['capacity']; ?> slots left</div>
                <div class="bar"><div class="bar-fill <?php echo $barClass; ?>" style="width: <?php echo $percent; ?>%"></div></div>
            </div>
            <div>
                <?php if ($left > 0): ?>
                    <a class="btn" href="resident_book.php?type=<?php echo $r['kind']; ?>&id=<?php echo (int)$r['sid']; ?>">Book</a>
                <?php else: ?>
                    <span class="muted">Full</span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Resident Dashboard</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Welcome, <?php echo htmlspecialchars($name); ?></h2>
<h3>View open sessions and slots, book, and manage your own appointments.</h3>

<div class="summary-cards">
    <div class="summary-card plain">
        <span class="summary-label">Next appointment</span>
        <?php if ($next): ?>
            <strong><?php echo htmlspecialchars($next['session_name']); ?></strong>
            <span class="muted"><?php echo date('D, M d', strtotime($next['session_date'])) . ' · ' . date('g:i A', strtotime($next['appointment_time'])); ?></span>
            <span class="pill pill-<?php echo $next['status']; ?>"><?php echo ucfirst($next['status']); ?></span>
        <?php else: ?>
            <span class="muted">No upcoming appointment.</span>
        <?php endif; ?>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo $upcomingCount; ?></div>
        <div class="summary-label">Upcoming appointments</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo $openCount; ?></div>
        <div class="summary-label">Sessions with open slots</div>
    </div>
</div>

<div class="card">
    <h4>Available vaccination sessions</h4>
    <?php sessionList($vaccines); ?>
</div>

<div class="card">
    <h4>Available services</h4>
    <?php sessionList($services); ?>
</div>

</body>
</html>