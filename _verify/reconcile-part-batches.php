<?php
// Reconciles a part's batch quantities against the movement record.
//
//   php _verify/reconcile-part-batches.php <part_id> [--apply]
//
// The movements are the ledger: a batch's quantity should be its "in" movements minus its
// "out" movements, and the batches together should equal what the summary shows. This
// reports both per part, and with --apply corrects the batch that holds the initial stock
// of the part - the only place a mismatch can be absorbed without inventing a batch.
require_once __DIR__ . '/../config/db_config.php';

$partId = (int) ($argv[1] ?? 0);
$apply = in_array('--apply', $argv, true);
if ($partId <= 0) {
    fwrite(STDERR, "usage: reconcile-part-batches.php <part_id> [--apply]\n");
    exit(2);
}

$inv = $pdo->prepare("SELECT quantity FROM spare_parts_inventory WHERE part_id = ?");
$inv->execute([$partId]);
$inventory = $inv->fetchColumn();
$inventory = $inventory === false ? null : (float) $inventory;

$net = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE -quantity END), 0)
                      FROM spare_parts_movements WHERE part_id = ?");
$net->execute([$partId]);
$movementsNet = (float) $net->fetchColumn();

$batchSum = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM spare_parts_batches WHERE part_id = ?");
$batchSum->execute([$partId]);
$batchesTotal = (float) $batchSum->fetchColumn();

printf("  part %d: inventory %s, movements net %s, batches %s\n", $partId,
    $inventory === null ? '(no row)' : $inventory, $movementsNet, $batchesTotal);

$expected = $inventory === null ? $movementsNet : $inventory;
$difference = $batchesTotal - $expected;
if (abs($difference) < 0.001) {
    echo "  batches already agree with the summary - nothing to do\n";
    exit(0);
}
printf("  batches are out by %+g\n", $difference);

// The initial-stock batch is the one to carry the correction: it is the part's opening
// balance, and every other batch was created by a movement of its own.
$stmt = $pdo->prepare("SELECT b.id, b.quantity, b.notes FROM spare_parts_batches b
                       WHERE b.part_id = ? ORDER BY (b.notes = 'Initial stock') DESC, b.date_received ASC, b.id ASC");
$stmt->execute([$partId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "  the part has no batches at all; nothing can carry the correction\n";
    exit(1);
}
$target = $rows[0];
$newQuantity = (float) $target['quantity'] - $difference;
echo "  batch {$target['id']} holds " . $target['quantity'] . " (" . ($target['notes'] ?: 'no note') . ")\n";
printf("  it should hold %g so the batches total %g\n", $newQuantity, $expected);

if ($newQuantity < 0) {
    echo "  refusing: that would make the batch negative\n";
    exit(1);
}

if (!$apply) {
    echo "  DRY RUN - pass --apply to write it\n";
    exit(0);
}

$upd = $pdo->prepare("UPDATE spare_parts_batches SET quantity = ? WHERE id = ?");
$upd->execute([$newQuantity, (int) $target['id']]);

$batchSum->execute([$partId]);
printf("  APPLIED: batches now total %s\n", $batchSum->fetchColumn());
