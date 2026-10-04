<?php
// Creates a purchase request, moves it to the purchasing stage, submits "Create Purchase
// Order" the way the page's form does, and checks that po_items actually receives the rows.
//
//   php _verify/check-create-po-items.php <port>
//
// This is the regression that mattered: the handler looks each chosen item up in
// $items_for_po, which was built in the endpoint the page loads AFTER the actions file. On the
// POST it did not exist, so the lookup found nothing and the order was created with no items.
//
// Everything created is listed and deleted at the end, and the row counts are compared before
// and after.
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
        'content' => http_build_query($fields), 'ignore_errors' => true, 'follow_location' => 0,
    ]]);
    $body = (string) @file_get_contents($url, false, $ctx);
    return $body;
}

$TABLES = ['purchase_requests', 'purchase_orders', 'po_items', 'pr_items', 'pr_routing', 'pr_routing_history'];
$counts = function () use ($pdo, $TABLES) {
    $out = [];
    foreach ($TABLES as $t) $out[$t] = (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    return $out;
};

$warehouse = sessionFor($pdo, ['department' => 'Warehouse', 'accounttype' => 'Admin']);
$purchaser = sessionFor($pdo, ['department' => 'Admin', 'position' => 'Purchaser', 'accounttype' => 'Admin']);
$owner = (int) $pdo->query("SELECT id FROM users WHERE accounttype = 'Admin' ORDER BY id LIMIT 1")->fetchColumn();
$requestor = sessionFor($pdo, ['id' => $owner]);
$supplierId = (int) $pdo->query("SELECT id FROM suppliers LIMIT 1")->fetchColumn();
$warehouseId = (int) $pdo->query("SELECT id FROM warehouses LIMIT 1")->fetchColumn();
$itemId = (int) $pdo->query("SELECT id FROM item_names LIMIT 1")->fetchColumn();

if (!$itemId) { echo "  no item_names row to build a request item from - cannot test\n"; exit(1); }
foreach (['warehouse' => $warehouse, 'purchaser' => $purchaser, 'requestor' => $requestor] as $role => $sid) {
    if (!$sid) { echo "  no user for the $role role - cannot test\n"; exit(1); }
}

$before = $counts();
$prId = 0;
$poIds = [];

try {
    // a project PR that needs a purchase order, with one item
    $pdo->prepare("INSERT INTO purchase_requests (pr_number, requested_by, request_type, document_type, status, request_date)
                   VALUES (?, ?, 'project', 'pr_po', 'pending', CURDATE())")
        ->execute(['VERIFY-POITEMS-' . time(), $owner]);
    $prId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO pr_items (pr_id, item_id, warehouse_id, supplier_id, quantity, unit_cost, delivered_quantity)
                   VALUES (?, ?, ?, ?, 10, 25.00, 0)")
        ->execute([$prId, $itemId, $warehouseId, $supplierId]);
    $prItemId = (int) $pdo->lastInsertId();
    echo "  created request $prId with pr_item $prItemId (quantity 10 @ 25.00)\n";

    // forward to the warehouse, then approve, so the request is on the purchasing stage
    post("$base?id=$prId", $requestor, ['action' => 'forward_to_warehouse', 'remarks' => 'probe']);
    post("$base?id=$prId", $warehouse, ['action' => 'approve_warehouse', 'remarks' => 'probe']);
    $stage = $pdo->query("SELECT stage FROM pr_routing WHERE pr_id = $prId ORDER BY created_at DESC, id DESC LIMIT 1")->fetchColumn();
    echo "  stage: $stage\n";
    if ($stage !== 'purchasing') { echo "  X could not reach the purchasing stage\n"; $problems++; }

    // the page must offer the item for ordering
    $pageCtx = stream_context_create(['http' => ['header' => "Cookie: PHPSESSID=$purchaser\r\n", 'ignore_errors' => true]]);
    $html = (string) @file_get_contents("$base?id=$prId", false, $pageCtx);
    $offered = preg_match('/name="selected_items\[\]" value="' . $prItemId . '"/', $html) === 1;
    echo "  the page offers pr_item $prItemId for ordering: " . ($offered ? 'yes' : 'NO') . "\n";
    if (!$offered) $problems++;

    // submit exactly what the form posts
    post("$base?id=$prId", $purchaser, [
        'action' => 'create_purchase_order',
        'selected_items' => [$prItemId],
        "supplier_id_$prItemId" => $supplierId,
        "quantity_$prItemId" => '8',
        "unit_cost_$prItemId" => '30.50',
        'expected_delivery' => date('Y-m-d', strtotime('+7 days')),
        'po_remarks' => 'probe',
    ]);

    $po = $pdo->query("SELECT id, po_number, total_amount FROM purchase_orders WHERE pr_id = $prId")->fetch(PDO::FETCH_ASSOC);
    if (!$po) {
        echo "  X no purchase order was created\n";
        $problems++;
    } else {
        $poIds[] = (int) $po['id'];
        echo "  purchase order {$po['po_number']} (id {$po['id']}) created\n";
        $rows = $pdo->query("SELECT id, pr_item_id, item_id, supplier_id, quantity, unit_cost, total_cost FROM po_items WHERE po_id = {$po['id']}")->fetchAll(PDO::FETCH_ASSOC);
        echo "  po_items rows: " . count($rows) . "\n";
        foreach ($rows as $r) {
            echo "    pr_item_id={$r['pr_item_id']} item_id={$r['item_id']} supplier={$r['supplier_id']} "
               . "qty={$r['quantity']} unit={$r['unit_cost']} total={$r['total_cost']}\n";
        }
        if (count($rows) !== 1) {
            echo "  X expected exactly 1 po_items row for the 1 selected item\n";
            $problems++;
        } else {
            $r = $rows[0];
            if ((int) $r['pr_item_id'] !== $prItemId) { echo "  X pr_item_id is wrong\n"; $problems++; }
            if ((int) $r['item_id'] !== $itemId) { echo "  X item_id is wrong\n"; $problems++; }
            if (abs((float) $r['quantity'] - 8) > 0.001) { echo "  X quantity is wrong\n"; $problems++; }
            if (abs((float) $r['unit_cost'] - 30.50) > 0.001) { echo "  X unit_cost is wrong\n"; $problems++; }
            if (abs((float) $r['total_cost'] - 244.00) > 0.01) { echo "  X total_cost is wrong ({$r['total_cost']})\n"; $problems++; }
            if (abs((float) $po['total_amount'] - 244.00) > 0.01) { echo "  X the PO total is wrong ({$po['total_amount']})\n"; $problems++; }
        }
        // the pr_items row must have been costed too
        $prItem = $pdo->query("SELECT unit_cost, total_cost FROM pr_items WHERE id = $prItemId")->fetch(PDO::FETCH_ASSOC);
        echo "  pr_item costed: unit={$prItem['unit_cost']} total={$prItem['total_cost']}\n";
        if (abs((float) $prItem['total_cost'] - 244.00) > 0.01) { echo "  X the pr_items row was not costed\n"; $problems++; }
    }
} catch (Throwable $e) {
    echo "  FAILED: " . $e->getMessage() . "\n";
    $problems++;
} finally {
    foreach ($poIds as $id) $pdo->exec("DELETE FROM po_items WHERE po_id = $id");
    if ($poIds) $pdo->exec("DELETE FROM purchase_orders WHERE id IN (" . implode(',', $poIds) . ")");
    if ($prId) {
        $pdo->exec("DELETE FROM po_items WHERE pr_item_id IN (SELECT id FROM pr_items WHERE pr_id = $prId)");
        $pdo->exec("DELETE FROM pr_items WHERE pr_id = $prId");
        $pdo->exec("DELETE FROM pr_routing_history WHERE pr_id = $prId");
        $pdo->exec("DELETE FROM pr_routing WHERE pr_id = $prId");
        $pdo->exec("DELETE FROM purchase_requests WHERE id = $prId");
    }
    $after = $counts();
    $bad = [];
    foreach ($before as $t => $n) if ($after[$t] !== $n) $bad[] = "$t $n->{$after[$t]}";
    echo "  cleanup: " . ($bad ? 'LEFTOVERS ' . implode(', ', $bad) : 'all six tables back to their original counts') . "\n";
    if ($bad) $problems++;
}

echo "  problems: $problems\n";
exit($problems ? 1 : 0);
