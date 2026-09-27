<?php
// Sign out. Only a POST with the CSRF token (checked in includes/security.php) signs out,
// so a link or image on another site can't. A plain visit shows a "Sign out?" button.
require_once __DIR__ . '/includes/security.php';

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
    header('Location: ./', true, 303);
    exit;
}

include('includes/config.php');
if (empty($_SESSION['login'])) {
    redirect_to('./');
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Sign out';
  $pageNoindex = true; // account / utility page: not for search engines
  include('includes/head.php');
  ?>
</head>

<body class="r-page">
  <?php include('includes/header.php'); ?>
  <main id="main">
    <section class="r-section">
      <div class="container">
        <div class="r-panel text-center mx-auto" style="max-width: 420px">
          <h1 class="r-panel__title">Sign out of <?php echo content('site_name'); ?>?</h1>
          <form method="post" action="logout">
            <?php echo csrf_field(); ?>
            <button type="submit" class="r-btn r-btn--block">Sign out</button>
          </form>
          <p class="mt-3 mb-0"><a href="./">Stay signed in</a></p>
        </div>
      </div>
    </section>
  </main>
  <?php include('includes/footer.php'); ?>
  <?php include('includes/scripts.php'); ?>
</body>

</html>
