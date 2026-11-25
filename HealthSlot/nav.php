<?php
// Sidebar navigation. Include it as the first thing inside <body> on logged-in pages.
$role = $_SESSION['role'] ?? '';
$current = basename($_SERVER['PHP_SELF']);

// Pages that belong under another menu item
$aliases = [
    'resident_book.php' => 'resident_services.php',
    'resident_profile_update.php' => 'resident_profile_page.php',
    'resident_password.php' => 'resident_profile_page.php',
    'staff_profile_update.php' => 'staff_profile.php',
    'staff_password.php' => 'staff_profile.php',
    'admin_staff_account_form.php' => 'admin_staff_accounts.php',
    'admin_admin_account_form.php' => 'admin_admin_accounts.php',
    'service_form.php' => 'services_offered.php',
    'vaccination_form.php' => 'vaccination_offered.php',
    'staff_account_form.php' => 'admin_staff_accounts.php',
];
if (isset($aliases[$current])) {
    $current = $aliases[$current];
}

if ($role === 'admin') {
    $links = [
        'admin_dashboard.php' => 'Dashboard',
        'booking_request.php' => 'Booking requests',
        'book_for_resident.php' => 'Book for a resident',
        'services_offered.php' => 'Services',
        'vaccination_offered.php' => 'Vaccination sessions',
        'activity_log.php' => 'Activity history',
        'admin_staff_accounts.php' => 'Staff accounts',
        'admin_admin_accounts.php' => 'Admin accounts',
        'admin_reports.php' => 'Reports and analytics',
        'staff_profile.php' => 'My profile'
    ];
} elseif ($role === 'staff') {
    $links = [
        'staff_dashboard.php' => 'Dashboard',
        'booking_request.php' => 'Booking requests',
        'book_for_resident.php' => 'Book for a resident',
        'services_offered.php' => 'Services',
        'vaccination_offered.php' => 'Vaccination sessions',
        'activity_log.php' => 'Activity history',
        'staff_profile.php' => 'My profile'
    ];
} else {
    $links = [
        'resident_dashboard.php' => 'Dashboard',
        'resident_services.php' => 'Services',
        'resident_vaccination.php' => 'Vaccination',
        'resident_appointments.php' => 'My appointments',
        'resident_profile_page.php' => 'My profile'
    ];
}

// Name and role shown at the bottom of the sidebar
include_once("connection.php");
$displayName = 'User';
$roleLabel = strtoupper($role !== '' ? $role : 'user');
$profileLink = ($role === 'resident') ? 'resident_profile_page.php' : 'staff_profile.php';

$sidePhoto = '';

try {
    if ($role === 'admin' || $role === 'staff') {
        $q = $conn->prepare("SELECT fname, lname, photo FROM staff_tbl WHERE staffid = :id");
        $q->execute([':id' => (int)($_SESSION['userID'] ?? 0)]);
        $who = $q->fetch(PDO::FETCH_ASSOC);
        if ($who) {
            $displayName = $who['fname'] . ' ' . $who['lname'];
            $sidePhoto = (string)$who['photo'];
        }
    } elseif ($role === 'resident') {
        $q = $conn->prepare("SELECT fname, photo FROM resident_tbl WHERE residentid = :id");
        $q->execute([':id' => (int)($_SESSION['userID'] ?? 0)]);
        $who = $q->fetch(PDO::FETCH_ASSOC);
        if ($who) {
            $displayName = $who['fname'];
            $sidePhoto = (string)$who['photo'];
        }
    }
} catch (PDOException $e) {
    // If the new photo column is not created yet, keep the default name and icon
}

$sidePhotoPath = 'pictures/' . basename($sidePhoto);
$hasSidePhoto = ($sidePhoto !== '' && is_file($sidePhotoPath));

?>
<div class="profile-actions">
    <?php foreach ($links as $file => $label): ?>
        <a class="btn <?php echo ($file === $current) ? 'on' : ''; ?>" href="<?php echo $file; ?>"><?php echo $label; ?></a>
    <?php endforeach; ?>

    <div class="side-user">
        <a class="side-who" href="<?php echo $profileLink; ?>" title="My profile">
            <span class="side-avatar"<?php echo $hasSidePhoto ? ' style="overflow:hidden;"' : ''; ?>>
                <?php if ($hasSidePhoto): ?>
                    <img src="<?php echo htmlspecialchars($sidePhotoPath); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <?php if ($role === 'admin'): ?>
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <?php elseif ($role === 'staff'): ?>
                        <path d="M12 5v14M5 12h14"/>
                    <?php else: ?>
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    <?php endif; ?>
                </svg>
                <?php endif; ?>
            </span>
            <span class="side-text">
                <span class="side-name"><?php echo htmlspecialchars($displayName); ?></span>
                <span class="side-role"><?php echo htmlspecialchars($roleLabel); ?></span>
            </span>
        </a>
        <a class="side-logout" href="logout.php">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Log out
        </a>
    </div>
</div>