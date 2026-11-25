<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");
include_once("pagination.php");

date_default_timezone_set('Asia/Manila');

$success = $_SESSION['service_success'] ?? '';
$error = $_SESSION['service_error'] ?? '';
unset($_SESSION['service_success'], $_SESSION['service_error']);

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
$statuses = ['Available', 'Cancelled', 'Completed'];

// Filters
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$date = trim($_GET['date'] ?? '');

if (!in_array($category, $categories)) { $category = ''; }
if (!in_array($status, $statuses)) { $status = ''; }

$view = $_GET['view'] ?? 'upcoming';
if (!in_array($view, ['upcoming', 'past', 'all'])) { $view = 'upcoming'; }
$today = date('Y-m-d');
$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) { $date = ''; }

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "s.servicename LIKE :search";
    $params[':search'] = '%' . $search . '%';
}
if ($category !== '') {
    $where[] = "s.servicecategory = :category";
    $params[':category'] = $category;
}
if ($status !== '') {
    $where[] = "s.status = :status";
    $params[':status'] = $status;
}
if ($date !== '') {
    // A chosen date overrides the view
    $where[] = "s.servicedate = :date";
    $params[':date'] = $date;
} elseif ($view === 'upcoming') {
    $where[] = "s.servicedate >= :today";
    $params[':today'] = $today;
} elseif ($view === 'past') {
    $where[] = "s.servicedate < :today";
    $params[':today'] = $today;
}
$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Upcoming and single-day lists run soonest first, past and all run newest first
$orderSql = ($date !== '' || $view === 'upcoming')
    ? "s.servicedate ASC, s.starttime ASC"
    : "s.servicedate DESC, s.starttime DESC";

// Pagination
$perPage = 10;
$countSql = $conn->prepare("SELECT COUNT(*) FROM service_tbl s $whereSql");
$countSql->execute($params);
$total = (int)$countSql->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $perPage;

if ($date !== '') {
    $heading = 'Services on ' . date('M d, Y', strtotime($date));
} elseif ($view === 'upcoming') {
    $heading = 'Upcoming services';
} elseif ($view === 'past') {
    $heading = 'Past services';
} else {
    $heading = 'All services';
}
$filtered = ($search !== '' || $category !== '' || $status !== '' || $date !== '' || $view !== 'upcoming');

$sql = $conn->prepare("SELECT s.*,
        (SELECT COUNT(*) FROM appointment_tbl a
          WHERE a.serviceid = s.serviceid AND a.status IN ('confirmed', 'completed', 'missed')) AS taken
    FROM service_tbl s
    $whereSql
    ORDER BY $orderSql
    LIMIT $perPage OFFSET $offset");
$sql->execute($params);
$services = $sql->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Services Offered</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<div class="page-head">
    <div>
        <h2>Services Offered</h2>
        <h3>Post and manage health center services.</h3>
    </div>
    <a class="btn" href="service_form.php">Add service</a>
</div>

<?php if ($success !== ''): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <form method="get" action="services_offered.php">
        <div class="filter-row">
            <div class="field">
                <label for="search">Service name</label>
                <input type="text" id="search" name="search" placeholder="Search name" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($category === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($status === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
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
                <a class="btn btn-outline" href="services_offered.php">Clear filters</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h4><?php echo htmlspecialchars($heading); ?></h4>
    <table class="records">
        <thead>
            <tr>
                <th>Service</th>
                <th>Date and time</th>
                <th>Capacity</th>
                <th>Location</th>
                <th>Description</th>
                <th>Beneficiaries</th>
                <th>Requirements</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($services) === 0): ?>
            <tr><td colspan="9">
                <?php echo $filtered ? 'No services match your filters.' : 'No upcoming services. Choose Past or All in the View filter to see earlier ones.'; ?>
            </td></tr>
        <?php endif; ?>
        <?php foreach ($services as $s): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($s['servicename']); ?></strong>
                    <span class="cell-sub"><?php echo htmlspecialchars($s['servicecategory']); ?></span>
                </td>
                <td>
                    <?php echo date('M d, Y', strtotime($s['servicedate'])); ?>
                    <span class="cell-sub"><?php echo date('g:i A', strtotime($s['starttime'])) . ' - ' . date('g:i A', strtotime($s['endtime'])); ?></span>
                </td>
                <td>
                    <?php echo (int)$s['capacity']; ?>
                    <span class="cell-sub"><?php echo (int)$s['taken']; ?> booked</span>
                </td>
                <td><?php echo htmlspecialchars($s['location']); ?></td>
                <td><?php echo htmlspecialchars($s['description']); ?></td>
                <td><?php echo htmlspecialchars($s['beneficiaries']); ?></td>
                <td><?php echo htmlspecialchars($s['requirements']); ?></td>
                <td><span class="pill pill-<?php echo strtolower($s['status']); ?>"><?php echo htmlspecialchars($s['status']); ?></span></td>
                <td>
                    <?php if ($s['status'] === 'Available' || $s['status'] === 'Full'): ?>
                        <div class="action-buttons">
                            <a class="btn" href="service_form.php?id=<?php echo (int)$s['serviceid']; ?>">Edit</a>
                            <form action="service_process.php" method="post" onsubmit="return confirm('Mark this service as completed?');">
                                <input type="hidden" name="serviceid" value="<?php echo (int)$s['serviceid']; ?>">
                                <input type="submit" name="complete_service" value="Complete">
                            </form>
                            <form action="service_process.php" method="post" onsubmit="return confirm('Cancel this service?');">
                                <input type="hidden" name="serviceid" value="<?php echo (int)$s['serviceid']; ?>">
                                <input type="submit" name="cancel_service" value="Cancel">
                            </form>
                        </div>
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