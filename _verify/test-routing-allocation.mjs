// Verifies the routing algorithm's stock decision for each document_type.
//
//   node test-routing-allocation.mjs
//
// The three flows the page offers:
//
//   pr_po  (a purchase request answered by a Purchase Order)
//          every remaining item is ordered, whatever the stock - that is what the request
//          asks for.
//   ws     (a purchase request answered from stock, a Withdrawal Slip)
//          each item is withdrawn up to what the warehouse actually holds. It used to take
//          the whole remainder regardless: a slip was drawn for 12 against 3 on hand, and
//          processing it could only fail with "Insufficient stock available for item: ...".
//          The shortfall is now what it is, and is shown beside the input.
//   po_ws  (both - order what is missing, withdraw what is there)
//          stock first, and only the difference goes on the order.
//
// For every request the page renders, this reads the allocation the endpoint produced from
// the rendered page and checks it against the stock in the database.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8581';
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const rows = (s) => php('rows.php', [s]).split('\n').map(l => l.trim()).filter(l => l.startsWith('{')).map(l => JSON.parse(l));
const SID = JSON.parse(php('sessions.php'))[0].sid;

const problems = [];
const log = [];

const app = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2200));

// stock per item and warehouse, the same figure the endpoint uses
const stockOf = (itemId, warehouseId) => Number(rows(
  `SELECT COALESCE(SUM(quantity), 0) AS s FROM inventory_batches WHERE item_id = ${itemId} AND warehouse_id = ${warehouseId}`)[0].s);

for (const docType of ['ws', 'pr_po', 'po_ws']) {
  const requests = rows(`SELECT id, pr_number FROM purchase_requests
                         WHERE document_type = '${docType}' AND request_type = 'project'
                           AND status NOT IN ('completed','cancelled')
                         ORDER BY id DESC LIMIT 6`);
  if (!requests.length) { log.push(`${docType}: no open request to check`); continue; }

  for (const req of requests) {
    const res = await fetch(`http://127.0.0.1:${PORT}/pr_view_routing.php?id=${req.id}`, { headers: { Cookie: `PHPSESSID=${SID}` } });
    const html = Buffer.from(await res.arrayBuffer()).toString('utf8');
    if (/Fatal error|Parse error/.test(html)) { problems.push(`${req.pr_number}: the page does not render`); continue; }

    // the items the endpoint classified, from the island the page publishes
    const island = /window\.OCP_PAGE_PR_VIEW_ROUTING\s*=\s*(\{[\s\S]*?\});/.exec(html);
    let data = null;
    try { data = island ? JSON.parse(island[1]) : null; } catch { /* ignore */ }
    if (!data) { log.push(`${docType} ${req.pr_number}: no island to read`); continue; }

    // reconcile the WS rows the page offers against the stock
    const wsInputs = [...html.matchAll(/name="withdrawal_quantity_(\d+)"[\s\S]{0,240}?max="([\d.]+)"/g)]
      .map(m => ({ prItemId: Number(m[1]), max: Number(m[2]) }));

    let checked = 0;
    let over = 0;
    for (const ws of wsInputs) {
      const item = rows(`SELECT pri.id, pri.item_id, pri.warehouse_id, pri.quantity, COALESCE(pri.delivered_quantity,0) AS delivered
                         FROM pr_items pri WHERE pri.id = ${ws.prItemId}`)[0];
      if (!item) continue;
      const stock = stockOf(item.item_id, item.warehouse_id);
      const remaining = Number(item.quantity) - Number(item.delivered);
      checked++;
      // the slip may never ask for more than the warehouse holds
      if (ws.max > stock) {
        over++;
        problems.push(`${docType} ${req.pr_number}: item ${ws.prItemId} offers to withdraw ${ws.max} with ${stock} in stock`);
      }
      if (ws.max > remaining) {
        problems.push(`${docType} ${req.pr_number}: item ${ws.prItemId} offers ${ws.max} of a ${remaining} remainder`);
      }
    }

    const note = /short of stock/.test(html) ? 'shortfall shown' : 'no shortfall';
    log.push(`${docType.padEnd(6)} ${String(req.pr_number).padEnd(16)} WS rows ${String(checked).padStart(2)}, over stock ${over}, ${note}`);
  }
}

app.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems.slice(0, 10)) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
