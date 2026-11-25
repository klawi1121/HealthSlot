<?php
session_start();
include_once("connection.php");

if(isset($_POST['sign_up'])){
	$firstname = $_POST['fname'];
	$mname = $_POST['mname'];
	$lastname = $_POST['lname'];
	$email = $_POST['email'];
	$uname = $_POST['uname'];
    $age = $_POST['age'];
	$birthday = $_POST['birthday'];
    $address = $_POST['address'];
	$sex = $_POST['sex'];
	$contactnum = $_POST['contactnum'];

	$pword = $_POST['pword'];
	$cpword = $_POST['cpword'];
	if(empty($pword) || empty($cpword)){
		$_SESSION['register_error'] = 'Error: Both password fields are required!';
		header('Location: register.php');
		exit();
	}
	elseif ($pword !== $cpword) {
		$_SESSION['register_error'] = 'Error: Passwords do not match! Please try again.';
		header('Location: register.php');
		exit();
	}

	$check_query = $conn->prepare("SELECT uname, email FROM resident_tbl WHERE uname = :uname OR email = :email LIMIT 1");
	$check_query->execute([
		':uname' => $uname,
		':email' => $email
	]);
	$existing_record = $check_query->fetch(PDO::FETCH_ASSOC);

	if ($existing_record) {
		if (strcasecmp($existing_record['uname'], $uname) === 0) {
			$_SESSION['register_error'] = 'Error: Username is already registered!';
		} elseif (strcasecmp($existing_record['email'], $email) === 0) {
			$_SESSION['register_error'] = 'Error: Email address is already registered!';
		}
		header('Location: register.php');
		exit(); 
	}

	$hashed_pword = password_hash($pword, PASSWORD_DEFAULT);
	
	$imgFile = $_FILES['pictureko']['name'];
	$temp_name = $_FILES['pictureko']['tmp_name'];
	$imgSize = $_FILES['pictureko']['size'];
	
	$directory = "pictures/";
	$imgExt = strtolower(pathinfo($imgFile, PATHINFO_EXTENSION));
	
	$validExt = array('jpg','png','gif');
	
	$newname = rand(1000,10000000).".".$imgExt;
	
	if(in_array($imgExt,$validExt)){
		if($imgSize < 1000000){
			move_uploaded_file($temp_name,$directory.$newname);
			$query = $conn->prepare("INSERT INTO resident_tbl (fname,mname,lname,email,uname,age,birthday,address,sex,contactnum,password,photo) 
	VALUES (:pname,:pmname,:plname,:pemail,:puname,:paage,:pbirthday,:paddress,:psex,:pcontactnum,:ppword,:pic)");
		$query->bindParam(':pname',$firstname);
		$query->bindParam(':pmname',$mname);
		$query->bindParam(':plname',$lastname);
		$query->bindParam(':pemail',$email);
		$query->bindParam(':puname',$uname);
        $query->bindParam(':paage',$age);
		$query->bindParam(':pbirthday',$birthday);
        $query->bindParam(':paddress',$address);
		$query->bindParam(':psex',$sex);
		$query->bindParam(':pcontactnum',$contactnum);
		$query->bindParam(':ppword',$hashed_pword);
		$query->bindParam(':pic',$newname);
	$query->execute();

			header('Location: login.php');
			exit();
		} else {
			$_SESSION['register_error'] = 'Sorry, file is too large';
			header('Location: register.php');
			exit();
		}
	} else {
		$_SESSION['register_error'] = 'Sorry, only jpg, png and gif are allowed';
		header('Location: register.php');
		exit();

	}
}
?>
