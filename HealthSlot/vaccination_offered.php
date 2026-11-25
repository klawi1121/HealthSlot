<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");
include_once("pagination.php");

date_default_timezone_set('Asia/Manila');

$success = $_SESSION['vax_success'] ?? '';
$error = $_SESSION['vax_error'] ?? '';
unset($_SESSION['vax_success'], $_SESSION['vax_error']);

$statuses = ['Available', 'Limited Slots', 'Full', 'Cancelled', 'Completed'];

// Filters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$date = trim($_GET['date'] ?? '');

if (!in_array($status, $statuses)) { $status = ''; }

$view = $_GET['view'] ?? 'upcoming';
if (!in_array($view, ['upcoming', 'past', 'all'])) { $view = 'upcoming'; }
$today = date('Y-m-d');
$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) { $date = ''; }

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "v.vaccine_name LIKE :search";
    $params[':search'] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = "v.availability_status = :status";
    $params[':status'] = $status;
}
if ($date !== '') {
    // A chosen date overrides the view
    $where[] = "v.vaccination_date = :date";
    $params[':date'] = $date;
} elseif ($view === 'upcoming') {
    $where[] = "v.vaccination_date >= :today";
    $params[':today'] = $today;
} elseif ($view === 'past') {
    $where[] = "v.vaccination_date < :today";
    $params[':today'] = $today;
}
$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Upcoming and single-day lists run soonest first, past and all run newest first
$orderSql = ($date !== '' || $view === 'upcoming')
    ? "v.vaccination_date ASC, v.start_time ASC"
    : "v.vaccination_date DESC, v.start_time DESC";

// Pagination
$perPage = 10;
$countSql = $conn->prepare("SELECT COUNT(*) FROM vaccination_schedules v $whereSql");
$countSql->execute($params);
$total = (int)$countSql->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $perPage;

if ($date !== '') {
    $heading = 'Vaccination sessions on ' . date('M d, Y', strtotime($date));
} elseif ($view === 'upcoming') {
    $heading = 'Upcoming vaccination sessions';
} elseif ($view === 'past') {
    $heading = 'Past vaccination sessions';
} else {
    $heading = 'All vaccination sessions';
}
$filtered = ($search !== '' || $status !== '' || $date !== '' || $view !== 'upcoming');

$sql = $conn->prepare("SELECT v.*,
        (SELECT COUNT(*) FROM appointment_tbl a
          WHERE a.vaccination_id = v.vaccination_id AND a.status IN ('confirmed', 'completed', 'missed')) AS taken
    FROM vaccination_schedules v
    $whereSql
    ORDER BY $orderSql
    LIMIT $perPage OFFSET $offset");
$sql->execute($params);
$vaccines = $sql->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Vaccination Sessions</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<div class="page-head">
    <div>
        <h2>Vaccination Sessions</h2>
        <h3>Post and manage vaccination schedules.</h3>
    </div>
    <a class="btn" href="vaccination_form.php">Add vaccination session</a>
</div>

<?php if ($success !== ''): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <form method="get" action="vaccination_offered.php">
        <div class="filter-row">
            <div class="field">
                <label for="search">Vaccine name</label>
                <input type="text" id="search" name="search" placeholder="Search name" value="<?php echo htmlspecialchars($search); ?>">
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
                <a class="btn btn-outline" href="vaccination_offered.php">Clear filters</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h4><?php echo htmlspecialchars($heading); ?></h4>
    <table class="records">
        <thead>
            <tr>
                <th>Vaccine</th>
                <th>Date and time</th>
                <th>Capacity</th>
                <th>Location</th>
                <th>Who may attend</th>
                <th>Dose</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($vaccines) === 0): ?>
            <tr><td colspan="9">
                <?php echo $filtered ? 'No sessions match your filters.' : 'No upcoming vaccination sessions. Choose Past or All in the View filter to see earlier ones.'; ?>
            </td></tr>
        <?php endif; ?>
        <?php foreach ($vaccines as $v): ?>
            <?php $open = in_array($v['availability_status'], ['Available', 'Limited Slots', 'Full']); ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($v['vaccine_name']); ?></strong></td>
                <td>
                    <?php echo date('M d, Y', strtotime($v['vaccination_date'])); ?>
                    <span class="cell-sub"><?php echo date('g:i A', strtotime($v['start_time'])) . ' - ' . date('g:i A', strtotime($v['end_time'])); ?></span>
                </td>
                <td>
                    <?php echo (int)$v['capacity']; ?>
                    <span class="cell-sub"><?php echo (int)$v['taken']; ?> booked</span>
                </td>
                <td><?php echo htmlspecialchars($v['location'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($v['target_beneficiaries'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($v['dose_information'] ?? ''); ?></td>
                <td><span class="pill pill-<?php echo strtolower(str_replace(' ', '-', $v['availability_status'])); ?>"><?php echo htmlspecialchars($v['availability_status']); ?></span></td>
                <td>
                    <?php if ($open): ?>
                        <div class="action-buttons">
                            <a class="btn" href="vaccination_form.php?id=<?php echo (int)$v['vaccination_id']; ?>">Edit</a>
                            <form action="vaccination_process.php" method="post" onsubmit="return confirm('Mark this session as completed?');">
                                <input type="hidden" name="vaccination_id" value="<?php echo (int)$v['vaccination_id']; ?>">
                                <input type="submit" name="complete_vaccination" value="Complete">
                            </form>
                            <form action="vaccination_process.php" method="post" onsubmit="return confirm('Cancel this vaccination session?');">
                                <input type="hidden" name="vaccination_id" value="<?php echo (int)$v['vaccination_id']; ?>">
                                <input type="submit" name="cancel_vaccination" value="Cancel">
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