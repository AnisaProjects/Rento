<?php
require_once __DIR__ . '/../includes/security.php';
include('includes/config.php');
if(isset($_POST['login']))
{
$email=$_POST['username'];
if(rate_limit_exceeded($dbh, 'admin_login', $email))
{
echo "<script>alert(" . json_encode(RATE_LIMIT_MESSAGE) . ");</script>";
}
else
{
$sql ="SELECT id,UserName,Password FROM admin WHERE UserName=:email";
$query= $dbh -> prepare($sql);
$query-> bindParam(':email', $email, PDO::PARAM_STR);
$query-> execute();
$admin=$query->fetch(PDO::FETCH_OBJ);
// Old MD5 hashes are accepted once and replaced with a bcrypt hash.
$validLogin = $admin && password_check_and_upgrade((string)($_POST['password'] ?? ''), $admin->Password, function ($newHash) use ($dbh, $admin) {
$upgrade = $dbh->prepare("UPDATE admin SET Password=:password WHERE id=:id");
$upgrade->bindParam(':password', $newHash, PDO::PARAM_STR);
$upgrade->bindParam(':id', $admin->id, PDO::PARAM_INT);
$upgrade->execute();
});
if($validLogin)
{
rate_limit_clear($dbh, 'admin_login', $email);
session_login_regenerate();
$_SESSION['alogin']=$admin->UserName;
echo "<script type='text/javascript'> document.location = 'dashboard.php'; </script>";
} else{
  
  rate_limit_record($dbh, 'admin_login', $email);
  echo "<script>alert('Invalid Details');</script>";

}
}

}

?>
<!doctype html>
<html lang="en" class="no-js">

<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
	<meta name="description" content="">
	<meta name="author" content="">

	<title>Car Rental Portal | Admin Login</title>
	<link rel="stylesheet" href="css/font-awesome.min.css">
	<link rel="stylesheet" href="css/bootstrap.min.css">
	<link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
	<link rel="stylesheet" href="css/bootstrap-social.css">
	<link rel="stylesheet" href="css/bootstrap-select.css">
	<link rel="stylesheet" href="css/fileinput.min.css">
	<link rel="stylesheet" href="css/awesome-bootstrap-checkbox.css">
	<link rel="stylesheet" href="css/style.css">
</head>

<body>
	
	<div class="login-page bk-img" style="background-image: url(img/login-bg.jpg);">
		<div class="form-content">
			<div class="container">
				<div class="row">
					<div class="col-md-6 col-md-offset-3">
						<h1 class="text-center text-bold mt-4x" style="color:#fff">Admin | Sign in</h1>
						<div class="well row pt-2x pb-3x bk-light">
							<div class="col-md-8 col-md-offset-2">
								<form method="post">
<?php echo csrf_field(); ?>

									<label for="" class="text-uppercase text-sm">Your Username </label>
									<input type="text" placeholder="Username" name="username" class="form-control mb">

									<label for="" class="text-uppercase text-sm">Password</label>
									<input type="password" placeholder="Password" name="password" class="form-control mb">
		

									<button class="btn btn-primary btn-block" name="login" type="submit">LOGIN</button>

								</form>

			<p style="margin-top: 4%" alignment="center"><a href="../index.php">Back to Home</a>	</p>
							</div>

						</div>
							
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Loading Scripts -->
	<script src="js/jquery.min.js"></script>
	<script src="js/bootstrap-select.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script src="js/jquery.dataTables.min.js"></script>
	<script src="js/dataTables.bootstrap.min.js"></script>
	<script src="js/Chart.min.js"></script>
	<script src="js/fileinput.js"></script>
	<script src="js/chartData.js"></script>
	<script src="js/main.js"></script>

</body>

</html>