// Data-driven test: endpoint split for dashboard.php.
//
// The dashboard is read-only but heavily role-gated, so this loads it as every
// fixture role and checks each one renders its own variant: the right page marker,
// no PHP failure text, and a data island whose keys match the role.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8260';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const db = (sql) => php('query.php', [sql]).trim();

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;

// which panel each role should get, matching the page's if / elseif chain
const markerFor = (s) => {
  if (s.dept === 'Motorpool') return /Motorpool/i;
  if (s.dept === 'Warehouse') return /Warehouse/i;
  if (s.dept === 'Admin' && s.pos === 'HR Officer') return /Human Resource|HR|Employee/i;
  if (s.dept === 'Admin' && s.pos === 'Accounting') return /Expense|Accounting/i;
  if (s.dept === 'Admin' && s.pos === 'Purchaser') return /Purchase|Gasoline|Inventory/i;
  return null;   // the remaining roles fall through to the general dashboard
};

const island = (html) => {
  const m = /window\.OCP_PAGE_DASHBOARD\s*=\s*(\{[\s\S]*?\});/.exec(html);
  if (!m) return null;
  try { return JSON.parse(m[1]); } catch { return null; }
};

let rendered = 0;
const sizes = [];

for (const s of sessions) {
  const res = await fetch(`http://127.0.0.1:${PORT}/dashboard.php`, {
    headers: { Cookie: `PHPSESSID=${s.sid}` }, redirect: 'manual',
  });
  const html = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const who = `${s.dept}/${s.pos}`;

  if (res.status !== 200) { problems.push(`${who}: HTTP ${res.status}`); continue; }
  const err = HARD.exec(html);
  if (err) {
    problems.push(`${who}: PHP ${html.slice(err.index, err.index + 140).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
    continue;
  }
  if (!/<html/i.test(html)) { problems.push(`${who}: did not render a page`); continue; }
  if (!/Logged in as:/.test(html)) problems.push(`${who}: the side menu is missing`);

  const want = markerFor(s);
  if (want && !want.test(html)) problems.push(`${who}: its own panel marker ${want} was not found`);
  const keys = island(html);
  if (keys === null) problems.push(`${who}: the data island is missing or unparsable`);

  rendered++;
  sizes.push(html.length);
  log.push(`  ${who.padEnd(34)} ${String(html.length).padStart(6)} bytes, island keys: ${keys ? Object.keys(keys).length : 0}`);
}

log.unshift(`rendered for ${rendered} of ${sessions.length} roles`);

// every role must produce a real page, and not all the same size (they are gated)
if (rendered !== sessions.length) problems.push(`only ${rendered} of ${sessions.length} roles rendered`);
if (new Set(sizes).size < 3) problems.push(`only ${new Set(sizes).size} distinct page size(s) across roles; the gating may have collapsed`);

// the actions file is a guard only
{
  const res = await fetch(`http://127.0.0.1:${PORT}/actions/dashboard-actions.php`, { headers: { Cookie: `PHPSESSID=${sessions[0].sid}` } });
  const text = await res.text();
  if (text.trim().length !== 0) problems.push('the actions file printed output when requested directly');
  else log.push('the actions file prints nothing on its own');
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
void db;
process.exit(problems.length ? 1 : 0);
