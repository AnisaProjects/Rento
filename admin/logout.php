<?php
// Admin sign out: POST with the CSRF token only (see includes/security.php).
// A plain visit shows a "Log out?" button instead of logging out.
require_once __DIR__ . '/../includes/security.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 60*60,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: index.php", true, 303);
    exit;
}

require_once __DIR__ . '/includes/layout.php';
if (empty($_SESSION['alogin'])) {
    redirect_to('index.php');
}
admin_auth_start('Log out');
?>
<h1>Log out?</h1>
<p class="a-muted mb-4">You are signed in as <?php echo h($_SESSION['alogin']); ?>.</p>
<form method="post" action="logout.php">
  <?php echo csrf_field(); ?>
  <button class="btn btn-primary w-100 btn-lg" type="submit"><i class="fa fa-arrow-right-from-bracket" aria-hidden="true"></i>Log out</button>
</form>
<div class="a-auth__links"><a href="dashboard.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back to the dashboard</a></div>
<?php admin_auth_end(); ?>
