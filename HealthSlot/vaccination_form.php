<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");

$id = (int)($_GET['id'] ?? 0);
$editing = ($id > 0);
$row = [];

if ($editing) {
    $sql = $conn->prepare("SELECT * FROM vaccination_schedules WHERE vaccination_id = :id");
    $sql->execute([':id' => $id]);
    $row = $sql->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['vax_error'] = 'That vaccination session was not found.';
        header("Location: vaccination_offered.php");
        exit;
    }
    $row['start_time'] = substr($row['start_time'], 0, 5);
    $row['end_time'] = substr($row['end_time'], 0, 5);
}

// If the last submit failed, show what was typed
$old = $_SESSION['vax_old'] ?? [];
$error = $_SESSION['vax_error'] ?? '';
unset($_SESSION['vax_old'], $_SESSION['vax_error']);

function val($key, $old, $row) {
    return htmlspecialchars((string)($old[$key] ?? $row[$key] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title><?php echo $editing ? 'Edit Vaccination Session' : 'Add Vaccination Session'; ?></title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2><?php echo $editing ? 'Edit vaccination session' : 'Add vaccination session'; ?></h2>
<h3><?php echo $editing ? 'Update the details of this session.' : 'Post a new vaccination session for residents to book.'; ?></h3>

<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <form action="vaccination_process.php" method="post">
        <?php if ($editing): ?>
            <input type="hidden" name="vaccination_id" value="<?php echo $id; ?>">
        <?php endif; ?>

        <div class="grid-2">
            <div class="field">
                <label for="vaccine_name">Vaccine name</label>
                <input type="text" id="vaccine_name" name="vaccine_name" placeholder="e.g. Flu Vaccine" maxlength="150" value="<?php echo val('vaccine_name', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="vaccination_date">Date</label>
                <input type="date" id="vaccination_date" name="vaccination_date" value="<?php echo val('vaccination_date', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="start_time">Start time</label>
                <input type="time" id="start_time" name="start_time" value="<?php echo val('start_time', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="end_time">End time</label>
                <input type="time" id="end_time" name="end_time" value="<?php echo val('end_time', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="capacity">Capacity</label>
                <input type="number" id="capacity" name="capacity" min="1" placeholder="Number of slots" value="<?php echo val('capacity', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" placeholder="e.g. Barangay Health Center" maxlength="255" value="<?php echo val('location', $old, $row); ?>" required>
            </div>
            <div class="field">
                <label for="target_beneficiaries">Who may attend</label>
                <input type="text" id="target_beneficiaries" name="target_beneficiaries" placeholder="e.g. Children 0-5 years old" maxlength="255" value="<?php echo val('target_beneficiaries', $old, $row); ?>">
            </div>
            <div class="field">
                <label for="dose_information">Dose information</label>
                <input type="text" id="dose_information" name="dose_information" placeholder="e.g. 1st dose" maxlength="100" value="<?php echo val('dose_information', $old, $row); ?>">
            </div>
            <div class="field span-2">
                <label for="description">Description (optional)</label>
                <textarea id="description" name="description"><?php echo val('description', $old, $row); ?></textarea>
            </div>
            <div class="field">
                <label for="requirements">Requirements (optional)</label>
                <textarea id="requirements" name="requirements"><?php echo val('requirements', $old, $row); ?></textarea>
            </div>
            <div class="field">
                <label for="additional_notes">Additional notes (optional)</label>
                <textarea id="additional_notes" name="additional_notes"><?php echo val('additional_notes', $old, $row); ?></textarea>
            </div>
        </div>

        <div class="button-row" style="margin-top: 16px;">
            <?php if ($editing): ?>
                <input type="submit" name="update_vaccination" value="Save changes">
            <?php else: ?>
                <input type="submit" name="add_vaccination" value="Add session">
            <?php endif; ?>
            <a class="btn btn-outline" href="vaccination_offered.php">Back</a>
        </div>
    </form>
</div>

</body>
</html>