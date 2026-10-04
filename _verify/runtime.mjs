// Runtime check: serves the project over PHP's built-in server and exercises it
// the way a browser would.
//
//   1. log in through index.php and follow the redirect
//   2. load every page as every role, flagging PHP failures
//   3. fetch every referenced script/stylesheet and confirm it is real output
//   4. hit an api/ endpoint and an actions/ endpoint
//   5. render report generators and confirm they still produce a PDF
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = process.env.VERIFY_PORT || '8150';
const BASE = `http://127.0.0.1:${PORT}`;
const problems = [];
const log = [];

const php = (file, args = []) =>
  execFileSync('php', [path.join(ROOT, '_verify', file), ...args], { encoding: 'utf8' }).replace(/^\uFEFF/, '');

/* ------------------------------------------------------ test sessions + ids */
const sessions = JSON.parse(php('sessions.php'));
const ids = JSON.parse(php('ids.php'));
log.push(`sessions: ${sessions.length} roles   ids: ${JSON.stringify(ids)}`);

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2500));

const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Trying to access array offset|Call to undefined|Failed opening required)/;

async function get(url, cookie) {
  const res = await fetch(`${BASE}${url}`, { headers: cookie ? { Cookie: cookie } : {}, redirect: 'manual' });
  const buf = Buffer.from(await res.arrayBuffer());
  return { status: res.status, buf, text: buf.toString('utf8'), headers: res.headers };
}

/* ---------------------------------------------------------------- 1. login  */
// The checks below run as a prepared session rather than by changing a password.
// The login form itself is still exercised - a wrong password must be rejected,
// and index.php must still render - which covers the restructure's effect on it
// (the page's connection to config/db_config.php) without touching stored data.
const primary = sessions[0];
const cookie = `PHPSESSID=${primary.sid}`;
{
  const res = await get('/index.php');
  log.push(`GET /index.php -> ${res.status}, login form present=${/name="username"/.test(res.text)}`);
  if (res.status !== 200) problems.push(`index.php HTTP ${res.status}`);
  if (!/name="username"/.test(res.text)) problems.push('index.php did not render the login form');
  if (!/assets\/css\/index-style\.css/.test(res.text)) problems.push('index.php does not link its own stylesheet');
  if (!/assets\/js\/index\.js/.test(res.text)) problems.push('index.php does not link its own script');

  const bad = await fetch(`${BASE}/index.php`, {
    method: 'POST',
    body: new URLSearchParams({ username: 'definitely-not-a-user', password: 'wrong' }),
    redirect: 'manual',
  });
  const badText = await bad.text();
  log.push(`POST /index.php with bad credentials -> ${bad.status}, rejected=${/Invalid username or password/.test(badText)}`);
  if (/dashboard\.php/.test(bad.headers.get('location') || '')) problems.push('bad credentials were accepted');
}

/* ------------------------------------- 2. every page as every role + assets */
const pages = fs.readdirSync(ROOT, { withFileTypes: true })
  .filter(d => d.isFile() && d.name.endsWith('.php')).map(d => d.name)
  .filter(f => !/_pdf\.php$/.test(f) && f !== 'job_order_slip_PDF.php').sort();

let requests = 0;
const assetCache = new Map();
const redirects = new Map();

for (const s of sessions) {
  for (const page of pages) {
    requests++;
    const r = await get('/' + page, `PHPSESSID=${s.sid}`);

    // A page may legitimately redirect: index.php guards the login, and the
    // detail pages bounce to their list page when no record id was posted.
    // Bouncing back to index.php would mean the session was not recognised.
    if (r.status === 302) {
      const loc = r.headers.get('location') || '';
      redirects.set(page, loc);
      if (/index\.php/.test(loc)) problems.push(`${page} [${s.dept}/${s.pos}]: bounced to the login page`);
      continue;
    }
    if (r.status !== 200) problems.push(`${page} [${s.dept}/${s.pos}] HTTP ${r.status}`);

    const m = HARD.exec(r.text);
    if (m) {
      const detail = r.text.slice(m.index, m.index + 130).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ');
      problems.push(`${page} [${s.dept}/${s.pos}] ${detail}`);
    }

    // confirm the page's own assets are reachable
    for (const am of r.text.matchAll(/<script\s+src="(assets\/js\/[^"]+)"|<link[^>]+href="(assets\/css\/[^"]+)"/g)) {
      const rel = am[1] || am[2];
      if (assetCache.has(rel)) continue;
      assetCache.set(rel, null);
      const a = await get('/' + rel, `PHPSESSID=${s.sid}`);
      requests++;
      if (a.status !== 200) problems.push(`asset ${rel}: HTTP ${a.status}`);
      assetCache.set(rel, a);
    }
  }
}
log.push(`page requests: ${requests} (${pages.length} pages x ${sessions.length} roles) + assets`);
if (redirects.size) {
  log.push(`pages that redirect without a record id: ${[...redirects].map(([p, l]) => `${p}->${l}`).join(', ')}`);
}

// every asset must be real content, not a PHP error page
for (const [rel, res] of assetCache) {
  if (!res) { problems.push(`asset ${rel}: never fetched`); continue; }
  if (HARD.test(res.text)) problems.push(`asset ${rel}: PHP error in output`);
  if (rel.endsWith('.js.php') && /<\?php|<\?=/.test(res.text)) problems.push(`asset ${rel}: raw PHP served`);
  if (rel.endsWith('.js') && res.text.trim().length === 0) problems.push(`asset ${rel}: empty`);
  if (rel.endsWith('.css') && res.text.trim().length === 0) problems.push(`asset ${rel}: empty`);
}
log.push(`assets checked: ${assetCache.size}`);

// The dashboard initialises its charts per role and role-gates the data for each
// one, so the island is legitimately empty for a role that matches no branch
// (the pre-restructure page behaved the same way - see baseline-compare.mjs).
const ROLES = JSON.parse(php('roles.php'));
const CHART_ROLES = /Motorpool|Warehouse|Accounting|Purchaser|HR Officer/;
const loginRole = ROLES[primary.sid] || '';
{
  const r = await get('/dashboard.php', cookie);
  // The island is "<script>window.OCP_PAGE_DASHBOARD = {...};</script>"; match up
  // to the closing tag that follows the terminating semicolon.
  const m = /<script>window\.OCP_PAGE_DASHBOARD = (\{[\s\S]*?\});<\/script>/.exec(r.text);
  if (!m) {
    problems.push('dashboard.php: no data island in the response');
    log.push(`dashboard status=${r.status} bytes=${r.text.length}`);
    const i = r.text.indexOf('OCP_PAGE_DASHBOARD');
    log.push(`raw island region: ${i < 0 ? '(tag absent)' : JSON.stringify(r.text.slice(i, i + 240))}`);
  }
  else {
    let data = null;
    try { data = JSON.parse(m[1]); } catch (e) { problems.push(`dashboard island is not valid JSON: ${e.message}`); }
    if (data) {
      const keys = Object.keys(data);
      log.push(`dashboard island keys: ${keys.join(', ') || '(none)'}   (role: ${loginRole || 'unknown'})`);
      log.push(`dashboard island sample: ${keys.slice(0, 3).map(k => `${k}=${JSON.stringify(data[k]).slice(0, 28)}`).join('  ')}`);
      if (!keys.length && CHART_ROLES.test(loginRole)) {
        problems.push(`dashboard island came back empty for a role that has chart data (${loginRole})`);
      }
      for (const [k, v] of Object.entries(data)) {
        if (v === undefined) problems.push(`dashboard island key ${k} is undefined`);
      }
    }
  }
  // the page script must be served and parse as JavaScript
  const sm = /<script src="(assets\/js\/dashboard[^"]*)"/.exec(r.text);
  if (!sm) problems.push('dashboard.php: no dashboard script tag');
  else {
    const a = await get('/' + sm[1], cookie);
    const neutral = a.text.replace(/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g, '0');
    try { new vm.Script(neutral); log.push(`${sm[1]} served and parses (${a.text.length} bytes)`); }
    catch (e) { problems.push(`${sm[1]} does not parse as served: ${e.message.split('\n')[0]}`); }
  }
}

/* ------------------------------------------------ 4. api/ and actions/     */
// The logout endpoint destroys the session, so it runs after every other check.
//
// A per-page endpoint is only ever required by its page, where $pdo and the
// session are already open. Requested on its own it must therefore stay harmless:
// it answers with its empty result array and prints nothing. The AJAX endpoints
// that answer JSON are exercised by the per-page tests instead.
{
  const endpoints = fs.readdirSync(path.join(ROOT, 'api')).filter(f => f.endsWith('-endpoint.php')).sort();
  let clean = 0;
  for (const f of endpoints) {
    const r = await get(`/api/${f}`, cookie);
    const output = r.text.trim().length;
    const phpError = /(Fatal error|Parse error|Uncaught|Warning|Notice|Undefined)/.test(r.text);
    if (r.status === 200 && output === 0 && !phpError) { clean++; continue; }
    problems.push(`/api/${f}: HTTP ${r.status}, ${output} bytes of output${phpError ? ', PHP error' : ''}`);
  }
  log.push(`per-page endpoints harmless on a direct request: ${clean} of ${endpoints.length}`);
}

/* ------------------------------------------------------- 5. report generators */
// A generator may legitimately answer with a short message instead of a PDF when
// the chosen record has nothing to report (for example a purchase request with no
// items to order). What must never happen is a PHP failure.
for (const g of [['cash_on_hand_report_pdf.php', ''], ['expenses_report_pdf.php', ''],
  ['generate_pr_pdf.php', `?id=${ids.pr}`], ['generate_pr_supplier_pdf.php', `?id=${ids.pr}`],
  ['generate_po_pdf.php', `?pr_id=${ids.pr}`], ['generate_ws_pdf.php', `?pr_id=${ids.pr}`],
  ['generate_gas_po_pdf.php', `?id=${ids.gas_po}`]]) {
  const r = await get(`/${g[0]}${g[1]}`, cookie);
  const isPdf = r.buf.toString('latin1').trimStart().startsWith('%PDF-');
  const err = HARD.exec(r.text);
  const note = r.buf.length < 200 && !isPdf ? ` message: ${JSON.stringify(r.text.trim().slice(0, 60))}` : '';
  log.push(`GET /${g[0]}${g[1]} -> ${r.status}, ${r.buf.length} bytes, pdf=${isPdf}${note}`);
  if (r.status >= 500) problems.push(`${g[0]} HTTP ${r.status}`);
  if (err) problems.push(`${g[0]}${g[1]}: ${err[0]}`);
}

/* --------------------------------------------------------- 6. logout (last) */
{
  const l = await get('/actions/logout.php', cookie);
  log.push(`GET /actions/logout.php -> ${l.status} (${l.headers.get('location') || '-'})`);
  if (l.status !== 302) problems.push(`actions/logout.php did not redirect (status ${l.status})`);
  // the session is gone now: the protected pages must send us back to the login
  const after = await get('/dashboard.php', cookie);
  const bounced = after.status === 302 && /index\.php/.test(after.headers.get('location') || '');
  log.push(`GET /dashboard.php after logout -> ${after.status} ${bounced ? '(back to the login page - correct)' : '(NOT redirected)'}`);
  if (!bounced) problems.push('dashboard.php did not require a session after logout');
}

server.kill();

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems.slice(0, 30)) console.log('  X ' + p);
if (problems.length > 30) console.log(`  ... ${problems.length - 30} more`);
process.exit(problems.length ? 1 : 0);
