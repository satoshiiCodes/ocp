// Data-driven test: actions/endpoint split for purchase_request_spare_parts.php.
//
// Two live files were folded in: the document-number action the page's JavaScript
// posts for, and the single-record lookup it fetches. Both are covered here, along
// with the page's own rendering. Creating a request would add to the live list, so
// only an invalid submission is posted. Row counts are compared before and after.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8273';
const ACTIONS = '/actions/purchase_request_spare_parts-actions.php';

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
  return { status: res.status, text, json };
}

const count = () => Number(db('SELECT COUNT(*) FROM spare_parts_pr'));
const start = count();
log.push(`fixture: ${start} spare-parts request(s)`);

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/purchase_request_spare_parts.php');
  log.push(`GET the page -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`the page HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
  const parts = Number(db('SELECT COUNT(*) FROM spare_parts'));
  if (!/Spare Parts Purchase Request|Purchase Request/i.test(r.text)) problems.push('the page did not render its heading');
  else log.push(`  the page renders (${parts} part(s) exist for its dropdowns)`);
}

// ------------------------------------- 2. the document-number action (was its own file)
{
  const want = { issue_materials: 'PRWS-', issue: 'IPPR-', stock: 'SPR-' };
  for (const [type, prefix] of Object.entries(want)) {
    const r = await req(ACTIONS, { request_type: type });
    if (!r.json || !r.json.success) { problems.push(`the document number for ${type} failed: ${r.text.slice(0, 90)}`); continue; }
    if (!r.json.document_number.startsWith(prefix)) {
      problems.push(`the ${type} number is "${r.json.document_number}", expected the ${prefix} prefix`);
    } else {
      log.push(`  request_type=${type.padEnd(16)} -> ${r.json.document_number}`);
    }
  }

  // an unauthenticated request must still be refused
  const res = await fetch(`http://127.0.0.1:${PORT}${ACTIONS}`, {
    method: 'POST', body: new URLSearchParams({ request_type: 'stock' }), redirect: 'manual',
  });
  const text = await res.text();
  let json = null; try { json = JSON.parse(text); } catch { /* fine */ }
  log.push(`  the document number with no session -> ${res.status} ${text.slice(0, 40)}`);
  if (json && json.success) problems.push('the document number was served without a session');
}

// ---------------------------------------------- 3. the single-record lookup
{
  const prId = db('SELECT id FROM spare_parts_pr ORDER BY id LIMIT 1');
  const r = await req(`/api/purchase_request_spare_parts-endpoint.php?id=${prId}`);
  log.push(`GET the endpoint ?id=${prId} -> ${r.status}, json=${r.json ? 'yes' : 'no'}`);
  if (!r.json) problems.push('the lookup did not answer JSON');
  else if (!r.json.success) problems.push(`the lookup failed: ${r.json.message || JSON.stringify(r.json).slice(0, 80)}`);
  else log.push('  it returned the request');

  const missing = await req('/api/purchase_request_spare_parts-endpoint.php?id=999999999');
  if (!missing.json || missing.json.success) problems.push('the lookup accepted an unknown id');
  else log.push('  an unknown id is reported');
}

// ------------------------------------------ 4. no id falls through to the listing
{
  const r = await req('/api/purchase_request_spare_parts-endpoint.php');
  log.push(`GET the endpoint with no id -> ${r.status}, ${r.text.trim().length} bytes of output`);
  if (r.text.trim().length !== 0) problems.push('the endpoint printed output when it was required rather than asked for a record');
}

// ------------------------------------- 5. a submission the page does not handle
// create_pr is NOT exercised: the handler inserts the request without validating the
// items list first (recorded in _verify/KNOWN-ISSUES.md), so any POST would add a row
// to the live list. A payload matching no branch proves nothing is written by accident.
{
  const before = count();
  const r = await req('/purchase_request_spare_parts.php', { unrelated_field: '1', request_date: '2020-01-01' });
  log.push(`POST a payload matching no branch -> ${r.status}`);
  if (count() !== before) problems.push(`an unmatched submission created ${count() - before} request(s)`);
  else log.push('  nothing was written');
}

// ------------------------------------------------------------- 6. no leftovers
{
  if (count() !== start) problems.push(`the test left ${count() - start} row(s) behind`);
  else log.push('no rows left behind');
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
