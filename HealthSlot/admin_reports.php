<?php
include_once("session.php");
require_role(['admin']);
include_once("connection.php");

date_default_timezone_set('Asia/Manila');

$report = $_GET['report'] ?? 'daily';
if (!in_array($report, ['daily', 'monthly'])) { $report = 'daily'; }
$period = trim($_GET['period'] ?? '');

if ($report === 'daily') {
    $d = DateTime::createFromFormat('Y-m-d', $period);
    if (!$d || $d->format('Y-m-d') !== $period) { $period = date('Y-m-d'); }
    $start = $period;
    $end = $period;
    $label = date('F d, Y', strtotime($period));
} else {
    $m = DateTime::createFromFormat('Y-m-d', $period . '-01');
    if (!$m || $m->format('Y-m') !== $period) { $period = date('Y-m'); }
    $start = $period . '-01';
    $end = date('Y-m-t', strtotime($start));
    $label = date('F Y', strtotime($start));
}

$from = "FROM appointment_tbl a
    LEFT JOIN service_tbl s ON s.serviceid = a.serviceid
    LEFT JOIN vaccination_schedules v ON v.vaccination_id = a.vaccination_id
    WHERE COALESCE(s.servicedate, v.vaccination_date) BETWEEN :start AND :end";
$range = [':start' => $start, ':end' => $end];

// Appointments by status
$counts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'missed' => 0, 'cancelled' => 0];
$sql = $conn->prepare("SELECT a.status, COUNT(*) AS c $from GROUP BY a.status");
$sql->execute($range);
foreach ($sql->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counts[$row['status']] = (int)$row['c'];
}
$total = array_sum($counts);

// Residents served: different residents with a completed appointment
$sql = $conn->prepare("SELECT COUNT(DISTINCT LOWER(a.patient_name)) $from AND a.status = 'completed'");
$sql->execute($range);
$served = (int)$sql->fetchColumn();

// Vaccination sessions conducted
$sql = $conn->prepare("SELECT COUNT(*) FROM vaccination_schedules
    WHERE vaccination_date BETWEEN :start AND :end AND availability_status = 'Completed'");
$sql->execute($range);
$vaccConducted = (int)$sql->fetchColumn();

// Appointments per service (cancelled ones are not counted here)
$sql = $conn->prepare("SELECT COALESCE(s.servicename, v.vaccine_name) AS name, COUNT(*) AS c
    $from AND a.status <> 'cancelled'
    GROUP BY name ORDER BY c DESC");
$sql->execute($range);
$perService = $sql->fetchAll(PDO::FETCH_ASSOC);
$maxService = 1;
foreach ($perService as $p) {
    if ((int)$p['c'] > $maxService) { $maxService = (int)$p['c']; }
}

// Daily attendance
$sql = $conn->prepare("SELECT COALESCE(s.servicedate, v.vaccination_date) AS d,
        COUNT(*) AS booked,
        SUM(a.status = 'completed') AS completed,
        SUM(a.status = 'missed') AS missed,
        SUM(a.status = 'cancelled') AS cancelled
    $from
    GROUP BY d ORDER BY d");
$sql->execute($range);
$daily = $sql->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Reports and Analytics</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<div class="page-head">
    <div>
        <h2>Reports and analytics</h2>
        <h3>Administrator view. <?php echo ucfirst($report); ?> summary: <?php echo $label; ?>.</h3>
    </div>
    <button type="button" class="no-print" onclick="window.print()">Print report</button>
</div>

<div class="card no-print">
    <form method="get" action="admin_reports.php">
        <div class="grid-2">
            <div class="field">
                <label for="report">Report type</label>
                <select id="report" name="report">
                    <option value="daily" <?php echo ($report === 'daily') ? 'selected' : ''; ?>>Daily</option>
                    <option value="monthly" <?php echo ($report === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                </select>
            </div>
            <div class="field">
                <label for="period">Period</label>
                <input type="<?php echo ($report === 'daily') ? 'date' : 'month'; ?>" id="period" name="period" value="<?php echo htmlspecialchars($period); ?>" required>
            </div>
        </div>
        <div class="button-row" style="margin-top: 16px;">
            <input type="submit" value="Generate">
        </div>
    </form>
</div>

<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-number"><?php echo $total; ?></div>
        <div class="summary-label">Appointments</div>
    </div>
    <div class="summary-card">
        <div class="summary-number" style="color: var(--primary);"><?php echo $counts['completed']; ?></div>
        <div class="summary-label">Completed</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo $counts['missed']; ?></div>
        <div class="summary-label">Missed</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo $counts['cancelled']; ?></div>
        <div class="summary-label">Cancelled</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo $served; ?></div>
        <div class="summary-label">Residents served</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?php echo $vaccConducted; ?></div>
        <div class="summary-label">Vaccination sessions held</div>
    </div>
</div>

<div class="card">
    <h4>Appointments per service</h4>
    <?php if (count($perService) === 0): ?>
        <p class="muted">No appointments for this period.</p>
    <?php endif; ?>
    <?php foreach ($perService as $p): ?>
        <div class="bar-row">
            <span><?php echo htmlspecialchars($p['name']); ?></span>
            <div class="bar"><div class="bar-fill" style="width: <?php echo round((int)$p['c'] / $maxService * 100); ?>%"></div></div>
            <strong><?php echo (int)$p['c']; ?></strong>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h4>Daily attendance</h4>
    <table class="records">
        <thead>
            <tr>
                <th>Date</th>
                <th>Booked</th>
                <th>Completed</th>
                <th>Missed</th>
                <th>Cancelled</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($daily) === 0): ?>
            <tr><td colspan="5">No appointments for this period.</td></tr>
        <?php endif; ?>
        <?php foreach ($daily as $row): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($row['d'])); ?></td>
                <td><?php echo (int)$row['booked']; ?></td>
                <td><?php echo (int)$row['completed']; ?></td>
                <td><?php echo (int)$row['missed']; ?></td>
                <td><?php echo (int)$row['cancelled']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    var reportType = document.getElementById('report');
    var periodInput = document.getElementById('period');
    var today = '<?php echo date('Y-m-d'); ?>';
    var thisMonth = '<?php echo date('Y-m'); ?>';

    reportType.addEventListener('change', function () {
        if (reportType.value === 'daily') {
            periodInput.type = 'date';
            periodInput.value = today;
        } else {
            periodInput.type = 'month';
            periodInput.value = thisMonth;
        }
    });
</script>

</body>
</html>