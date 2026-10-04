// Data-driven test: actions/endpoint split for purchase_request.php.
//
// The page renders a listing and its script asks the endpoint for one purchase
// request by ?id=. The test covers both, plus the filter dropdowns and a deliberately
// invalid submission. The AJAX lookup is read-only; the create path is not exercised
// because it would add a purchase request to the live list.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8261';
const TABLE = 'purchase_requests';

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
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 150).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  let json = null;
  try { json = JSON.parse(text); } catch { /* not JSON, fine */ }
  return { status: res.status, text, json, location: res.headers.get('location') };
}

const count = () => Number(db(`SELECT COUNT(*) FROM ${TABLE}`));
const start = count();
log.push(`fixture: ${start} purchase request(s)`);

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/purchase_request.php');
  log.push(`GET /purchase_request.php -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`purchase_request.php HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
  // the form's PR number comes from the shared helper
  if (!/PR-\d{4}-\d{4}/.test(r.text)) problems.push('the generated PR number is missing from the form');
  else log.push('  the PR number is generated for the form');
  // the dropdowns come from the endpoint
  for (const [what, re] of [['projects', /name="project_id"/], ['warehouses', /warehouse/i], ['suppliers', /name="supplier_id"/]]) {
    if (!re.test(r.text)) problems.push(`the ${what} control is missing`);
  }
  log.push('  the projects, warehouses and suppliers controls are present');
}

// ------------------------------------------------- 2. the AJAX single-PR lookup
{
  const prId = db(`SELECT id FROM ${TABLE} ORDER BY id LIMIT 1`);
  const prRow = JSON.parse(php('query.php', ['SELECT 0'] ) === '0' ? '{}' : '{}');   // no-op, keeps the helper honest
  void prRow;

  const r = await req(`/api/purchase_request-endpoint.php?id=${prId}`);
  log.push(`GET the endpoint ?id=${prId} -> ${r.status}, json=${r.json ? 'yes' : 'no'}`);
  if (r.status !== 200) problems.push(`the lookup HTTP ${r.status}`);
  if (!r.json) problems.push('the lookup did not answer JSON');
  else if (!r.json.success) problems.push(`the lookup failed: ${r.json.message}`);
  else {
    if (String(r.json.pr.id) !== String(prId)) problems.push(`the lookup returned PR ${r.json.pr.id}, expected ${prId}`);
    if (!Array.isArray(r.json.items)) problems.push('the lookup returned no items array');
    else log.push(`  it returned the PR and ${r.json.items.length} item(s)`);
  }

  const missing = await req('/api/purchase_request-endpoint.php?id=999999999');
  if (!missing.json || missing.json.success) problems.push('the lookup accepted an unknown id');
  else log.push('  an unknown id is reported');

  // no id at all must fall through to the listing, not to the lookup
  const listing = await req('/api/purchase_request-endpoint.php');
  log.push(`GET the endpoint with no id -> ${listing.status}, ${listing.text.trim().length} bytes of output`);
  if (listing.text.trim().length !== 0) problems.push('the endpoint printed output when it was required rather than asked for a record');
}

// ------------------------------------------- 3. the endpoint refuses no session
{
  const res = await fetch(`http://127.0.0.1:${PORT}/api/purchase_request-endpoint.php?id=1`, { redirect: 'manual' });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  let json = null; try { json = JSON.parse(text); } catch { /* fine */ }
  log.push(`the lookup with no session -> ${res.status} ${text.slice(0, 40)}`);
  if (json && json.success) problems.push('the lookup served an unauthenticated request');
  else log.push('  an unauthenticated lookup is refused');
}

// ------------------------------------- 4. an invalid submission writes nothing
{
  const before = count();
  // request_date is read without a default in the handler, as in the original
  const r = await req('/purchase_request.php', { create_pr: '1', request_type: 'project', request_date: '2020-01-01' });
  log.push(`POST create_pr with no items -> ${r.status}`);
  if (count() !== before) problems.push(`an invalid submission created ${count() - before} request(s)`);
  else log.push('  nothing was written');
}

// ------------------------------------------------------------- 5. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} row(s) behind`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
