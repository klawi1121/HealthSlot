<?php
include_once("session.php");
require_role(['staff', 'admin']);
include_once("connection.php");
include_once("functions.php");

date_default_timezone_set('Asia/Manila');

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$error = $_SESSION['sbook_error'] ?? '';
$old = $_SESSION['sbook_old'] ?? [];
unset($_SESSION['sbook_error'], $_SESSION['sbook_old']);

$picked = in_array($type, ['service', 'vaccination']) && $id > 0;

// ---------- Step 2: the form for one session ----------
if ($picked) {
    $s = getSession($conn, $type, $id);

    if (!$s || !(int)$s['is_open']) {
        $_SESSION['sbook_error'] = 'That session is no longer available.';
        header("Location: book_for_resident.php");
        exit;
    }

    $left = (int)$s['capacity'] - countTaken($conn, $type, $id);
    if ($left <= 0) {
        $_SESSION['sbook_error'] = 'That session is already full.';
        header("Location: book_for_resident.php");
        exit;
    }

    // Time options. For a session held today, remove the times that already passed.
    $slots = timeSlots($s['session_start'], $s['session_end']);
    if ($s['session_date'] < date('Y-m-d')) {
        $slots = [];
    } elseif ($s['session_date'] === date('Y-m-d')) {
        $now = date('H:i:s');
        $slots = array_values(array_filter($slots, function ($t) use ($now) { return $t > $now; }));
    }
    if (count($slots) === 0) {
        $_SESSION['sbook_error'] = 'There are no time options left for that session.';
        header("Location: book_for_resident.php");
        exit;
    }
}

// ---------- Step 1: the list of sessions ----------
$search = trim($_GET['search'] ?? '');
$kind = $_GET['kind'] ?? '';
$date = trim($_GET['date'] ?? '');

if (!in_array($kind, ['service', 'vaccination'])) { $kind = ''; }
$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) { $date = ''; }

$sessions = [];
if (!$picked) {
    $today = date('Y-m-d');
    $sql = $conn->prepare("SELECT 'service' AS kind, s.serviceid AS sid, s.servicename AS name,
            s.servicecategory AS category, s.servicedate AS d, s.starttime AS st, s.endtime AS et,
            s.capacity, s.location AS loc, s.beneficiaries AS who, s.requirements AS req,
            s.description AS descr, NULL AS dose,
            (SELECT COUNT(*) FROM appointment_tbl a
              WHERE a.serviceid = s.serviceid AND a.status IN ('confirmed', 'completed', 'missed')) AS taken
        FROM service_tbl s
        WHERE s.servicedate >= :t1 AND s.status = 'Available'
        UNION ALL
        SELECT 'vaccination', v.vaccination_id, v.vaccine_name,
            'Immunization & Vaccination', v.vaccination_date, v.start_time, v.end_time,
            v.capacity, v.location, v.target_beneficiaries, v.requirements,
            v.description, v.dose_information,
            (SELECT COUNT(*) FROM appointment_tbl a
              WHERE a.vaccination_id = v.vaccination_id AND a.status IN ('confirmed', 'completed', 'missed'))
        FROM vaccination_schedules v
        WHERE v.vaccination_date >= :t2 AND v.availability_status IN ('Available', 'Limited Slots')
        ORDER BY d, st");
    $sql->execute([':t1' => $today, ':t2' => $today]);
    $all = $sql->fetchAll(PDO::FETCH_ASSOC);

    $sessions = array_values(array_filter($all, function ($r) use ($search, $kind, $date) {
        if ($kind !== '' && $r['kind'] !== $kind) { return false; }
        if ($date !== '' && $r['d'] !== $date) { return false; }
        if ($search !== '' && stripos($r['name'], $search) === false) { return false; }
        return true;
    }));
}

function val($key, $old) {
    return htmlspecialchars($old[$key] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Book for a Resident</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Book for a resident</h2>
<h3>For residents who cannot book online, such as senior citizens. Ask for their details by phone or in person.</h3>

<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<?php if (!$picked): ?>

    <div class="card">
        <form method="get" action="book_for_resident.php">
            <div class="filter-row">
                <div class="field">
                    <label for="search">Session name</label>
                    <input type="text" id="search" name="search" placeholder="Search name" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="field">
                    <label for="kind">Type</label>
                    <select id="kind" name="kind">
                        <option value="">All types</option>
                        <option value="service" <?php echo ($kind === 'service') ? 'selected' : ''; ?>>Service</option>
                        <option value="vaccination" <?php echo ($kind === 'vaccination') ? 'selected' : ''; ?>>Vaccination</option>
                    </select>
                </div>
                <div class="field">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
                </div>
                <div class="button-row">
                    <input type="submit" value="Filter">
                    <a class="btn btn-outline" href="book_for_resident.php">Clear filters</a>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h4>Choose a session</h4>
        <table class="records">
            <thead>
                <tr>
                    <th>Session</th>
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
            <?php if (count($sessions) === 0): ?>
                <tr><td colspan="8">
                    <?php echo ($search !== '' || $kind !== '' || $date !== '') ? 'No sessions match your filters.' : 'No sessions available right now.'; ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($sessions as $r): ?>
                <?php $slotsLeft = max(0, (int)$r['capacity'] - (int)$r['taken']); ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                        <span class="cell-sub"><?php echo htmlspecialchars($r['category']); ?></span>
                        <?php if (!empty($r['dose'])): ?>
                            <span class="cell-sub">Dose: <?php echo htmlspecialchars($r['dose']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($r['descr']) ? htmlspecialchars($r['descr']) : '-'; ?></td>
                    <td>
                        <?php echo date('M d, Y', strtotime($r['d'])); ?>
                        <span class="cell-sub"><?php echo date('g:i A', strtotime($r['st'])) . ' - ' . date('g:i A', strtotime($r['et'])); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($r['loc'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($r['who'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($r['req'] ?? ''); ?></td>
                    <td>
                        <?php if ($slotsLeft === 0): ?>
                            <span class="pill pill-full">Full</span>
                        <?php else: ?>
                            <?php echo $slotsLeft; ?> of <?php echo (int)$r['capacity']; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($slotsLeft > 0): ?>
                            <a class="btn" href="book_for_resident.php?type=<?php echo $r['kind']; ?>&id=<?php echo (int)$r['sid']; ?>">Book</a>
                        <?php else: ?>
                            <span class="muted">No action</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>

    <div class="card">
        <h4><?php echo htmlspecialchars($s['name']); ?></h4>
        <p class="muted" style="margin: 0;">
            <?php echo date('F d, Y', strtotime($s['session_date'])); ?>,
            <?php echo date('g:i A', strtotime($s['session_start'])) . ' - ' . date('g:i A', strtotime($s['session_end'])); ?>
            · Slots left: <?php echo $left; ?>
        </p>
    </div>

    <div class="card">
        <form action="book_for_resident_process.php" method="post">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
            <input type="hidden" name="id" value="<?php echo $id; ?>">

            <div class="grid-2">
                <?php renderTimeField($conn, $type, $id, $s, $slots, $old['appointment_time'] ?? ''); ?>
                <div class="field">
                    <label for="patient_name">Patient full name</label>
                    <input type="text" id="patient_name" name="patient_name" maxlength="100" value="<?php echo val('patient_name', $old); ?>" required>
                </div>
                <div class="field">
                    <label for="patient_age">Age</label>
                    <input type="number" id="patient_age" name="patient_age" min="0" max="120" value="<?php echo val('patient_age', $old); ?>" required>
                </div>
                <div class="field">
                    <label for="patient_address">Address</label>
                    <input type="text" id="patient_address" name="patient_address" maxlength="255" value="<?php echo val('patient_address', $old); ?>" required>
                </div>
                <div class="field">
                    <label for="patient_contact">Contact number</label>
                    <input type="text" id="patient_contact" name="patient_contact" maxlength="15" value="<?php echo val('patient_contact', $old); ?>" required>
                </div>
                <div class="field span-2">
                    <span class="muted" id="companion_note">Companion (optional for patients 18 and above)</span>
                </div>
                <div class="field">
                    <label for="companion_name">Companion name</label>
                    <input type="text" id="companion_name" name="companion_name" maxlength="100" value="<?php echo val('companion_name', $old); ?>">
                </div>
                <div class="field">
                    <label for="companion_contact">Companion contact</label>
                    <input type="text" id="companion_contact" name="companion_contact" maxlength="15" placeholder="Optional" value="<?php echo val('companion_contact', $old); ?>">
                </div>
                <div class="field span-2">
                    <label class="check-row">
                        <input type="checkbox" name="allow_same_day" value="1">
                        Staff override: allow a second booking for this service on the same day
                    </label>
                </div>
            </div>

            <div class="button-row" style="margin-top: 16px;">
                <input type="submit" name="book" value="Confirm booking">
                <a class="btn btn-outline" href="book_for_resident.php">Back</a>
            </div>
        </form>
    </div>

    <script>
        var ageInput = document.getElementById('patient_age');
        var companionInput = document.getElementById('companion_name');
        var companionNote = document.getElementById('companion_note');

        function checkAge() {
            var age = parseInt(ageInput.value, 10);
            var minor = !isNaN(age) && age < 18;
            companionInput.required = minor;
            companionNote.textContent = minor
                ? 'Companion (required: the patient is under 18)'
                : 'Companion (optional for patients 18 and above)';
        }

        ageInput.addEventListener('input', checkAge);
        checkAge();
    </script>

<?php endif; ?>

</body>
</html>