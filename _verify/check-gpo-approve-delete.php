<?php
// Proves the approve and delete forms on gasoline_purchase_order.php do what they should.
//
//   php _verify/check-gpo-approve-delete.php <user_id> <supplier_id> <port> <sid>
//
// A purchase order is created for real (an uncommitted row is invisible to the HTTP request,
// which uses its own connection), put through the page's own POST handlers, and then removed
// completely - the order, its items and any movements the approval wrote. Every step is
// reported with the ids it touched so the cleanup can be checked.
require_once __DIR__ . '/../config/db_config.php';

[$userId, $supplierId, $port, $sid] = array_pad(array_slice($argv, 1), 4, null);
$base = "http://127.0.0.1:$port/gasoline_purchase_order.php";
$cookie = "PHPSESSID=$sid";

function post($base, $cookie, array $fields)
{
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
        'content' => http_build_query($fields),
        'ignore_errors' => true,
        'follow_location' => 0,
    ]]);
    $body = @file_get_contents($base, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int) $m[1];
    }
    return [$status, (string) $body];
}

function islandMessages($body)
{
    if (!preg_match('/window\.OCP_PAGE_GASOLINE_PURCHASE_ORDER\s*=\s*(\{.*?\});/s', $body, $m)) return [];
    $data = json_decode($m[1], true) ?: [];
    $out = [];
    foreach ($data as $k => $v) {
        if (preg_match('/swal/i', $k) && $v !== '' && $v !== false) $out[$k] = is_string($v) ? $v : json_encode($v);
    }
    return $out;
}

$number = 'VERIFY-' . time();
$ins = $pdo->prepare("INSERT INTO gasoline_purchase_orders (po_number, supplier_id, po_date, status, prepared_by, total_amount)
                      VALUES (?, ?, CURDATE(), 'pending', ?, 1200)");
$ins->execute([$number, $supplierId, $userId]);
$poId = (int) $pdo->lastInsertId();

$item = $pdo->prepare("INSERT INTO gasoline_po_items (po_id, gasoline_type, supplier_id, quantity_liters, price_per_liter, purpose, date_issued)
                       VALUES (?, 'Premium', ?, 15, 80, 'probe', CURDATE())");
$item->execute([$poId, $supplierId]);

echo "  created order $poId ($number) with 1 item\n";
$problems = 0;

try {
    // ---------------------------------------------------------------- approve
    [$status, $body] = post($base, $cookie, [
        'action' => 'approve_po',
        'po_id' => (string) $poId,
        'signature_data' => 'data:image/png;base64,VERIFYPROBE',
        'signature_type' => 'draw',
    ]);
    $isPage = (bool) preg_match('/<!DOCTYPE html/i', $body);
    $msgs = islandMessages($body);
    $now = $pdo->query("SELECT status FROM gasoline_purchase_orders WHERE id = $poId")->fetchColumn();
    $movements = (int) $pdo->query("SELECT COUNT(*) FROM gasoline_movements WHERE po_id = $poId")->fetchColumn();
    echo "  approve: $status, full page=" . var_export($isPage, true) . ", status=$now, movements=$movements\n";
    echo "    message: " . json_encode($msgs) . "\n";
    if (!$isPage) { echo "    X the response is not the page - the browser would show a fragment\n"; $problems++; }
    if ($now !== 'approved') { echo "    X the order was not approved\n"; $problems++; }
    if (!preg_match('/approved successfully/i', $msgs['swalData2'] ?? '')) { echo "    X no success message in the island\n"; $problems++; }

    // ---------------------------------------------------------------- delete
    // the order is approved now, and delete only accepts pending/cancelled/delivered, so it
    // is put back to pending first - which is also what the page offers for a pending order
    $pdo->exec("UPDATE gasoline_purchase_orders SET status = 'pending', approval_signature = NULL, approved_by = NULL WHERE id = $poId");
    [$dStatus, $dBody] = post($base, $cookie, ['action' => 'delete_po', 'po_id' => (string) $poId, 'delete_reason' => 'probe']);
    $stillThere = (int) $pdo->query("SELECT COUNT(*) FROM gasoline_purchase_orders WHERE id = $poId")->fetchColumn();
    $dMsgs = islandMessages($dBody);
    echo "  delete: $dStatus, full page=" . var_export((bool) preg_match('/<!DOCTYPE html/i', $dBody), true) . ", rows left=$stillThere\n";
    echo "    message: " . json_encode($dMsgs) . "\n";
    if ($stillThere !== 0) { echo "    X the order was not deleted\n"; $problems++; }
    if (!preg_match('/deleted/i', $dMsgs['swalData2'] ?? '')) { echo "    X no deletion message in the island\n"; $problems++; }
} finally {
    // ---------------------------------------------------------------- cleanup
    $pdo->exec("DELETE FROM gasoline_movements WHERE po_id = $poId");
    $pdo->exec("DELETE FROM gasoline_po_items WHERE po_id = $poId");
    $pdo->exec("DELETE FROM gasoline_purchase_orders WHERE id = $poId");
    $left = (int) $pdo->query("SELECT COUNT(*) FROM gasoline_purchase_orders WHERE po_number LIKE 'VERIFY-%'")->fetchColumn();
    echo "  cleanup: VERIFY- orders left = $left\n";
    if ($left !== 0) $problems++;
}

echo "  problems: $problems\n";
exit($problems ? 1 : 0);
