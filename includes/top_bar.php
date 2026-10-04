<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once __DIR__ . '/../config/db_config.php';

// Cache-busting URLs for the plain .js assets (see the file for why this is needed).
require_once __DIR__ . '/asset.php';

// Get current user details
$user_id = $_SESSION['user_id'];
$userStmt = $pdo->prepare("SELECT department, position, accounttype FROM users WHERE id = :id");
$userStmt->bindParam(':id', $user_id);
$userStmt->execute();
$current_user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Get pending PR routing notifications for the current user.
//
// This used to run its own query that selected prr.purchase_request_id, prr.current_step,
// prr.current_status, prr.updated_at and pr.purchase_request_no / pr.created_by. None of those
// columns exist, so every run raised and the catch below turned it into an empty list - which
// is why the bell has never shown a badge. The real columns are pr_id, stage, status,
// created_at, pr_number and requested_by.
//
// The stage list it also carried (a 5-step 'Warehouse Department' / 'Purchasing Department'
// model) matched nothing either: the stages actually written are the ones in
// includes/routing_notifications.php, which is now the single source for this.
require_once __DIR__ . '/routing_notifications.php';

$pending_notifications = [];
$notification_count = 0;

try {
    $pending_notifications = ocp_routing_pending_notifications($pdo, $current_user ?: [], $user_id);
    $notification_count = count($pending_notifications);
} catch (PDOException $e) {
    // Log error but don't show to user
    error_log("Notification error: " . $e->getMessage());
    $pending_notifications = [];
    $notification_count = 0;
}

// Where the browser opens its one long-lived SSE connection to. Derived from this file's own
// location rather than hard-coded, so the app works whether it is served from a vhost root or
// from a subdirectory.
$notification_stream_url = ocp_routing_notification_stream_url();
?>

<nav class="sb-topnav">
    <button type="button" class="icon-btn shrink-0" id="sidebarToggle" aria-label="Toggle navigation" aria-controls="layoutSidenav_nav" aria-expanded="false">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    <a class="navbar-brand" href="dashboard.php">
        <img src="assets/images/logo/OCP.png" alt="" width="28" height="28" />
        <span>OCP Construction</span>
    </a>

    <!-- Pushes everything after it to the right. -->
    <div class="flex-1"></div>

    <!-- Notification Bell -->
    <div class="relative shrink-0">
        <button type="button" class="icon-btn relative" id="notificationDropdown"
                data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="Notifications"
                data-notification-stream="<?php echo htmlspecialchars($notification_stream_url); ?>">
            <i class="fas fa-bell" aria-hidden="true"></i>
            <span id="notificationBadge"
                  class="absolute -top-1 -right-1 inline-flex min-w-[1.15rem] items-center justify-center rounded-full bg-danger-600 px-1 text-[0.625rem] font-semibold leading-[1.15rem] text-white<?php echo $notification_count > 0 ? '' : ' hidden'; ?>">
                <?php echo $notification_count; ?>
                <span class="sr-only">unread notifications</span>
            </span>
        </button>
        <ul class="dropdown-menu app-dropdown dropdown-notifications"
            aria-labelledby="notificationDropdown"
            id="notificationList">
            <?php
            /* The <li> list the bell shows, header included. The same partial is what the
             * SSE stream pushes when the list changes, so a live update and a fresh page
             * load render identically - there is only one copy of this markup. */
            $ocp_items_partial = __DIR__ . '/partials/routing_notifications_items.php';
            if (is_file($ocp_items_partial)) {
                $ocp_with_header = true;
                include $ocp_items_partial;
            }
            ?>
        </ul>
    </div>

    <!-- User Profile Dropdown -->
    <div class="relative shrink-0">
        <button type="button" class="icon-btn" id="navbarDropdown"
                data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
            <i class="fas fa-user" aria-hidden="true"></i>
        </button>
        <ul class="dropdown-menu app-dropdown" aria-labelledby="navbarDropdown">
            <li><a class="app-dropdown-item" href="#!"><i class="fas fa-gear w-4 text-slate-400"></i> Settings</a></li>
            <li><a class="app-dropdown-item" href="#!"><i class="fas fa-clock-rotate-left w-4 text-slate-400"></i> Activity Log</a></li>
            <li class="my-1 border-t border-slate-200"></li>
            <li><a class="app-dropdown-item" href="#!" id="logoutLink"><i class="fas fa-arrow-right-from-bracket w-4 text-slate-400"></i> Logout</a></li>
        </ul>
    </div>
</nav>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

<?php
/* The Logout link below is wired with Swal.fire, so the library has to be present. Eight
 * pages included this bar without loading it, and on those clicking Logout threw instead of
 * logging out. This loads it for any page that has not already. */
require_once __DIR__ . '/sweetalert.php';
?>

<?php
/* ---------------------------------------------------------------------------------------------
 * Make sure DOMContentLoaded handlers always run.
 *
 * Every page script in assets/js is a classic <script src> at the END of the body, and each one
 * wraps its whole body of work in window.addEventListener('DOMContentLoaded', ...). That is
 * normally safe - a blocking script stops the parser, so the event has not fired yet.
 *
 * It stops being safe when an external <script> above them hangs. A pending classic script keeps
 * the document in readyState "loading", DOMContentLoaded never fires, and every page script goes
 * silent: no handlers bound, no callbacks. On gasoline_purchase_order.php that left the CEO
 * signature pad uninitialised - its setup runs from shown.bs.modal, bound inside that block - and
 * the same shape applies to the other pages.
 *
 * This does not replace the normal path: each handler is registered with the browser exactly as
 * it was. A short fallback is added alongside it, which takes over only when the document is
 * already parsed (readyState no longer "loading") and the event still has not been delivered.
 * A handler that has already run is skipped, so it can never run twice.
 *
 * It is registered here, in the top bar, so it is in place before the body scripts register.
 * ------------------------------------------------------------------------------------------- */
?>
<script>
(function () {
    var proto = Document.prototype;
    if (proto.__ocpDclPatched) { return; }
    var nativeAdd = proto.addEventListener;
    var state = { handlers: [], done: [], timer: null, fired: false };

    proto.addEventListener = function (type, listener, options) {
        if (type !== 'DOMContentLoaded' || typeof listener !== 'function') {
            return nativeAdd.call(this, type, listener, options);
        }
        state.handlers.push(listener);
        // The real event still drives this, unchanged.
        nativeAdd.call(this, type, function (event) {
            state.fired = true;
            state.handlers.forEach(function (fn, i) {
                if (state.done[i]) { return; }
                state.done[i] = true;
                fn.call(document, event);
            });
        }, options);
        // And a fallback only for the case where the event never arrives.
        if (!state.timer) {
            state.timer = window.setTimeout(function () {
                if (state.fired || document.readyState === 'loading') { return; }
                state.handlers.forEach(function (fn, i) {
                    if (state.done[i]) { return; }
                    state.done[i] = true;
                    try { fn.call(document, new Event('DOMContentLoaded')); }
                    catch (err) { if (window.console && console.error) { console.error(err); } }
                });
            }, 1500);
        }
        return undefined;
    };

    proto.__ocpDclPatched = true;
}());
</script>
<script src="<?php echo ocp_asset('assets/js/ui.js'); ?>"></script>
<script src="<?php echo ocp_asset('assets/js/includes/top_bar.js'); ?>"></script>