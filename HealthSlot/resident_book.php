<?php
include_once("session.php");
require_role(['resident']);
include_once("connection.php");
include_once("functions.php");

date_default_timezone_set('Asia/Manila');

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$list = ($type === 'vaccination') ? 'resident_vaccination.php' : 'resident_services.php';

function leave($message, $list) {
    $_SESSION['res_error'] = $message;
    header("Location: " . $list);
    exit;
}

if (!in_array($type, ['service', 'vaccination']) || $id < 1) {
    leave('Invalid booking request.', 'resident_services.php');
}

$s = getSession($conn, $type, $id);
if (!$s || !(int)$s['is_open']) {
    leave('This session is no longer available.', $list);
}

$left = (int)$s['capacity'] - countTaken($conn, $type, $id);
if ($left <= 0) {
    leave('Sorry, this session is already full.', $list);
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
    leave('There are no time options left for this session.', $list);
}

// Prefill the form with the resident's own information
$sql = $conn->prepare("SELECT fname, lname, birthday, address, contactnum FROM resident_tbl WHERE residentid = :id");
$sql->execute([':id' => (int)$_SESSION['userID']]);
$me = $sql->fetch(PDO::FETCH_ASSOC);

$defaults = [
    'patient_name' => $me ? trim($me['fname'] . ' ' . $me['lname']) : '',
    'patient_age' => $me ? (new DateTime($me['birthday']))->diff(new DateTime('today'))->y : '',
    'patient_address' => $me ? $me['address'] : '',
    'patient_contact' => $me ? $me['contactnum'] : '',
    'companion_name' => '',
    'companion_contact' => '',
    'appointment_time' => ''
];

// If the last submit failed, show what the resident typed
$old = $_SESSION['book_old'] ?? [];
$error = $_SESSION['book_error'] ?? '';
unset($_SESSION['book_old'], $_SESSION['book_error']);

function field($key, $old, $defaults) {
    return htmlspecialchars($old[$key] ?? $defaults[$key] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Book Appointment</title>
</head>
<body class="dashboard-page">

<?php include 'nav.php'; ?>

<h2>Book appointment</h2>
<h3>Fill in the details of the person who will receive the service.</h3>

<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <h4><?php echo htmlspecialchars($s['name']); ?></h4>
    <p class="muted" style="margin: 0;">
        <?php echo date('F d, Y', strtotime($s['session_date'])); ?>,
        <?php echo date('g:i A', strtotime($s['session_start'])) . ' - ' . date('g:i A', strtotime($s['session_end'])); ?>
        · Slots left: <?php echo $left; ?>
    </p>
</div>

<div class="card">
    <form action="book_process.php" method="post">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div class="grid-2">
            <?php renderTimeField($conn, $type, $id, $s, $slots, $old['appointment_time'] ?? ''); ?>
            <div class="field">
                <label for="patient_name">Patient full name</label>
                <input type="text" id="patient_name" name="patient_name" maxlength="100" value="<?php echo field('patient_name', $old, $defaults); ?>" required>
            </div>
            <div class="field">
                <label for="patient_age">Age</label>
                <input type="number" id="patient_age" name="patient_age" min="0" max="120" value="<?php echo field('patient_age', $old, $defaults); ?>" required>
            </div>
            <div class="field">
                <label for="patient_address">Address</label>
                <input type="text" id="patient_address" name="patient_address" maxlength="255" value="<?php echo field('patient_address', $old, $defaults); ?>" required>
            </div>
            <div class="field">
                <label for="patient_contact">Contact number</label>
                <input type="text" id="patient_contact" name="patient_contact" maxlength="15" value="<?php echo field('patient_contact', $old, $defaults); ?>" required>
            </div>
            <div class="field span-2">
                <span class="muted" id="companion_note">Companion (optional for patients 18 and above)</span>
            </div>
            <div class="field">
                <label for="companion_name">Companion name</label>
                <input type="text" id="companion_name" name="companion_name" maxlength="100" value="<?php echo field('companion_name', $old, $defaults); ?>">
            </div>
            <div class="field">
                <label for="companion_contact">Companion contact</label>
                <input type="text" id="companion_contact" name="companion_contact" maxlength="15" placeholder="Optional" value="<?php echo field('companion_contact', $old, $defaults); ?>">
            </div>
        </div>

        <div class="button-row" style="margin-top: 16px;">
            <input type="submit" name="book" value="Submit request">
            <a class="btn btn-outline" href="<?php echo $list; ?>">Back</a>
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

</body>
</html>