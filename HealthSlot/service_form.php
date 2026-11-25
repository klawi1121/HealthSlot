<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");

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

$id = (int)($_GET['id'] ?? 0);
$editing = ($id > 0);
$row = [];

if ($editing) {
    $sql = $conn->prepare("SELECT * FROM service_tbl WHERE serviceid = :id");
    $sql->execute([':id' => $id]);
    $row = $sql->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['service_error'] = 'That service was not found.';
        header("Location: services_offered.php");
        exit;
    }
    $row['starttime'] = substr($row['starttime'], 0, 5);
    $row['endtime'] = substr($row['endtime'], 0, 5);
}

// If the last submit failed, show what was typed
$old = $_SESSION['service_old'] ?? [];
$error = $_SESSION['service_error'] ?? '';
unset($_SESSION['service_old'], $_SESSION['service_error']);

function val($key, $old, $row) {
    return htmlspecialchars((string)($old[$key] ?? $row[$key] ?? ''));
}

$selectedCategory = $old['servicecategory'] ?? $row['servicecategory'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title><?php echo $editing ? 'Edit Service' : 'Add Service'; ?></title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2><?php echo $editing ? 'Edit service' : 'Add service'; ?></h2>
<h3><?php echo $editing ? 'Update the details of this service.' : 'Post a new service for residents to book.'; ?></h3>

<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <form action="service_process.php" method="post">
        <?php if ($editing): ?>
            <input type="hidden" name="serviceid" value="<?php echo $id; ?>">
        <?php endif; ?>

        <div class="grid-2">
            <div class="field">
                <label for="servicename">Service name</label>
                <input type="text" id="servicename" name="servicename" placeholder="e.g. General Checkup" maxlength="50" value="<?php echo val('servicename', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="servicecategory">Category</label>
                <select id="servicecategory" name="servicecategory" required>
                    <option value="" disabled <?php echo ($selectedCategory === '') ? 'selected' : ''; ?>>Select category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($selectedCategory === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="servicedate">Date</label>
                <input type="date" id="servicedate" name="servicedate" value="<?php echo val('servicedate', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="capacity">Capacity</label>
                <input type="number" id="capacity" name="capacity" min="1" placeholder="Number of slots" value="<?php echo val('capacity', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="starttime">Start time</label>
                <input type="time" id="starttime" name="starttime" value="<?php echo val('starttime', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="endtime">End time</label>
                <input type="time" id="endtime" name="endtime" value="<?php echo val('endtime', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" placeholder="e.g. Barangay Health Center" maxlength="50" value="<?php echo val('location', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="beneficiaries">Who may attend</label>
                <input type="text" id="beneficiaries" name="beneficiaries" placeholder="e.g. Anyone" maxlength="100" value="<?php echo val('beneficiaries', $old, $row); ?>" required>
            </div>
            <div class="field span-2">
                <label for="requirements">Requirements (optional)</label>
                <input type="text" id="requirements" name="requirements" maxlength="100" value="<?php echo val('requirements', $old, $row); ?>">
            </div>
            <div class="field span-2">
                <label for="description">Description</label>
                <textarea id="description" name="description" maxlength="100" placeholder="Short description" required><?php echo val('description', $old, $row); ?></textarea>
            </div>
        </div>

        <div class="button-row" style="margin-top: 16px;">
            <?php if ($editing): ?>
                <input type="submit" name="update_service" value="Save changes">
            <?php else: ?>
                <input type="submit" name="add_service" value="Add service">
            <?php endif; ?>
            <a class="btn btn-outline" href="services_offered.php">Back</a>
        </div>
    </form>
</div>

</body>
</html>