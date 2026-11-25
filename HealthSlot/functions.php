<?php
// Length of one time option in the booking form, in minutes
if (!defined('SLOT_MINUTES')) {
    define('SLOT_MINUTES', 60);
}

// Minimum gap between two appointments of the same patient on the same day, in minutes.
// Set it to 0 to allow back-to-back appointments.
if (!defined('BUFFER_MINUTES')) {
    define('BUFFER_MINUTES', 30);
}

// Counts the slots a session has used. A slot is taken once staff confirm the booking.
// Confirmed, completed and missed appointments keep their slot.
// Pending (not yet confirmed) and cancelled appointments do not take a slot.
function countTaken($conn, $type, $id) {
    if ($type === 'service') {
        $sql = $conn->prepare("SELECT COUNT(*) FROM appointment_tbl
            WHERE serviceid = :id AND status IN ('confirmed', 'completed', 'missed')");
    } else {
        $sql = $conn->prepare("SELECT COUNT(*) FROM appointment_tbl
            WHERE vaccination_id = :id AND status IN ('confirmed', 'completed', 'missed')");
    }
    $sql->execute([':id' => $id]);
    return (int)$sql->fetchColumn();
}

// Returns one session in the same shape for services and vaccination, or false if not found.
// $lock = true locks the row, so it must be used inside a transaction.
function getSession($conn, $type, $id, $lock = false) {
    $suffix = $lock ? ' FOR UPDATE' : '';

    if ($type === 'service') {
        $sql = $conn->prepare("SELECT servicename AS name, servicedate AS session_date,
                starttime AS session_start, endtime AS session_end, capacity,
                (status = 'Available') AS is_open
            FROM service_tbl WHERE serviceid = :id" . $suffix);
    } else {
        $sql = $conn->prepare("SELECT vaccine_name AS name, vaccination_date AS session_date,
                start_time AS session_start, end_time AS session_end, capacity,
                (availability_status IN ('Available', 'Limited Slots')) AS is_open
            FROM vaccination_schedules WHERE vaccination_id = :id" . $suffix);
    }
    $sql->execute([':id' => $id]);
    return $sql->fetch(PDO::FETCH_ASSOC);
}

// Builds the list of start times a resident can choose, from the session start to the session end.
function timeSlots($start, $end) {
    $slots = [];
    $t = strtotime('1970-01-01 ' . $start);
    $e = strtotime('1970-01-01 ' . $end);

    while ($t + SLOT_MINUTES * 60 <= $e) {
        $slots[] = date('H:i:s', $t);
        $t += SLOT_MINUTES * 60;
    }

    // If the session is shorter than one option, offer the start time only
    if (count($slots) === 0) {
        $slots[] = date('H:i:s', strtotime('1970-01-01 ' . $start));
    }
    return $slots;
}

// Counts this patient's appointments (not cancelled) on that date that overlap the chosen time,
// with an optional buffer in minutes around it.
// A patient is the same person when the full name AND the age match, so two people who share
// a name (for example family members) are not blocked by each other.
function overlapCount($conn, $patientName, $patientAge, $date, $slotStart, $bufferMinutes) {
    $start = strtotime('1970-01-01 ' . $slotStart);
    $endWithBuffer = date('H:i:s', $start + (SLOT_MINUTES + $bufferMinutes) * 60);
    $slotLength = gmdate('H:i:s', SLOT_MINUTES * 60);
    $buffer = gmdate('H:i:s', $bufferMinutes * 60);

    $sql = $conn->prepare("SELECT COUNT(*)
        FROM appointment_tbl a
        LEFT JOIN service_tbl s ON s.serviceid = a.serviceid
        LEFT JOIN vaccination_schedules v ON v.vaccination_id = a.vaccination_id
        WHERE LOWER(a.patient_name) = LOWER(:pname)
          AND a.patient_age = :age
          AND a.status <> 'cancelled'
          AND COALESCE(s.servicedate, v.vaccination_date) = :d
          AND a.appointment_time < :endt
          AND ADDTIME(ADDTIME(a.appointment_time, :len), :buf) > :startt");
    $sql->execute([
        ':pname' => $patientName,
        ':age' => $patientAge,
        ':d' => $date,
        ':endt' => $endWithBuffer,
        ':len' => $slotLength,
        ':buf' => $buffer,
        ':startt' => $slotStart
    ]);
    return (int)$sql->fetchColumn();
}

// Returns 'overlap' (same time), 'close' (too near another appointment), or '' (no problem)
function findConflict($conn, $patientName, $patientAge, $date, $slotStart) {
    if (overlapCount($conn, $patientName, $patientAge, $date, $slotStart, 0) > 0) {
        return 'overlap';
    }
    if (BUFFER_MINUTES > 0 && overlapCount($conn, $patientName, $patientAge, $date, $slotStart, BUFFER_MINUTES) > 0) {
        return 'close';
    }
    return '';
}

// True if this patient already has the same service (or vaccine) on the same day.
// Pending, confirmed and completed bookings count. Missed and cancelled ones do not,
// so a patient who missed the morning can still book another time.
function hasSameServiceSameDay($conn, $patientName, $patientAge, $type, $sessionName, $date) {
    $sql = $conn->prepare("SELECT COUNT(*)
        FROM appointment_tbl a
        LEFT JOIN service_tbl s ON s.serviceid = a.serviceid
        LEFT JOIN vaccination_schedules v ON v.vaccination_id = a.vaccination_id
        WHERE LOWER(a.patient_name) = LOWER(:pname)
          AND a.patient_age = :age
          AND a.status IN ('pending', 'confirmed', 'completed')
          AND COALESCE(s.servicedate, v.vaccination_date) = :d
          AND COALESCE(s.servicename, v.vaccine_name) = :sname
          AND ((a.serviceid IS NOT NULL) = :isservice)");
    $sql->execute([
        ':pname' => $patientName,
        ':age' => $patientAge,
        ':d' => $date,
        ':sname' => $sessionName,
        ':isservice' => ($type === 'service') ? 1 : 0
    ]);
    return (int)$sql->fetchColumn() > 0;
}

// Records one action in the activity history. It never stops the real action if logging fails.
function logActivity($conn, $category, $action, $details) {
    date_default_timezone_set('Asia/Manila');
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'staff' && $role !== 'admin') {
        return;
    }
    $staffId = ($role === 'staff') ? (int)$_SESSION['userID'] : null;

    try {
        $sql = $conn->prepare("INSERT INTO activity_log (staffid, actor_role, category, action, details, created_at)
            VALUES (:sid, :role, :category, :action, :details, :time)");
        $sql->execute([
            ':sid' => $staffId,
            ':role' => $role,
            ':category' => $category,
            ':action' => $action,
            ':details' => mb_substr($details, 0, 255),
            ':time' => date('Y-m-d H:i:s')
        ]);
    } catch (PDOException $e) {
        // Ignore: history is extra, the main action already succeeded
    }
}

// ---------- Hourly limits, priority lane and queue numbers ----------

// Patients aged 60 and above are senior citizens and get the priority lane automatically
if (!defined('PRIORITY_AGE')) {
    define('PRIORITY_AGE', 60);
}
// Share of each hour's slots held for priority patients (at least 1, never the whole hour)
if (!defined('PRIORITY_RESERVE_PERCENT')) {
    define('PRIORITY_RESERVE_PERCENT', 20);
}
// The held slots open to everyone this many hours before the chosen time
if (!defined('PRIORITY_RELEASE_HOURS')) {
    define('PRIORITY_RELEASE_HOURS', 24);
}

// How many slots are held for priority patients in one hour
function priorityReserved($limit) {
    if ($limit <= 1) {
        return 0;
    }
    return min($limit - 1, max(1, (int)ceil($limit * PRIORITY_RESERVE_PERCENT / 100)));
}

// Slots still open in one hour of a session, for a regular patient and for a priority patient.
// $s is the row from getSession().
function slotAvailability($conn, $type, $id, $s, $slot) {
    $hours = max(1, count(timeSlots($s['session_start'], $s['session_end'])));
    $limit = (int)ceil(max(1, (int)$s['capacity']) / $hours);
    $reserved = priorityReserved($limit);

    $column = ($type === 'service') ? 'serviceid' : 'vaccination_id';
    $sql = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(is_priority = 1), 0) AS prio
        FROM appointment_tbl
        WHERE $column = :id AND appointment_time = :t
          AND status IN ('confirmed', 'completed', 'missed')");
    $sql->execute([':id' => $id, ':t' => $slot]);
    $row = $sql->fetch(PDO::FETCH_ASSOC);

    $total = (int)$row['total'];
    $regular = $total - (int)$row['prio'];

    $sessionLeft = max(0, (int)$s['capacity'] - countTaken($conn, $type, $id));
    $leftPriority = max(0, min($limit - $total, $sessionLeft));

    // The held slots stay reserved until PRIORITY_RELEASE_HOURS before this hour
    $released = (strtotime($s['session_date'] . ' ' . $slot) - PRIORITY_RELEASE_HOURS * 3600) <= time();
    if ($released || $reserved === 0) {
        $leftRegular = $leftPriority;
    } else {
        $leftRegular = max(0, min($leftPriority, ($limit - $reserved) - $regular));
    }

    return [
        'limit' => $limit,
        'reserved' => $reserved,
        'left_priority' => $leftPriority,
        'left_regular' => $leftRegular
    ];
}

// Next queue number for this session, hour and lane. Cancelled bookings keep their number,
// so a number is never given twice.
function nextQueueNumber($conn, $type, $id, $slot, $isPriority) {
    $column = ($type === 'service') ? 'serviceid' : 'vaccination_id';
    $sql = $conn->prepare("SELECT COALESCE(MAX(queue_no), 0) + 1 FROM appointment_tbl
        WHERE $column = :id AND appointment_time = :t AND is_priority = :p");
    $sql->execute([':id' => $id, ':t' => $slot, ':p' => $isPriority ? 1 : 0]);
    return (int)$sql->fetchColumn();
}

// "P-2" for the priority lane, "2" for the regular lane, "-" if there is no number
function queueLabel($queueNo, $isPriority) {
    if ($queueNo === null || $queueNo === '') {
        return '-';
    }
    return ((int)$isPriority === 1 ? 'P-' : '') . (int)$queueNo;
}

// Prints the time dropdown for the booking forms. It shows how many slots are left in each hour
// and changes to the priority lane when the age field is 60 or above.
function renderTimeField($conn, $type, $id, $s, $slots, $selected = '') {
    echo '<div class="field span-2">';
    echo '<label for="appointment_time">Preferred time</label>';
    echo '<select id="appointment_time" name="appointment_time" required>';

    foreach ($slots as $t) {
        $a = slotAvailability($conn, $type, $id, $s, $t);
        $label = date('g:i A', strtotime($t)) . ' - ' . date('g:i A', strtotime($t) + SLOT_MINUTES * 60);
        echo '<option value="' . htmlspecialchars($t) . '"'
            . ' data-label="' . htmlspecialchars($label) . '"'
            . ' data-regular="' . (int)$a['left_regular'] . '"'
            . ' data-priority="' . (int)$a['left_priority'] . '"'
            . (($selected === $t) ? ' selected' : '') . '>'
            . htmlspecialchars($label) . '</option>';
    }

    echo '</select>';
    echo '<span class="muted" id="time_note"></span>';
    echo '</div>';
    echo '<script>var PRIORITY_AGE = ' . (int)PRIORITY_AGE . ';</script>';

    echo <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function () {
    var age = document.getElementById('patient_age');
    var select = document.getElementById('appointment_time');
    var note = document.getElementById('time_note');
    if (!age || !select) { return; }

    var priorityNote = document.createElement('span');
    priorityNote.className = 'muted';
    age.parentNode.appendChild(priorityNote);

    function update() {
        var value = parseInt(age.value, 10);
        var senior = !isNaN(value) && value >= PRIORITY_AGE;
        var key = senior ? 'priority' : 'regular';
        var firstOpen = null;

        for (var i = 0; i < select.options.length; i++) {
            var option = select.options[i];
            var left = parseInt(option.dataset[key], 10);
            option.disabled = left <= 0;
            option.textContent = option.dataset.label + (left > 0 ? ' (' + left + ' left)' : ' (full)');
            if (left > 0 && firstOpen === null) { firstOpen = option; }
        }

        var current = select.options[select.selectedIndex];
        if (current && current.disabled && firstOpen !== null) {
            select.value = firstOpen.value;
        }

        note.textContent = (firstOpen === null) ? 'No time is open for this patient right now.' : '';
        priorityNote.textContent = senior
            ? 'Priority lane: senior citizens (' + PRIORITY_AGE + ' and above) are served first. Please bring a valid ID.'
            : '';
    }

    age.addEventListener('input', update);
    update();
});
</script>
JS;
}
?>