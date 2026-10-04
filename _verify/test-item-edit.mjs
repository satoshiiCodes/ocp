// Exercises every way the item_names edit can fail, checking each answers clean JSON.
//
//   node test-item-edit.mjs
//
// The script turns any non-JSON answer into "An error occurred while updating the item",
// so a PHP notice printed before the JSON is indistinguishable from a real failure. Each
// case here asserts the answer parses.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8341';
const ACTIONS = '/actions/item_names-actions.php';
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const db = (s) => php('query.php', [s]).trim();
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];

async function post(fields, cookie = COOKIE) {
  const body = new URLSearchParams();
  for (const [k, v] of Object.entries(fields)) body.append(k, v);
  const res = await fetch(`http://127.0.0.1:${PORT}${ACTIONS}`, {
    method: 'POST', body, headers: cookie ? { Cookie: cookie } : {},
  });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  let json = null;
  try { json = JSON.parse(text); } catch { /* reported */ }
  return { status: res.status, text, json };
}

// a real row to work with
const id = db('SELECT id FROM item_names ORDER BY id LIMIT 1');
const rows = [];
{
  const r = await fetch(`http://127.0.0.1:${PORT}/item_names.php`, { headers: { Cookie: COOKIE } });
  const html = Buffer.from(await r.arrayBuffer()).toString('utf8');
  const btn = /data-id="(\d+)"[\s\S]{0,600}?data-min-stock="([^"]*)"/.exec(html);
  rows.push(btn);
}
const base = {
  action: 'update', id,
  item_code: db(`SELECT item_code FROM item_names WHERE id = ${id}`),
  item_name: db(`SELECT item_name FROM item_names WHERE id = ${id}`),
  category_id: db(`SELECT category_id FROM item_names WHERE id = ${id}`),
  unit_of_measure: db(`SELECT unit_of_measure FROM item_names WHERE id = ${id}`),
  min_stock_level: db(`SELECT min_stock_level FROM item_names WHERE id = ${id}`),
};
log.push(`editing item ${id} (${base.item_code} / ${base.item_name})`);

const cases = [
  ['a valid edit', base],
  ['no item code', { ...base, item_code: '' }],
  ['no item name', { ...base, item_name: '' }],
  ['no category', { ...base, category_id: '' }],
  ['no unit', { ...base, unit_of_measure: '' }],
  ['no id', { ...base, id: '0' }],
  ['a negative stock level', { ...base, min_stock_level: '-5' }],
  ['a stock level that is not a number', { ...base, min_stock_level: 'abc' }],
  ['a duplicate item code', { ...base, item_code: db(`SELECT item_code FROM item_names WHERE id != ${id} ORDER BY id LIMIT 1`) }],
  ['an unknown id', { ...base, id: '999999999' }],
  ['no fields at all', { action: 'update' }],
];

for (const [what, fields] of cases) {
  const r = await post(fields);
  if (!r.json) {
    problems.push(`${what}: the answer is not JSON -> ${JSON.stringify(r.text.slice(0, 120))}`);
    continue;
  }
  if (typeof r.json.success !== 'boolean') {
    problems.push(`${what}: the JSON has no success flag -> ${JSON.stringify(r.json).slice(0, 100)}`);
    continue;
  }
  log.push(`  ${what.padEnd(34)} ${r.json.success ? 'ok' : 'rejected'}: ${String(r.json.message || '').slice(0, 46)}`);
}

// the session guard
{
  const r = await post(base, null);
  const ok = r.json && r.json.success === false;
  log.push(`  ${'no session'.padEnd(34)} ${ok ? 'refused: ' + r.json.message : 'NOT REFUSED'}`);
  if (!ok) problems.push('an unauthenticated edit was not refused with JSON');
}

// the row must be unchanged
{
  const now = db(`SELECT CONCAT(item_code, '|', item_name, '|', category_id, '|', unit_of_measure, '|', min_stock_level) FROM item_names WHERE id = ${id}`);
  const was = [base.item_code, base.item_name, base.category_id, base.unit_of_measure, base.min_stock_level].join('|');
  if (now !== was) problems.push(`the row changed: ${was} -> ${now}`);
  else log.push('  the row is unchanged');
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
