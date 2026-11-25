<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");

date_default_timezone_set('Asia/Manila');

$isAdmin = ($_SESSION['role'] === 'admin');
$myId = $isAdmin ? 0 : (int)$_SESSION['userID'];

$categories = [
    'appointment' => 'Appointments',
    'service' => 'Services',
    'vaccination' => 'Vaccination sessions'
];
if ($isAdmin) {
    $categories['staff_account'] = 'Staff accounts';
}

$who = $_GET['who'] ?? '';
$category = $_GET['category'] ?? '';
$date = trim($_GET['date'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$params = [];

// A staff member only ever sees their own rows, whatever is in the URL
if (!$isAdmin) {
    $where[] = "l.staffid = :me";
    $params[':me'] = $myId;
    $who = '';
} else {
    if ($who === 'admin') {
        $where[] = "l.staffid IS NULL";
    } elseif (ctype_digit($who) && (int)$who > 0) {
        $where[] = "l.staffid = :who";
        $params[':who'] = (int)$who;
    } else {
        $who = '';
    }
}

if ($category !== '' && isset($categories[$category])) {
    $where[] = "l.category = :category";
    $params[':category'] = $category;
} else {
    $category = '';
}

$d = DateTime::createFromFormat('Y-m-d', $date);
if ($date !== '' && $d && $d->format('Y-m-d') === $date) {
    $where[] = "DATE(l.created_at) = :date";
    $params[':date'] = $date;
} else {
    $date = '';
}

$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = $conn->prepare("SELECT COUNT(*) FROM activity_log l $whereSql");
$sql->execute($params);
$total = (int)$sql->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) { $page = $pages; }
$offset = ($page - 1) * $perPage;

$sql = $conn->prepare("SELECT l.created_at, l.category, l.action, l.details, l.actor_role,
        CONCAT(st.fname, ' ', st.lname) AS staff_name
    FROM activity_log l
    LEFT JOIN staff_tbl st ON st.staffid = l.staffid
    $whereSql
    ORDER BY l.created_at DESC, l.logid DESC
    LIMIT $perPage OFFSET $offset");
$sql->execute($params);
$rows = $sql->fetchAll(PDO::FETCH_ASSOC);

$staffList = [];
if ($isAdmin) {
    $staffList = $conn->query("SELECT staffid, fname, lname FROM staff_tbl ORDER BY fname, lname")->fetchAll(PDO::FETCH_ASSOC);
}

// Filters kept in the page links
$qs = http_build_query(array_filter(['who' => $who, 'category' => $category, 'date' => $date]));
$link = function ($p) use ($qs) {
    return 'activity_log.php?' . $qs . ($qs !== '' ? '&' : '') . 'page=' . $p;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Activity History</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Activity history</h2>
<h3>
    <?php echo $isAdmin
        ? 'Administrator view. Every action made by staff and administrators.'
        : 'Your own actions only.'; ?>
</h3>

<div class="card">
    <form method="get" action="activity_log.php">
        <div class="filter-row">
            <?php if ($isAdmin): ?>
                <div class="field">
                    <label for="who">Staff</label>
                    <select id="who" name="who">
                        <option value="">All staff</option>
                        <option value="admin" <?php echo ($who === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        <?php foreach ($staffList as $st): ?>
                            <option value="<?php echo (int)$st['staffid']; ?>" <?php echo ($who === (string)$st['staffid']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st['fname'] . ' ' . $st['lname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="field">
                <label for="category">Type</label>
                <select id="category" name="category">
                    <option value="">All types</option>
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($category === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
            </div>
            <div class="button-row">
                <input type="submit" value="Filter">
                <a class="btn btn-outline" href="activity_log.php">Clear filters</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <table class="records">
        <thead>
            <tr>
                <th>Date and time</th>
                <?php if ($isAdmin): ?><th>Staff</th><?php endif; ?>
                <th>Action</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($rows) === 0): ?>
            <tr><td colspan="<?php echo $isAdmin ? 4 : 3; ?>">No activity found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo date('M d, Y · g:i A', strtotime($row['created_at'])); ?></td>
                <?php if ($isAdmin): ?>
                    <td><strong><?php echo ($row['actor_role'] === 'admin') ? 'Administrator' : htmlspecialchars($row['staff_name']); ?></strong></td>
                <?php endif; ?>
                <td>
                    <?php echo htmlspecialchars($row['action']); ?>
                    <span class="cell-sub"><?php echo htmlspecialchars($categories[$row['category']] ?? $row['category']); ?></span>
                </td>
                <td><?php echo htmlspecialchars($row['details']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="button-row" style="margin-top: 14px;">
        <span class="muted">Page <?php echo $page; ?> of <?php echo $pages; ?> · <?php echo $total; ?> record<?php echo ($total === 1) ? '' : 's'; ?></span>
        <?php if ($page > 1): ?>
            <a class="btn btn-outline" href="<?php echo $link($page - 1); ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
            <a class="btn btn-outline" href="<?php echo $link($page + 1); ?>">Next</a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>