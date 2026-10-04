<?php
session_start();
// Database connection
require_once 'config/db_config.php';

// This page is standalone - it does not include includes/top_bar.php - so it needs the asset
// helper directly for the cache-busting script tags at the foot of the page.
require_once __DIR__ . '/includes/asset.php';

// The sign-in POST is handled by the page, just below: its result is what the
// form renders, so it cannot move out. The actions file is the page's one place
// for actions and the endpoint its one place for reads, as on every other page.
if (!defined('OCP_INDEX_ACTIONS_RAN')) {
    require __DIR__ . '/actions/index-actions.php';
}
// Process login form
$error = '';

// The page's variables, from its endpoint.
$ocp_endpoint = require __DIR__ . '/api/index-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = trim($_POST['username']);
    $password_input = $_POST['password'];
    $remember_me = isset($_POST['rememberMe']);
    
    if (!empty($username_input) && !empty($password_input)) {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, lastname, firstname, password, accounttype, status FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username_input);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password_input, $user['password'])) {
                // Check if account is active
                if ($user['status'] === 'active') {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['fullname'] = $user['firstname'] . ' ' . $user['lastname'];
                    $_SESSION['account_type'] = $user['accounttype'];
                    
                    // Update last login
                    $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                    $update_stmt->bindParam(':id', $user['id']);
                    $update_stmt->execute();
                    
                    // Set remember me cookie if checked
                    if ($remember_me) {
                        $cookie_value = $user['id'] . ':' . hash('sha256', $user['password']);
                        setcookie('remember_me', $cookie_value, time() + (30 * 24 * 60 * 60), '/');
                    }
                    
                    // Redirect based on account type
                    if ($user['accounttype'] === 'Admin') {
                        header('Location: dashboard.php');
                    } else {
                        header('Location: staff_dashboard.php');
                    }
                    exit();
                } else {
                    $error = 'Your account is not active. Please contact administrator.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
  <title>
    OCP System
  </title>
  <!-- Font Awesome Icons -->
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
  <!-- Inter. This page is standalone, so it does not get these from includes/top_bar.php. -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="assets/css/app.css" rel="stylesheet" />
  <link href="assets/css/app.build.css" rel="stylesheet" />
</head>

<body class="relative flex items-center justify-center" style="min-height:100vh;padding:20px;overflow-x:hidden;">

  <!-- Full-viewport photo backdrop. This replaces the deleted page stylesheet's body::before
       (background-size:cover, centred, no-repeat); the dark overlay replaces its
       filter:brightness(0.4). Inline styles carry the image and the overlay because
       assets/css/app.build.css is a shared, pre-compiled sheet that only contains the
       utilities the migrated pages already use. -->
  <div class="fixed" aria-hidden="true"
       style="inset:0;z-index:0;background-image:url('assets/images/background/back.jpg');background-repeat:no-repeat;background-position:center center;background-size:cover;"></div>
  <div class="fixed" aria-hidden="true"
       style="inset:0;z-index:0;background-color:rgb(15 23 42 / 0.6);"></div>

  <!-- Decorative light spots, kept from the old floating-circles markup. Their float
       animation lived in the deleted page stylesheet, so they are now static. -->
  <div class="fixed rounded-full" aria-hidden="true" style="width:80px;height:80px;top:10%;left:10%;z-index:0;background-color:rgb(255 255 255 / 0.06);pointer-events:none;"></div>
  <div class="fixed rounded-full" aria-hidden="true" style="width:60px;height:60px;top:20%;left:80%;z-index:0;background-color:rgb(255 255 255 / 0.06);pointer-events:none;"></div>
  <div class="fixed rounded-full" aria-hidden="true" style="width:100px;height:100px;top:60%;left:5%;z-index:0;background-color:rgb(255 255 255 / 0.06);pointer-events:none;"></div>
  <div class="fixed rounded-full" aria-hidden="true" style="width:70px;height:70px;top:70%;left:70%;z-index:0;background-color:rgb(255 255 255 / 0.06);pointer-events:none;"></div>
  <div class="fixed rounded-full" aria-hidden="true" style="width:90px;height:90px;top:30%;left:40%;z-index:0;background-color:rgb(255 255 255 / 0.06);pointer-events:none;"></div>

  <div class="relative w-full" style="max-width:26rem;z-index:1;">
    <div class="card" style="background-color:rgb(255 255 255 / 0.95);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-color:rgb(255 255 255 / 0.5);border-radius:1rem;box-shadow:0 24px 48px -12px rgb(15 23 42 / 0.45);">
      <div class="card-header justify-center" style="padding:1.5rem 1.25rem;background-color:rgb(255 255 255 / 0.6);border-bottom-color:rgb(226 232 240 / 0.8);">
        <div class="flex justify-center items-center w-full">
          <img src="assets/images/logo/OCP.png" alt="OCP System" style="max-width:9rem;height:auto;">
        </div>
      </div>
      <div class="card-body" style="padding:2rem 1.75rem;">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger mb-6" role="alert"><span class="flex-1 text-center"><?php echo $error; ?></span></div>
        <?php endif; ?>
        
        <form role="form" method="POST" action="">
          <div class="mb-4">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required placeholder="Enter your username">
          </div>
          <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" required placeholder="Enter your password">
          </div>
          <div class="mb-6">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
              <label class="text-sm text-slate-600" for="rememberMe">Remember me</label>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-lg w-full">Sign in</button>
        </form>
      </div>
    </div>
    
    <div class="text-center text-sm text-slate-300 mt-6">
      &copy; <script>document.write(new Date().getFullYear())</script> OCP Construction. All rights reserved.
    </div>
  </div>

<script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
<script src="<?php echo ocp_asset('assets/js/index.js'); ?>"></script>
</body>

</html>