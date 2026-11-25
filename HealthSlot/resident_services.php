<?php
include_once("session.php");
require_role(['resident']);
include_once("connection.php");

date_default_timezone_set('Asia/Manila');

$success = $_SESSION['res_success'] ?? '';
$error = $_SESSION['res_error'] ?? '';
unset($_SESSION['res_success'], $_SESSION['res_error']);

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

$category = $_GET['category'] ?? '';
if (!in_array($category, $categories)) { $category = ''; }

$params = [':today' => date('Y-m-d')];
$extra = '';
if ($category !== '') {
    $extra = ' AND s.servicecategory = :category';
    $params[':category'] = $category;
}

$sql = $conn->prepare("SELECT s.*,
        (SELECT COUNT(*) FROM appointment_tbl a
          WHERE a.serviceid = s.serviceid AND a.status IN ('confirmed', 'completed', 'missed')) AS taken
    FROM service_tbl s
    WHERE s.servicedate >= :today AND s.status = 'Available'" . $extra . "
    ORDER BY s.servicedate, s.starttime");
$sql->execute($params);
$services = $sql->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Available Services</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Services</h2>
<h3>Choose a service and book a slot.</h3>

<?php if ($success !== ''): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <form method="get" action="resident_services.php">
        <div class="filter-row">
            <div class="field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($category === $c) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="button-row">
                <input type="submit" value="Filter">
                <a class="btn btn-outline" href="resident_services.php">Clear filters</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h4>Choose a service</h4>
    <table class="records">
        <thead>
            <tr>
                <th>Service</th>
                <th>Description</th>
                <th>Date and time</th>
                <th>Location</th>
                <th>Who may attend</th>
                <th>Requirements</th>
                <th>Slots left</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($services) === 0): ?>
            <tr><td colspan="8">No available services right now.</td></tr>
        <?php endif; ?>
        <?php foreach ($services as $s): ?>
            <?php $left = max(0, (int)$s['capacity'] - (int)$s['taken']); ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($s['servicename']); ?></strong>
                    <span class="cell-sub"><?php echo htmlspecialchars($s['servicecategory']); ?></span>
                </td>
                <td><?php echo ($s['description'] !== '') ? htmlspecialchars($s['description']) : '-'; ?></td>
                <td>
                    <?php echo date('M d, Y', strtotime($s['servicedate'])); ?>
                    <span class="cell-sub"><?php echo date('g:i A', strtotime($s['starttime'])) . ' - ' . date('g:i A', strtotime($s['endtime'])); ?></span>
                </td>
                <td><?php echo htmlspecialchars($s['location']); ?></td>
                <td><?php echo htmlspecialchars($s['beneficiaries']); ?></td>
                <td><?php echo htmlspecialchars($s['requirements']); ?></td>
                <td>
                    <?php if ($left === 0): ?>
                        <span class="pill pill-full">Full</span>
                    <?php else: ?>
                        <?php echo $left; ?> of <?php echo (int)$s['capacity']; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($left > 0): ?>
                        <a class="btn" href="resident_book.php?type=service&id=<?php echo (int)$s['serviceid']; ?>">Book</a>
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