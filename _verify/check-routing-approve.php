<?php
// Walks a purchase request through every approve step of the routing flow, as the user each
// step authorizes, against a live server.
//
//   php _verify/check-routing-approve.php <port>
//
// The stage is read back from the database after each post, so a step that silently does
// nothing is reported rather than passing.
//
// The request is created as a real row rather than inside a transaction: the posts go over
// HTTP, which uses its own database connection, and an uncommitted row is invisible to it.
// Everything the walk creates is therefore listed and deleted at the end, and the row counts
// are compared before and after so a leak cannot pass unnoticed.
require_once __DIR__ . '/../config/db_config.php';

$port = $argv[1] ?? '8640';
$base = "http://127.0.0.1:$port/pr_view_routing.php";
$problems = 0;

function sessionFor(PDO $pdo, array $where)
{
    $sql = "SELECT id FROM users WHERE " . implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where))) . " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($where));
    $id = $stmt->fetchColumn();
    if (!$id) return null;
    $sid = trim((string) shell_exec('php ' . escapeshellarg(__DIR__ . '/make-session.php') . ' ' . (int) $id));
    return $sid ?: null;
}

function post($url, $sid, array $fields)
{
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: PHPSESSID=$sid\r\n",
        'content' => http_build_query($fields),
        'ignore_errors' => true,
        'follow_location' => 0,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int) $m[1];
    }
    return [$status, (string) $body];
}

$sessions = [];
foreach ([
    'warehouse' => ['department' => 'Warehouse', 'accounttype' => 'Admin'],
    'purchaser' => ['department' => 'Admin', 'position' => 'Purchaser', 'accounttype' => 'Admin'],
    'accounting' => ['department' => 'Admin', 'position' => 'Accounting', 'accounttype' => 'Admin'],
    'ceo' => ['department' => 'Admin', 'position' => 'CEO', 'accounttype' => 'Admin'],
] as $role => $where) {
    $sessions[$role] = sessionFor($pdo, $where);
}
echo "  sessions: " . json_encode(array_map(fn($s) => $s ? 'ok' : 'MISSING', $sessions)) . "\n";
foreach ($sessions as $role => $sid) {
    if (!$sid) { echo "  no user found for the $role role - cannot test\n"; exit(1); }
}

$counts = function () use ($pdo) {
    return [
        'pr' => (int) $pdo->query("SELECT COUNT(*) FROM purchase_requests")->fetchColumn(),
        'po' => (int) $pdo->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn(),
        'routing' => (int) $pdo->query("SELECT COUNT(*) FROM pr_routing")->fetchColumn(),
        'history' => (int) $pdo->query("SELECT COUNT(*) FROM pr_routing_history")->fetchColumn(),
    ];
};
$before = $counts();

$supplierId = (int) $pdo->query("SELECT id FROM suppliers LIMIT 1")->fetchColumn();
$owner = (int) $pdo->query("SELECT id FROM users WHERE accounttype = 'Admin' ORDER BY id LIMIT 1")->fetchColumn();
$prId = 0;
$poId = 0;

try {
    $number = 'VERIFY-ROUTE-' . time();
    $pdo->prepare("INSERT INTO purchase_requests (pr_number, requested_by, request_type, document_type, status, request_date)
                   VALUES (?, ?, 'project', 'pr_po', 'pending', CURDATE())")
        ->execute([$number, $owner]);
    $prId = (int) $pdo->lastInsertId();
    echo "  created $number (id $prId), document_type pr_po\n";

    $requestor = sessionFor($pdo, ['id' => $owner]);
    $stageOf = function () use ($pdo, $prId) {
        return $pdo->query("SELECT stage FROM pr_routing WHERE pr_id = $prId ORDER BY id DESC LIMIT 1")->fetchColumn();
    };
    $step = function ($label, $sid, $action, $expected) use ($base, $prId, $stageOf, &$problems) {
        [$status, ] = post("$base?id=$prId", $sid, ['action' => $action, 'remarks' => 'probe']);
        $stage = $stageOf();
        $ok = $stage === $expected;
        printf("  %-28s HTTP %d -> stage %-22s %s\n", $label, $status, $stage === false ? '(none)' : $stage, $ok ? 'ok' : 'FAIL (expected ' . $expected . ')');
        if (!$ok) $problems++;
        return $stage;
    };

    $step('forward_to_warehouse', $requestor, 'forward_to_warehouse', 'warehouse');
    $step('approve_warehouse', $sessions['warehouse'], 'approve_warehouse', 'purchasing');

    // pr_po requires a purchase order before purchasing can approve it - confirm it refuses.
    // The answer is a redirect, and the message travels in the session to the page it lands
    // on, so the reason is read from there rather than from the 302's empty body.
    [$status, $body] = post("$base?id=$prId", $sessions['purchaser'], ['action' => 'approve_purchasing', 'remarks' => 'probe']);
    $stage = $stageOf();
    // follow it, as a browser would, to see the message
    $followCtx = stream_context_create(['http' => ['header' => "Cookie: PHPSESSID={$sessions['purchaser']}\r\n", 'ignore_errors' => true]]);
    $landed = (string) @file_get_contents("$base?id=$prId", false, $followCtx);
    $refused = (bool) preg_match('/Purchase Order is required|Failed to process action/i', $landed);
    printf("  %-28s HTTP %d -> stage %-22s %s\n", 'approve_purchasing (no PO)', $status, $stage,
        ($refused && $stage === 'purchasing') ? 'ok (correctly refused)' : 'FAIL' . ($refused ? ' (moved anyway)' : ' (no refusal message)'));
    if (!($refused && $stage === 'purchasing')) $problems++;

    // the purchase order it asks for
    $pdo->prepare("INSERT INTO purchase_orders (pr_id, po_number, supplier_id, requested_by, status, total_amount, po_date)
                   VALUES (?, ?, ?, ?, 'pending', 0, CURDATE())")
        ->execute([$prId, 'VERIFY-PO-' . time(), $supplierId, $owner]);
    $poId = (int) $pdo->lastInsertId();

    $step('approve_purchasing', $sessions['purchaser'], 'approve_purchasing', 'accounting');
    $step('approve_accounting', $sessions['accounting'], 'approve_accounting', 'approver');
    $step('approve_approver', $sessions['ceo'], 'approve_approver', 'purchasing_final');
    $step('approve_purchasing_final', $sessions['purchaser'], 'approve_purchasing_final', 'warehouse_receiving');
} catch (Throwable $e) {
    echo "  FAILED: " . $e->getMessage() . "\n";
    $problems++;
} finally {
    // ---- cleanup: everything the walk made, in dependency order
    if ($prId) {
        $pdo->exec("DELETE FROM pr_routing_history WHERE pr_id = $prId");
        $pdo->exec("DELETE FROM pr_routing WHERE pr_id = $prId");
        if ($poId) $pdo->exec("DELETE FROM purchase_orders WHERE id = $poId");
        $pdo->exec("DELETE FROM purchase_requests WHERE id = $prId");
    }
    $after = $counts();
    foreach ($before as $k => $v) {
        if ($after[$k] !== $v) { echo "  X $k was not restored: $v -> {$after[$k]}\n"; $problems++; }
    }
    echo "  cleanup: " . ($after === $before ? 'all four tables back to their original counts' : 'LEFTOVERS') . "\n";
}

echo "  problems: $problems\n";
exit($problems ? 1 : 0);
