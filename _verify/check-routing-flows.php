<?php
// Reports, per document_type, exactly where a request could not proceed.
//
//   php _verify/check-routing-flows.php
//
// For each flow the stages are read from pr_view_routing.php, the current stage from
// pr_routing, and the branch the endpoint documents are looked at for the stock decisions.
// A request whose stage is not in its own flow, or whose flow stalls because a document the
// next step demands cannot exist, is reported with the reason.
require_once __DIR__ . '/../config/db_config.php';

$page = file_get_contents(__DIR__ . '/../pr_view_routing.php');
$flows = [];
foreach (preg_match_all("/if \(\\\$document_type === '(\w+)'\) \{[\s\S]*?\\\$stages = \[([\s\S]*?)\];/", $page, $m, PREG_SET_ORDER) as $set) {
    preg_match_all("/'(\w+)' => \['label'/", $set[2], $stages);
    $flows[$set[1]] = $stages[1];
}
if (preg_match("/\} else \{\s*\/\/ Supplier request\s*\\\$stages = \[([\s\S]*?)\];/", $page, $m)) {
    preg_match_all("/'(\w+)' => \['label'/", $m[1], $s);
    $flows['supplier'] = $s[1];
}

echo "  flows declared by the page:\n";
foreach ($flows as $name => $list) {
    echo "    " . str_pad($name, 10) . implode(' -> ', $list) . "\n";
}

// the stage each request is on, against its flow
$rows = $pdo->query("
    SELECT pr.id, pr.pr_number, pr.request_type, pr.document_type, pr.status,
           (SELECT stage FROM pr_routing r WHERE r.pr_id = pr.id ORDER BY r.created_at DESC, r.id DESC LIMIT 1) AS stage,
           (SELECT COUNT(*) FROM purchase_orders po WHERE po.pr_id = pr.id) AS pos,
           (SELECT COUNT(*) FROM withdrawal_slips ws WHERE ws.pr_id = pr.id) AS wss
    FROM purchase_requests pr
    ORDER BY pr.id
")->fetchAll(PDO::FETCH_ASSOC);

$problems = [];
$byType = [];
foreach ($rows as $r) {
    $docType = $r['document_type'] ?: 'pr_po';
    $flowName = $r['request_type'] === 'project' ? $docType : 'supplier';
    $flow = $flows[$flowName] ?? [];
    $stage = $r['stage'] ?: 'requestor';
    $byType[$flowName][] = $stage;

    if ($flow && !in_array($stage, $flow, true)) {
        $problems[] = "{$r['pr_number']} ({$flowName}) is on stage \"$stage\", which its flow does not contain";
    }

    // a step that demands a document which cannot exist
    if ($flowName === 'supplier' && $stage === 'purchasing' && (int) $r['pos'] === 0 && $r['status'] !== 'completed') {
        // purchasing cannot approve a supplier request without a PO, and the PO button is
        // offered only while the request is pending/approved - so this is worth naming
        $problems[] = "{$r['pr_number']} (supplier) is at \"purchasing\" with no purchase order";
    }
}

echo "\n  requests per flow and the stages they sit on:\n";
foreach ($byType as $name => $stages) {
    $counts = array_count_values($stages);
    arsort($counts);
    $parts = [];
    foreach ($counts as $stage => $n) $parts[] = "$stage($n)";
    echo "    " . str_pad($name, 10) . count($stages) . " requests: " . implode(', ', $parts) . "\n";
}

// where the stock decisions land for each document_type, using the real items
echo "\n  stock decision per document_type (from the endpoint's own query):\n";
foreach (['ws', 'pr_po', 'po_ws'] as $docType) {
    $rows = $pdo->query("
        SELECT pri.id, pri.quantity, pri.delivered_quantity,
               i.item_name,
               COALESCE((SELECT SUM(quantity) FROM inventory_batches WHERE item_id = pri.item_id AND warehouse_id = pri.warehouse_id), 0) AS stock
        FROM pr_items pri
        JOIN purchase_requests pr ON pri.pr_id = pr.id
        LEFT JOIN item_names i ON pri.item_id = i.id
        WHERE pr.document_type = '$docType' AND pr.request_type = 'project'
        LIMIT 60
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) { echo "    " . str_pad($docType, 8) . " no items\n"; continue; }

    $toWs = 0; $toPo = 0; $wsShort = 0; $skipped = 0;
    foreach ($rows as $it) {
        $remaining = (float) $it['quantity'] - (float) $it['delivered_quantity'];
        if ($remaining <= 0) { $skipped++; continue; }
        $stock = (float) $it['stock'];
        if ($docType === 'ws') {
            // every item goes to the withdrawal slip, whatever the stock
            $toWs++;
            if ($stock < $remaining) $wsShort++;
        } elseif ($docType === 'pr_po') {
            $toPo++;
        } else {
            if ($stock >= $remaining) $toWs++;
            elseif ($stock > 0) { $toWs++; $toPo++; }
            else $toPo++;
        }
    }
    printf("    %-8s %3d item(s): %d to WS, %d to PO, %d already delivered%s\n",
        $docType, count($rows), $toWs, $toPo, $skipped,
        $docType === 'ws' ? ", of which $wsShort lack the stock the slip will demand" : '');
}

echo "\n  problems: " . count($problems) . "\n";
foreach (array_slice($problems, 0, 12) as $p) echo "    X $p\n";
if (count($problems) > 12) echo "    ... and " . (count($problems) - 12) . " more\n";
exit($problems ? 1 : 0);
