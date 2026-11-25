<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Log in | HealthSlot</title>
</head>
<body class="auth-page">

<div class="auth-hero">
    <div class="brand"><span class="brand-mark">+</span>HealthSlot</div>
    <div>
        <h1>Your barangay health center, one tap away.</h1>
        <p>Book checkups and vaccinations, and see your appointment status without lining up.</p>
    </div>
    <div>Barangay Health Center</div>
</div>

<div class="auth-panel">
    <div class="card auth-card">
        <h2>Log in</h2>

        <?php if (isset($_GET['error'])): ?>
            <p class="error">Invalid username/email or password.</p>
        <?php endif; ?>
        <?php if (isset($_GET['registered'])): ?>
            <p class="success">Account created. Please log in.</p>
        <?php endif; ?>

        <form action="login_process.php" method="post">
            <div class="field">
                <label for="uname">Email or username</label>
                <input type="text" id="uname" name="uname" required>
            </div>
            <div class="field">
                <label for="pword">Password</label>
                <input type="password" id="pword" name="pword" required>
            </div>
            <input type="submit" name="login" value="Log in" class="btn-block">
        </form>

        <p class="auth-foot">
            New resident? <a href="register.php">Create an account</a>        </p>
    </div>
</div>

</body>
</html>