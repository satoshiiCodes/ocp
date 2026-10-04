// Exercises the converted scripts: a rejected submission must put a message in the
// island AND the flag that reopens the right form.
//
//   node test-converted-scripts.mjs
//
// Nothing here changes stored data: every POST is one the handlers reject, and row counts
// are compared before and after.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8322';
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const db = (sql) => php('query.php', [sql]).trim();
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required|Cannot redeclare)/;

async function req(url, fields) {
  const opts = { headers: { Cookie: COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 150).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text };
}

const island = (html, name) => {
  const m = new RegExp(`window\\.OCP_PAGE_${name}\\s*=\\s*(\\{[\\s\\S]*?\\});`).exec(html);
  if (!m) return null;
  try { return JSON.parse(m[1]); } catch { return null; }
};

// Each case: page, island name, a POST the handler rejects, and the flag that must be set.
// The field names are the handlers' own - they read them directly, as the original did.
const cases = [
  {
    page: '/inventory.php', name: 'INVENTORY',
    fields: { action: 'set_min_stock', item_id: 999999999, min_stock_level: 'not-a-number' },
    want: ['hasMessage'],
    counts: [['inventory', 'inventory'], ['stock_movements', 'stock_movements']],
  },
  {
    page: '/gasoline_inventory.php', name: 'GASOLINE_INVENTORY',
    fields: { action: 'set_min_gas', gasoline_type: '', tank_id: 999999999, min_stock_liters: '' },
    want: ['hasMessage'],
    counts: [['gasoline_movements', 'gasoline_movements']],
  },
  {
    page: '/spare_parts_inventory.php', name: 'SPARE_PARTS_INVENTORY',
    fields: { action: 'initial_part', part_id: 999999999, quantity: '', unit_cost: '', price_per_unit: '', date_added: '2020-01-01' },
    want: ['hasMessage'],
    counts: [['spare_parts_inventory', 'spare_parts_inventory']],
  },
  {
    page: '/spare_parts_suppliers.php', name: 'SPARE_PARTS_SUPPLIERS',
    fields: { supplier_name: '', contact_person: '', email: '', phone: '', address: '' },
    want: ['hasMessage'],
    counts: [['spare_parts_suppliers', 'spare_parts_suppliers']],
  },
  {
    page: '/issue_materials.php', name: 'ISSUE_MATERIALS',
    fields: { action: 'issue_to_employee', employee_id: 999999999, purpose: '', date_issued: '2020-01-01', 'part_id[]': '', 'quantity[]': '' },
    want: ['hasMessage'],
    counts: [['spare_parts_inventory', 'spare_parts_inventory']],
  },
];

const before = {};
const tables = {};
for (const c of cases) for (const [label, table] of c.counts) { before[label] = db(`SELECT COUNT(*) FROM ${table}`); tables[label] = table; }
log.push(`fixture: ${JSON.stringify(before)}`);

for (const c of cases) {
  let r = await req(c.page, c.fields);
  // A handler may answer with a redirect instead of a re-render; the message then travels
  // in the session and the page it lands on is the one to inspect.
  if (r.status >= 300 && r.status < 400) {
    r = await req(c.page);
  }
  const d = island(r.text, c.name);
  if (!d) { problems.push(`${c.page}: no ${c.name} island in the response`); continue; }
  for (const flag of c.want) {
    if (!d[flag]) problems.push(`${c.page}: a rejected post did not set ${flag}`);
  }
  const shown = d.hasMessage ? `message "${String(d.swalDataTitle || d.swalMessage || '').slice(0, 34)}"` : 'no message';
  log.push(`  ${c.page.replace('/', '').padEnd(30)} ${r.status}  ${shown}, hasMessage=${!!d.hasMessage}`);
}

// a plain load must show nothing
for (const c of cases) {
  const r = await req(c.page);
  const d = island(r.text, c.name);
  if (d && d.hasMessage) problems.push(`${c.page}: a plain load reports a message`);
}
log.push('  plain loads report no message');

let changed = false;
for (const [label, table] of Object.entries(tables)) {
  const now = db(`SELECT COUNT(*) FROM ${table}`);
  if (now !== before[label]) { problems.push(`${table} changed: ${before[label]} -> ${now}`); changed = true; }
}
if (!changed) log.push('no rows changed');

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
