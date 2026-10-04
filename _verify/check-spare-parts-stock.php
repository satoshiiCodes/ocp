<?php
// Proves editing or deleting a parts movement moves the stock figure the "Current Parts
// Inventory Summary" shows.
//
//   php _verify/check-spare-parts-stock.php <user_id> <port> <sid>
//
// A movement is created for real (an uncommitted row is invisible to the HTTP request, which
// uses its own connection), driven through the page's own edit and delete handlers, and then
// removed. The part's starting stock is read first and asserted back at the end, so the test
// proves both directions and leaves nothing behind.
require_once __DIR__ . '/../config/db_config.php';

[$userId, $port, $sid] = array_pad(array_slice($argv, 1), 3, null);
$base = "http://127.0.0.1:$port/spare_parts_inventory.php";
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

function island($body, $slug)
{
    if (!preg_match('/window\.OCP_PAGE_' . strtoupper($slug) . '\s*=\s*(\{.*?\});/s', $body, $m)) return [];
    return json_decode($m[1], true) ?: [];
}

$inventoryQty = function ($partId) use ($pdo) {
    $s = $pdo->prepare("SELECT quantity FROM spare_parts_inventory WHERE part_id = ?");
    $s->execute([$partId]);
    $v = $s->fetchColumn();
    return $v === false ? null : (float) $v;
};

$problems = 0;
$check = function ($label, $expected, $actual) use (&$problems) {
    $ok = abs((float) $expected - (float) $actual) < 0.001;
    echo sprintf("  %-58s expected %8s  actual %8s  %s\n", $label, $expected, $actual, $ok ? 'ok' : 'MISMATCH');
    if (!$ok) $problems++;
};

// a part that has an inventory row and a batch, so both places can be watched
$part = $pdo->query("SELECT spi.part_id, spi.quantity, sp.part_name
                     FROM spare_parts_inventory spi JOIN spare_parts sp ON sp.id = spi.part_id
                     WHERE EXISTS (SELECT 1 FROM spare_parts_batches b WHERE b.part_id = spi.part_id)
                     ORDER BY spi.part_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$part) { echo "  no part with stock to test with\n"; exit(1); }
$partId = (int) $part['part_id'];
$startQty = (float) $part['quantity'];
$batchQtyBefore = (float) $pdo->query("SELECT SUM(quantity) FROM spare_parts_batches WHERE part_id = $partId")->fetchColumn();
echo "  part {$partId} ({$part['part_name']}): stock {$startQty}, batches {$batchQtyBefore}\n";

// Snapshot every batch of the part before touching anything. Restoring only the one batch
// this test thinks it used is not enough: an earlier version of this script left part 2's
// batches 4 higher than its stock, because the batch a movement pointed at was not the batch
// the test had picked to restore. A snapshot cannot be wrong about which rows changed.
$batchSnapshot = $pdo->query("SELECT id, quantity FROM spare_parts_batches WHERE part_id = $partId")
    ->fetchAll(PDO::FETCH_ASSOC);
$restoreBatches = function () use ($pdo, $partId, $batchSnapshot) {
    $upd = $pdo->prepare("UPDATE spare_parts_batches SET quantity = ? WHERE id = ?");
    foreach ($batchSnapshot as $row) {
        $upd->execute([$row['quantity'], (int) $row['id']]);
    }
    $unknown = $pdo->query("SELECT COUNT(*) FROM spare_parts_batches WHERE part_id = $partId")->fetchColumn();
    return count($batchSnapshot) === (int) $unknown;
};

$batchId = (int) $pdo->query("SELECT id FROM spare_parts_batches WHERE part_id = $partId ORDER BY date_received LIMIT 1")->fetchColumn();

// an "in" movement of 5, linked to that batch, so the reversal has a batch to put back
$ins = $pdo->prepare("INSERT INTO spare_parts_movements (part_id, quantity, price_per_unit, movement_type, movement_date, purpose, batch_id)
                      VALUES (?, 5, 10, 'in', CURDATE(), 'stock check', ?)");
$ins->execute([$partId, $batchId]);
$movementId = (int) $pdo->lastInsertId();
// and put the stock where that movement says it is, so the test starts from a consistent state
$pdo->exec("UPDATE spare_parts_inventory SET quantity = quantity + 5 WHERE part_id = $partId");
$pdo->exec("UPDATE spare_parts_batches SET quantity = quantity + 5 WHERE id = $batchId");
echo "  created movement $movementId (in, 5) and added 5 to stock, now " . $inventoryQty($partId) . "\n";

try {
    // ------------------------------------------------------------ edit: 5 -> 9
    [$status, $body] = post($base, $cookie, [
        'edit_part_movement' => '1',
        'movement_id' => (string) $movementId,
        'movement_date' => date('Y-m-d'),
        'quantity' => '9',
        'price_per_unit' => '10',
        'technician' => '',
        'purpose' => 'stock check edited',
    ]);
    $msg = island($body, 'spare_parts_inventory');
    $afterEdit = $inventoryQty($partId);
    $batchAfterEdit = (float) $pdo->query("SELECT SUM(quantity) FROM spare_parts_batches WHERE part_id = $partId")->fetchColumn();
    echo "  edit 5 -> 9: HTTP $status, message " . json_encode($msg['swalData2'] ?? '') . "\n";
    $check('stock after editing 5 to 9 (was ' . ($startQty + 5) . ')', $startQty + 9, $afterEdit);
    $check('batches after editing 5 to 9', $batchQtyBefore + 9, $batchAfterEdit);
    $rowQty = $pdo->query("SELECT quantity FROM spare_parts_movements WHERE id = $movementId")->fetchColumn();
    $check('the movement row now holds', 9, $rowQty);

    // ------------------------------------------------------------ delete it
    [$dStatus, $dBody] = post($base, $cookie, [
        'delete_part_movement' => '1',
        'movement_id' => (string) $movementId,
    ]);
    $dMsg = island($dBody, 'spare_parts_inventory');
    $afterDelete = $inventoryQty($partId);
    $batchAfterDelete = (float) $pdo->query("SELECT SUM(quantity) FROM spare_parts_batches WHERE part_id = $partId")->fetchColumn();
    $rows = (int) $pdo->query("SELECT COUNT(*) FROM spare_parts_movements WHERE id = $movementId")->fetchColumn();
    echo "  delete: HTTP $dStatus, message " . json_encode($dMsg['swalData2'] ?? '') . "\n";
    $check('stock after deleting the movement', $startQty, $afterDelete);
    $check('batches after deleting the movement', $batchQtyBefore, $batchAfterDelete);
    $check('movement rows left', 0, $rows);

    // ------------------------------------------------------------ an "out" movement
    // deleting an outgoing movement must give the stock back
    $out = $pdo->prepare("INSERT INTO spare_parts_movements (part_id, quantity, price_per_unit, movement_type, movement_date, purpose, batch_id)
                          VALUES (?, 4, 10, 'out', CURDATE(), 'stock check out', ?)");
    $out->execute([$partId, $batchId]);
    $outId = (int) $pdo->lastInsertId();
    $pdo->exec("UPDATE spare_parts_inventory SET quantity = GREATEST(quantity - 4, 0) WHERE part_id = $partId");
    $pdo->exec("UPDATE spare_parts_batches SET quantity = GREATEST(quantity - 4, 0) WHERE id = $batchId");
    $beforeOut = $inventoryQty($partId);
    post($base, $cookie, ['delete_part_movement' => '1', 'movement_id' => (string) $outId]);
    $afterOut = $inventoryQty($partId);
    echo "  deleting an 'out' movement of 4: stock $beforeOut -> $afterOut\n";
    $check('stock after deleting an outgoing movement', $beforeOut + 4, $afterOut);
} finally {
    // ------------------------------------------------------------ cleanup
    $pdo->exec("DELETE FROM spare_parts_movements WHERE purpose LIKE 'stock check%'");
    $pdo->exec("UPDATE spare_parts_inventory SET quantity = $startQty WHERE part_id = $partId");
    $sameBatches = $restoreBatches();
    $left = (int) $pdo->query("SELECT COUNT(*) FROM spare_parts_movements WHERE purpose LIKE 'stock check%'")->fetchColumn();
    $finalQty = $inventoryQty($partId);
    $finalBatches = (float) $pdo->query("SELECT SUM(quantity) FROM spare_parts_batches WHERE part_id = $partId")->fetchColumn();
    echo "  cleanup: probe movements left $left, stock $finalQty (was $startQty), batches $finalBatches (was $batchQtyBefore)\n";
    if ($left !== 0) $problems++;
    if (!$sameBatches) { echo "    X the part has a batch this run did not know about\n"; $problems++; }
    if (abs($finalQty - $startQty) > 0.001) { echo "    X stock not restored\n"; $problems++; }
    if (abs($finalBatches - $batchQtyBefore) > 0.001) { echo "    X batches not restored\n"; $problems++; }
}

echo "  problems: $problems\n";
exit($problems ? 1 : 0);
