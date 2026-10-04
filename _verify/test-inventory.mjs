// Data-driven test: actions/endpoint split for inventory.php.
//
// The actions move stock, so this test does not post a real movement: it checks the
// page renders with its dropdowns and lists, that the folded single-movement lookup
// answers, and that submissions matching no branch write nothing. Inventory, movement
// and batch counts are compared before and after.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8276';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;
const db = (sql) => php('query.php', [sql]).trim();

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;

async function req(url, fields) {
  const opts = { headers: { Cookie: COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(0, 0) || ''}${text.slice(err.index, err.index + 150).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  let json = null;
  try { json = JSON.parse(text); } catch { /* not JSON, fine */ }
  return { status: res.status, text, json };
}

const counts = () => ({
  inventory: Number(db('SELECT COUNT(*) FROM inventory')),
  movements: Number(db('SELECT COUNT(*) FROM stock_movements')),
  batches: Number(db('SELECT COUNT(*) FROM inventory_batches')),
});
const start = counts();
log.push(`fixture: ${JSON.stringify(start)}`);

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/inventory.php');
  log.push(`GET /inventory.php -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`inventory.php HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
  if (!/Inventory/i.test(r.text)) problems.push('the page did not render its heading');
  else log.push('  the page renders');
  // the dropdowns the endpoint fills
  // the supplier field is filled in by the page's script when the movement type needs
  // one; the markup itself only carries these three, in the original and now.
  for (const [what, re] of [['items', /name="item_id"/], ['warehouses', /name="warehouse_id"/], ['projects', /name="project_id"/]]) {
    if (!re.test(r.text)) problems.push(`the ${what} control is missing`);
  }
  log.push('  the item, warehouse and project controls are present');
}

// ------------------------------------------ 2. the folded single-movement lookup
{
  const mvId = db('SELECT id FROM stock_movements ORDER BY id LIMIT 1');
  if (!mvId) {
    log.push('  (no movement on record to look up)');
  } else {
    const r = await req(`/api/inventory-endpoint.php?id=${mvId}`);
    log.push(`GET the endpoint ?id=${mvId} -> ${r.status}, json=${r.json ? 'yes' : 'no'}`);
    if (!r.json) problems.push('the lookup did not answer JSON');
    else if (!r.json.success) problems.push(`the lookup failed: ${JSON.stringify(r.json).slice(0, 90)}`);
    else log.push('  it returned the movement');

    const missing = await req('/api/inventory-endpoint.php?id=999999999');
    if (!missing.json || missing.json.success) problems.push('the lookup accepted an unknown id');
    else log.push('  an unknown id is reported');
  }
}

// ------------------------------------- 3. no id falls through to the listing
{
  const r = await req('/api/inventory-endpoint.php');
  log.push(`GET the endpoint with no id -> ${r.status}, ${r.text.trim().length} bytes of output`);
  if (r.text.trim().length !== 0) problems.push('the endpoint printed output when it was required rather than asked for a record');
}

// ---------------------------------------- 4. submissions that match no branch
{
  const before = counts();
  const r = await req('/inventory.php', { unrelated_field: '1' });
  log.push(`POST a payload matching no branch -> ${r.status}`);
  const after = counts();
  if (JSON.stringify(after) !== JSON.stringify(before)) {
    problems.push(`an unmatched submission changed the data: ${JSON.stringify(before)} -> ${JSON.stringify(after)}`);
  } else {
    log.push('  nothing was written');
  }
}

// ------------------------------------- 5. a delete for a movement that is not there
{
  const before = counts();
  await req('/inventory.php', { delete_movement: '1', movement_id: 999999999 });
  const after = counts();
  if (JSON.stringify(after) !== JSON.stringify(before)) {
    problems.push(`deleting a non-existent movement changed the data: ${JSON.stringify(before)} -> ${JSON.stringify(after)}`);
  } else {
    log.push('deleting a non-existent movement left the data alone');
  }
}

// ------------------------------------------------------------- 6. no leftovers
{
  const end = counts();
  if (JSON.stringify(end) !== JSON.stringify(start)) {
    problems.push(`the test left data behind: ${JSON.stringify(start)} -> ${JSON.stringify(end)}`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
