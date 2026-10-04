<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Redirect to the login page.
//
// Both spellings tried so far were wrong, and each failed in its own way:
//   "../index.php"  resolves one level ABOVE the application, where there is no index.php,
//                   so Apache answered 404 "Not Found" as soon as the user logged out.
//   "index.php"     resolves INSIDE actions/, which has no index.php either.
//
// The login page is the application's own index.php. Its address is worked out from the
// script's own path - /OCP/actions/logout.php minus "/actions/logout.php" is /OCP - so this
// is right whether the app is served from the web root or from a subdirectory. A leading
// slash makes it root-relative, so the browser cannot resolve it against actions/ again.
$ocp_app_path = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/actions/logout.php'))), '/');
header('Location: ' . $ocp_app_path . '/index.php');
exit();
?>