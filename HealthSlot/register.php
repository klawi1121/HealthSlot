<?php
session_start();
$error = $_SESSION['register_error'] ?? '';
unset($_SESSION['register_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">

<title>Registration Form</title>

</head>
<body>
<header>
	<h1> Create Account </h1>
    <p> Fill you information below. </p>
</header>

<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<form action="register_logic.php" method="post" enctype="multipart/form-data">
	<table>
		<tr>
			<td>Firstname *</td>
			<td><input type="text" placeholder="Firstname" name="fname" required></td>
		</tr>
		
		<tr>
			<td>Middlename</td>
			<td><input type="text" placeholder="Middlename" name="mname"></td>
		</tr>

		<tr>
			<td>Lastname *</td>
			<td><input type="text" placeholder="Lastname" name="lname" required></td>
		</tr>

		<tr>
			<td>Email *</td>
			<td><input type="email" placeholder="Email" name="email" required></td>
		</tr>

		<tr>
			<td>Username *</td>
			<td><input type="text" placeholder="Username" name="uname" required></td>
		</tr>

        <tr>
			<td>Age *</td>
			<td><input type="number" placeholder="Age" name="age" required></td>
		</tr>

		<tr>
			<td>Birthday *</td>
			<td><input type="date" placeholder="Birthday" name="birthday" required></td>
		</tr>

        <tr>
			<td>Address *</td>
			<td><input type="text" placeholder="Address" name="address" required></td>
		</tr>

		<tr>
			<td>Sex *</td>
			<td>
				<select name="sex" id="sex" required>
					<option value="" disabled selected>Select sex</option>
					<option value="male">Male</option>
					<option value="female">Female</option>
				</select>
			</td>
		</tr>
		
        <tr>
			<td>Contact No. *</td>
			<td><input type="number" placeholder="Contact Number" name="contactnum" required></td>
		</tr>

		<tr>
			<td> Password *</td>
			<td> <input type="password" placeholder="Password" name="pword" required ></td>
		</tr>

        <tr>
			<td> Confirm Password *</td>
			<td> <input type="password" placeholder="Password" name="cpword" required ></td>
		</tr>

		<tr>
			<td>Photo </td>
			<td><input type="file" name="pictureko" accept="image/*" required></td>
		</tr>

		<tr>
			<td>&nbsp;</td>
			<td>
				<input type="submit" value="Sign Up" name="sign_up">
				<a class="btn" href="login.php">Cancel</a>
			</td>
		</tr>
	</table>
</form>

</body>
</html>