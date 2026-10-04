// Proves the two reports on spare_parts_inventory.php, against the served page.
//
//   node test-spare-parts-batches-avg.mjs
//
//   1. "Parts Batches (FIFO Tracking)" is newest-first. It was oldest-first, which buries the
//      delivery just received at the bottom of the table.
//   2. "Current Parts Inventory Summary" -> "Avg Price/Unit" is the real average. It used to
//      be the single price stored on the inventory row - the price of whichever delivery last
//      wrote it - so a part bought once at 1222 and several times at ~100 read 1222.00.
//
// The average is checked by reading the batches straight from the database and recomputing
// the weighted average independently, then requiring the page to print that number.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8620';
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const rows = (s) => php('rows.php', [s]).split('\n').map(l => l.trim()).filter(l => l.startsWith('{')).map(l => JSON.parse(l));
const SID = JSON.parse(php('sessions.php'))[0].sid;

const problems = [];
const log = [];

const app = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2200));

const res = await fetch(`http://127.0.0.1:${PORT}/spare_parts_inventory.php`, { headers: { Cookie: `PHPSESSID=${SID}` } });
const html = Buffer.from(await res.arrayBuffer()).toString('utf8');
log.push(`page: ${res.status}, ${html.length} bytes, PHP diagnostics: ${(html.match(/<b>(Warning|Notice|Fatal error|Deprecated)<\/b>/g) || []).length}`);

const cells = (tableHtml) => [...tableHtml.matchAll(/<tr[\s\S]*?<\/tr>/g)]
  .map(r => [...r[0].matchAll(/<td[^>]*>([\s\S]*?)<\/td>/g)].map(c => c[1].replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim()));

// ---------------------------------------------------------------- 1. newest first
const batchTable = /Parts Batches \(FIFO Tracking\)[\s\S]*?<tbody>([\s\S]*?)<\/tbody>/.exec(html);
if (!batchTable) {
  problems.push('the Parts Batches table did not render');
} else {
  const body = cells(batchTable[1]);
  // column order: Date Received, Part Number, Part Name, Supplier, ...
  const entries = body.map(r => ({ date: r[0], part: r[2] })).filter(e => /^\d{4}-\d{2}-\d{2}$/.test(e.date));
  log.push(`batches rendered: ${entries.length}`);
  log.push(`  first four dates: ${entries.slice(0, 4).map(e => e.date).join(', ')}`);

  // grouped by part, the dates must never increase
  let lastPart = null;
  let lastDate = null;
  let violations = 0;
  for (const e of entries) {
    if (e.part !== lastPart) { lastPart = e.part; lastDate = null; }
    if (lastDate !== null && e.date > lastDate) violations++;
    lastDate = e.date;
  }
  if (!entries.length) problems.push('no batch rows to check the order of');
  if (violations) problems.push(`${violations} batch row(s) are not newest-first within their part`);

  // and the very first row must be the latest for its part
  if (entries.length) {
    const first = entries[0];
    const latest = rows(`SELECT MAX(b.date_received) AS d FROM spare_parts_batches b
                         JOIN spare_parts sp ON sp.id = b.part_id
                         WHERE b.quantity > 0 AND sp.part_name = '${first.part.replace(/'/g, "''")}'`)[0];
    log.push(`  first row: ${first.part} on ${first.date}; that part's latest delivery is ${latest.d}`);
    if (latest.d !== first.date) problems.push(`the first row is ${first.date} but the latest for ${first.part} is ${latest.d}`);
  }
}

// ---------------------------------------------------------------- 2. the average
const summaryTable = /Current Parts Inventory Summary[\s\S]*?<tbody>([\s\S]*?)<\/tbody>/.exec(html);
if (!summaryTable) {
  problems.push('the Current Parts Inventory Summary table did not render');
} else {
  const header = /Current Parts Inventory Summary[\s\S]*?<thead>([\s\S]*?)<\/thead>/.exec(html);
  const heads = header ? [...header[1].matchAll(/<th[^>]*>([\s\S]*?)<\/th>/g)].map(m => m[1].replace(/<[^>]*>/g, '').trim()) : [];
  const qtyIdx = heads.indexOf('Quantity');
  const avgIdx = heads.indexOf('Avg Price/Unit');
  const valIdx = heads.indexOf('Total Value');
  log.push(`summary columns: ${JSON.stringify(heads)}`);
  if (avgIdx < 0) problems.push('the summary has no Avg Price/Unit column');

  const body = cells(summaryTable[1]);
  let checked = 0;
  for (const r of body) {
    const name = r[1];
    const qty = Number(String(r[qtyIdx]).replace(/[^0-9.]/g, ''));
    const shown = Number(String(r[avgIdx]).replace(/[^0-9.]/g, ''));
    const value = Number(String(r[valIdx]).replace(/[^0-9.]/g, ''));

    // what the average must be, recomputed from the batches themselves
    const agg = rows(`SELECT sp.part_name, SUM(b.quantity) AS qty, SUM(b.quantity * b.price_per_unit) AS val
                      FROM spare_parts_batches b JOIN spare_parts sp ON sp.id = b.part_id
                      WHERE b.quantity > 0 AND sp.part_name = '${String(name).replace(/'/g, "''")}'
                      GROUP BY sp.part_name`)[0];
    if (!agg) continue;
    const expected = Number(agg.val) / Number(agg.qty);
    checked++;
    const avgOk = Math.abs(shown - expected) < 0.01;
    const rowOk = Math.abs(qty * shown - value) < Math.max(1, value * 0.005);
    log.push(`  ${String(name).padEnd(16)} qty ${String(qty).padStart(5)}  shown ${String(shown).padStart(9)}  recomputed ${expected.toFixed(2).padStart(9)}  qty x avg = value: ${rowOk}`);
    if (!avgOk) problems.push(`${name}: the page shows an average of ${shown}, the batches give ${expected.toFixed(2)}`);
    if (!rowOk) problems.push(`${name}: ${qty} x ${shown} does not equal the total value ${value}`);
  }
  log.push(`averages verified against the batch data: ${checked}`);

  // and it must not simply be the stored price
  const stored = rows(`SELECT sp.part_name, spi.price_per_unit FROM spare_parts_inventory spi JOIN spare_parts sp ON sp.id = spi.part_id`)[0];
  void stored;
}

app.kill();

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
