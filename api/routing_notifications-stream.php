<?php
/**
 * api/routing_notifications-stream.php
 *
 * Server-Sent Events stream: the bell in includes/top_bar.php subscribes to this and is told
 * the moment a request lands on a stage the signed-in user can act on. There is no polling in
 * the browser and no page refresh - the browser opens ONE connection and the server writes to
 * it only when something actually changed.
 *
 * Why SSE rather than WebSockets: it is plain HTTP, so it needs nothing beyond the PHP and
 * Apache already running here - no daemon, no port, no composer package. It is one-directional,
 * which is all a notification needs.
 *
 * Two deliberate choices:
 *
 *   - The session is closed as soon as the user is identified. PHP holds an exclusive lock on a
 *     session file for the whole request, so leaving it open would stall every other request the
 *     same signed-in user makes (their next click would block until this stream ended).
 *   - The stream ends itself after ~25 seconds and the browser reconnects. That keeps each
 *     connection well inside Apache's default 60-second Timeout and stops idle streams from
 *     occupying worker threads indefinitely. Between polls it sleeps, so a connected bell is a
 *     couple of indexed queries every few seconds, not a spin loop.
 */

// A broken or slow stream must still be a stream: no notices, no PHP warnings, no compression
// and no buffering can be allowed to reach the client mid-frame.
@ini_set('display_errors', '0');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
@ini_set('max_execution_time', '0');
error_reporting(0);
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* config/db_config.php opens with a blank line before its <?php, so including it emits a stray
 * newline. Sent on its own that trips PHP's "headers already sent", and every header below -
 * including the Content-Type that makes the browser treat this as an event stream - is silently
 * dropped. Buffering the include and throwing the buffer away keeps the wire clean; the headers
 * go out before it, so nothing has been flushed when it runs. */
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // belt and braces if this ever runs behind a proxy

ob_implicit_flush(false);
ob_start();
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/routing_notifications.php';
ob_end_clean();
ob_implicit_flush(true);

// Identify the user, then let go of the session lock immediately.
$user_id = (int) ($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    session_write_close();
    // A comment, not a status code: EventSource cannot read a body on a failure and will simply
    // reconnect, which is what we want once the user has signed in again.
    echo ": unauthenticated\n\n";
    exit;
}

$userStmt = $pdo->prepare("SELECT department, position, accounttype FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$current_user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
session_write_close();

function ocp_sse_send($event, $data) {
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

function ocp_sse_comment($text) {
    echo ': ' . $text . "\n\n";
    flush();
}

// Reconnect hint: if the connection drops, wait 3s rather than hammering.
echo "retry: 3000\n\n";
flush();

$lastSignature = null;
$streamDeadline = time() + 25;
$pollInterval = 4;

// Render the list to the same markup the page ships, so a live update and a fresh load are
// indistinguishable. The partial reads $pending_notifications; the header goes with it because
// top_bar.php ships the whole list as one replaceable block.
function ocp_render_notification_items($pdo, $user, $user_id) {
    $notifications = ocp_routing_pending_notifications($pdo, $user, $user_id);
    $pending_notifications = $notifications;
    $ocp_with_header = true;
    ob_start();
    include __DIR__ . '/../includes/partials/routing_notifications_items.php';
    return [$notifications, ob_get_clean()];
}

// Send the current state immediately, so the bell is correct the instant the stream opens.
list($notifications, $html) = ocp_render_notification_items($pdo, $current_user, $user_id);
$lastSignature = ocp_routing_notifications_signature($notifications);
ocp_sse_send('notifications', [
    'count' => count($notifications),
    'signature' => $lastSignature,
    'html' => $html,
]);

// Then watch for changes until the deadline. Only a real change writes a frame.
while (time() < $streamDeadline) {
    // If the browser navigated away or closed the tab, this is how we find out.
    if (connection_aborted()) {
        break;
    }

    sleep($pollInterval);

    if (connection_aborted()) {
        break;
    }

    try {
        list($notifications, $html) = ocp_render_notification_items($pdo, $current_user, $user_id);
    } catch (Exception $e) {
        // A transient database problem must not kill the stream - the browser would show the
        // connection as failed. Skip this round and try again.
        continue;
    }

    $signature = ocp_routing_notifications_signature($notifications);
    if ($signature !== $lastSignature) {
        $lastSignature = $signature;
        ocp_sse_send('notifications', [
            'count' => count($notifications),
            'signature' => $signature,
            'html' => $html,
        ]);
    } else {
        // A heartbeat comment keeps proxies from deciding the connection is dead, and gives
        // connection_aborted() a reason to be checked on a quiet stream.
        ocp_sse_comment('keep-alive');
    }
}

// Ask the browser to come straight back; the work continues in a new connection.
ocp_sse_comment('reconnect');
