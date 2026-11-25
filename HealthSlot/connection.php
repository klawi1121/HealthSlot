<?php
$host = "localhost";
$database = "healthslotdb";
$username = "root";
$password = "";

try {
	$conn = new PDO("mysql:host=$host;dbname=$database",$username,$password);
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

}catch(PDOException $e){
	echo "Connection Failed! :".$e->getMessage();
}

// Marks a service or vaccination session as Completed once its end time has passed
if (isset($conn)) {
    date_default_timezone_set('Asia/Manila');
    $now = date('Y-m-d H:i:s');

    try {
        $sql = $conn->prepare("UPDATE service_tbl SET status = 'Completed'
            WHERE status IN ('Available', 'Full') AND TIMESTAMP(servicedate, endtime) <= :now");
        $sql->execute([':now' => $now]);

        $sql = $conn->prepare("UPDATE vaccination_schedules SET availability_status = 'Completed'
            WHERE availability_status IN ('Available', 'Limited Slots', 'Full')
              AND TIMESTAMP(vaccination_date, end_time) <= :now");
        $sql->execute([':now' => $now]);
    } catch (PDOException $e) {
        // Ignore: this is a convenience check and must never stop the page
    }
}
?>