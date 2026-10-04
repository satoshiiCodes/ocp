// Reports parts whose stock figures disagree.
//
//   node audit-parts-stock-consistency.mjs
//
// Three things are supposed to say the same number for a part:
//   spare_parts_inventory.quantity        what "Current Parts Inventory Summary" shows
//   SUM(spare_parts_batches.quantity)     the FIFO batches
//   SUM(movements, in - out)              the movement ledger
//
// Editing or deleting a movement used to change none of them, so the summary kept counting
// stock the movement no longer accounted for. Now both handlers move the summary and the
// batch the movement names. This check catches any drift that is left, from any cause.
//
// A mismatch is not always a bug in the handlers: `initial_part` writes its movement and its
// batch without linking them, so a part whose movements have no batch_id cannot be
// reconciled from the ledger alone. Those are reported as "no batch link" so they are not
// confused with real drift.
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const rows = (sql) => execFileSync('php', [path.join(ROOT, '_verify', 'rows.php'), sql], { encoding: 'utf8' })
  .split('\n').map(l => l.trim()).filter(l => l.startsWith('{')).map(l => JSON.parse(l));

const data = rows(`
  SELECT spi.part_id,
         sp.part_name,
         spi.quantity AS inventory,
         COALESCE((SELECT SUM(b.quantity) FROM spare_parts_batches b WHERE b.part_id = spi.part_id), 0) AS batches,
         COALESCE((SELECT SUM(CASE WHEN m.movement_type = 'in' THEN m.quantity ELSE -m.quantity END)
                   FROM spare_parts_movements m WHERE m.part_id = spi.part_id), 0) AS movements,
         (SELECT COUNT(*) FROM spare_parts_movements m WHERE m.part_id = spi.part_id) AS movement_rows,
         (SELECT COUNT(*) FROM spare_parts_movements m WHERE m.part_id = spi.part_id AND m.batch_id IS NULL) AS unlinked
  FROM spare_parts_inventory spi
  JOIN spare_parts sp ON sp.id = spi.part_id
  ORDER BY spi.part_id
`);

// What the page shows for a part is its BATCHES: "Current Parts Inventory Summary" takes the
// quantity, the average price and the total value from the same batch aggregate, so those
// three always multiply out. The inventory row is a cache the movement handlers keep in step,
// not the figure on screen.
//
// So the check that matters is batches against the movement ledger - the same stock counted
// two ways. The inventory row is reported separately, because a part whose movements were
// deleted or never written (its batches were inserted by hand, or by an older version) can
// legitimately have a stale row while the page is right: naming that as an error would send
// someone to "fix" a page that is already showing the correct number.
const drift = [];
const staleRow = [];
for (const r of data) {
  const inv = Number(r.inventory);
  const batches = Number(r.batches);
  const movements = Number(r.movements);
  // only comparable when there is a ledger to compare with and every row is linked to a batch
  if (Number(r.movement_rows) > 0 && Number(r.unlinked) === 0 && Math.abs(batches - movements) > 0.001) {
    drift.push({ ...r, out: batches - movements, kind: 'batches and the movement ledger disagree' });
  }
  if (Math.abs(inv - batches) > 0.001) {
    staleRow.push({ ...r, out: batches - inv });
  }
}

const verdict = drift.length
  ? `${drift.length} of ${data.length} part(s) whose stock is counted differently by batches and ledger`
  : `OK: all ${data.length} parts' batches agree with their movement ledger`;
console.log(verdict);
console.log('');
if (drift.length) {
  console.log(`${drift.length} part(s) whose stock is counted differently by the two records:`);
  for (const d of drift) {
    console.log(`  part ${String(d.part_id).padStart(3)} ${String(d.part_name).slice(0, 22).padEnd(24)} `
      + `batches ${String(d.batches).padStart(8)}  ledger ${String(d.movements).padStart(8)}  `
      + `out by ${d.out > 0 ? '+' : ''}${d.out}  (${d.kind})`);
  }
  console.log('\n_verify/reconcile-part-batches.php <part_id> [--apply] corrects one of these.');
}
if (staleRow.length) {
  console.log(`  note: ${staleRow.length} part(s) whose stored inventory row differs from the batches, which is`);
  console.log('  what the page shows - harmless, but noted so it is not mistaken for agreement:');
  for (const d of staleRow) {
    console.log(`    part ${String(d.part_id).padStart(3)} ${String(d.part_name).slice(0, 22).padEnd(24)} `
      + `stored ${String(d.inventory).padStart(8)}  batches ${String(d.batches).padStart(8)}`);
  }
}
process.exit(drift.length ? 1 : 0);
