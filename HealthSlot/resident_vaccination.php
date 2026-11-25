<?php
include_once("session.php");
require_role(['resident']);
include_once("connection.php");

date_default_timezone_set('Asia/Manila');

$success = $_SESSION['res_success'] ?? '';
$error = $_SESSION['res_error'] ?? '';
unset($_SESSION['res_success'], $_SESSION['res_error']);

$sql = $conn->prepare("SELECT v.*,
        (SELECT COUNT(*) FROM appointment_tbl a
          WHERE a.vaccination_id = v.vaccination_id AND a.status IN ('confirmed', 'completed', 'missed')) AS taken
    FROM vaccination_schedules v
    WHERE v.vaccination_date >= :today AND v.availability_status IN ('Available', 'Limited Slots')
    ORDER BY v.vaccination_date, v.start_time");
$sql->execute([':today' => date('Y-m-d')]);
$vaccines = $sql->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Vaccination Schedule</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Vaccination</h2>
<h3>Upcoming vaccination sessions. Choose one and book a slot.</h3>

<?php if ($success !== ''): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <h4>Choose a vaccination session</h4>
    <table class="records">
        <thead>
            <tr>
                <th>Vaccine</th>
                <th>Description</th>
                <th>Dose</th>
                <th>Date and time</th>
                <th>Location</th>
                <th>Who may attend</th>
                <th>Requirements</th>
                <th>Slots left</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($vaccines) === 0): ?>
            <tr><td colspan="9">No vaccination sessions available right now.</td></tr>
        <?php endif; ?>
        <?php foreach ($vaccines as $v): ?>
            <?php $left = max(0, (int)$v['capacity'] - (int)$v['taken']); ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($v['vaccine_name']); ?></strong></td>
                <td><?php echo !empty($v['description']) ? htmlspecialchars($v['description']) : '-'; ?></td>
                <td><?php echo !empty($v['dose_information']) ? htmlspecialchars($v['dose_information']) : '-'; ?></td>
                <td>
                    <?php echo date('M d, Y', strtotime($v['vaccination_date'])); ?>
                    <span class="cell-sub"><?php echo date('g:i A', strtotime($v['start_time'])) . ' - ' . date('g:i A', strtotime($v['end_time'])); ?></span>
                </td>
                <td><?php echo htmlspecialchars($v['location'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($v['target_beneficiaries'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($v['requirements'] ?? ''); ?></td>
                <td>
                    <?php if ($left === 0): ?>
                        <span class="pill pill-full">Full</span>
                    <?php else: ?>
                        <?php echo $left; ?> of <?php echo (int)$v['capacity']; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($left > 0): ?>
                        <a class="btn" href="resident_book.php?type=vaccination&id=<?php echo (int)$v['vaccination_id']; ?>">Book</a>
                    <?php else: ?>
                        <span class="muted">No action</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>