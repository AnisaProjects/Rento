<?php
require_once("includes/config.php");
// code user email availablity
if (!empty($_POST["emailid"])) {
	$email = $_POST["emailid"];
	// Per IP: this endpoint reveals whether an email is registered, so repeated lookups are throttled.
	if (rate_limit_exceeded($dbh, 'email_check', null, RATE_LIMIT_EMAIL_CHECKS)) {
		echo "<span class='r-hint--error'>" . htmlentities(RATE_LIMIT_MESSAGE) . "</span>";
		exit;
	}
	rate_limit_record($dbh, 'email_check');
	if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {

		echo "<span class='r-hint--error'>Please enter a valid email address.</span>";
	} else {
		$sql = "SELECT EmailId FROM tblusers WHERE EmailId=:email";
		$query = $dbh->prepare($sql);
		$query->bindParam(':email', $email, PDO::PARAM_STR);
		$query->execute();
		$results = $query->fetchAll(PDO::FETCH_OBJ);
		$cnt = 1;
		if ($query->rowCount() > 0) {
			echo "<span class='r-hint--error'>This email is already registered. Try logging in.</span>";
			echo "<script>$('#submit').prop('disabled',true);</script>";
		} else {

			echo "<span class='r-hint--success'>Email is available.</span>";
			echo "<script>$('#submit').prop('disabled',false);</script>";
		}
	}
}


?>