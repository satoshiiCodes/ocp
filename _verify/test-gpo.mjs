// Data-driven test: actions/endpoint split for gasoline_purchase_order.php.
//
// The actions create, approve, complete and delete POs, so this test does not post any
// of those: it loads the page as every fixture role (the handlers are gated on four
// role flags computed by the page), exercises the folded single-PO lookup, and checks
// that submissions matching no branch write nothing. PO and item counts are compared
// before and after.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8280';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;
const db = (sql) => php('query.php', [sql]).trim();

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required|Cannot redeclare)/;

async function req(url, fields, cookie) {
  const opts = { headers: { Cookie: cookie || COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 160).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  let json = null;
  try { json = JSON.parse(text); } catch { /* not JSON, fine */ }
  return { status: res.status, text, json };
}

const counts = () => ({
  pos: Number(db('SELECT COUNT(*) FROM gasoline_purchase_orders')),
  items: Number(db('SELECT COUNT(*) FROM gasoline_po_items')),
});
const start = counts();
log.push(`fixture: ${JSON.stringify(start)}`);

// ------------------------------------------------- 1. renders for every role
{
  let rendered = 0;
  const sizes = [];
  for (const s of sessions) {
    const r = await req('/gasoline_purchase_order.php', undefined, `PHPSESSID=${s.sid}`);
    const who = `${s.dept}/${s.pos}`;
    if (r.status !== 200) { problems.push(`${who}: HTTP ${r.status}`); continue; }
    if (!/Logged in as:/.test(r.text)) problems.push(`${who}: the side menu is missing`);
    // the PO number comes from the shared helper (rendered in the form)
    if (!/\b\d{6}\b/.test(r.text)) problems.push(`${who}: the generated PO number is missing from the form`);
    rendered++;
    sizes.push(r.text.length);
  }
  log.push(`rendered for ${rendered} of ${sessions.length} roles`);
  if (rendered !== sessions.length) problems.push(`only ${rendered} of ${sessions.length} roles rendered`);
  if (new Set(sizes).size < 2) problems.push('every role produced the same page size; the role gating may have collapsed');
  else log.push(`  ${new Set(sizes).size} distinct page sizes across roles (the approve/delete controls are gated)`);
}

// ------------------------------------------- 2. the folded single-PO lookup
{
  const poId = db('SELECT id FROM gasoline_purchase_orders ORDER BY id LIMIT 1');
  const poNumber = poId ? db(`SELECT po_number FROM gasoline_purchase_orders WHERE id = ${poId}`) : '';
  if (!poId) {
    log.push('  (no PO on record to look up)');
  } else {
    const r = await req('/api/gasoline_purchase_order-endpoint.php', { po_id: poId });
    log.push(`POST the endpoint po_id=${poId} -> ${r.status}, json=${r.json ? 'yes' : 'no'}`);
    if (r.status !== 200) problems.push(`the lookup HTTP ${r.status}`);
    else if (!/data-po-id=/.test(r.text)) problems.push('the lookup did not return the PO markup');
    else if (poNumber && !r.text.includes(poNumber)) problems.push(`the markup does not mention ${poNumber}`);
    else log.push(`  it returned the markup for ${poNumber}`);

    const missing = await req('/api/gasoline_purchase_order-endpoint.php', { po_id: 999999999 });
    if (missing.json && missing.json.error) log.push(`  an unknown id is reported: ${missing.json.error}`);
    else problems.push('the lookup accepted an unknown id');

    // po_id sent but empty: still the lookup, answered with its "required" message
    const emptyId = await req('/api/gasoline_purchase_order-endpoint.php', { po_id: '' });
    if (emptyId.json && emptyId.json.error) log.push(`  an empty id is reported: ${emptyId.json.error}`);
    else problems.push('an empty id was not reported');

    const res = await fetch(`http://127.0.0.1:${PORT}/api/gasoline_purchase_order-endpoint.php`, {
      method: 'POST', body: new URLSearchParams({ po_id: poId }), redirect: 'manual',
    });
    const text = await res.text();
    let json = null; try { json = JSON.parse(text); } catch { /* fine */ }
    log.push(`  the lookup with no session -> ${res.status} ${json ? json.error : text.slice(0, 40)}`);
    if (json && !json.error) problems.push('the lookup served an unauthenticated request');
  }
}

// --------------------------------------------- 3. the listing path is silent
{
  const r = await req('/api/gasoline_purchase_order-endpoint.php');
  log.push(`GET the endpoint with no po_id -> ${r.status}, ${r.text.trim().length} bytes of output`);
  if (r.text.trim().length !== 0) problems.push('the endpoint printed output when it was required rather than asked for a record');
}

// ------------------------------------------ 4. submissions matching no branch
{
  const before = counts();
  const r = await req('/gasoline_purchase_order.php', { unrelated_field: '1' });
  log.push(`POST a payload matching no branch -> ${r.status}`);
  const after = counts();
  if (JSON.stringify(after) !== JSON.stringify(before)) {
    problems.push(`an unmatched submission changed the data: ${JSON.stringify(before)} -> ${JSON.stringify(after)}`);
  } else {
    log.push('  nothing was written');
  }
}

// ------------------------------------------------------------- 5. no leftovers
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
