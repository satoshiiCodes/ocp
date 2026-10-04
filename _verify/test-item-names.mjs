// Data-driven page test: exercises the actions/endpoint split for item_names.php.
//
// Every operation is additive and removed again, and the row count is compared
// before and after so the test cannot leave data behind.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8182';
const BASE = `http://127.0.0.1:${PORT}`;
const TABLE = 'item_names';
const ACTIONS = '/actions/item_names-actions.php';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const SID = sessions.find(s => s.dept === 'Admin' && s.pos === 'Purchaser')?.sid || sessions[0].sid;
const COOKIE = `PHPSESSID=${SID}`;
const db = (sql) => php('query.php', [sql]).trim();

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;

async function req(url, fields) {
  const opts = { headers: { Cookie: COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`${BASE}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 110).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  let json = null;
  try { json = JSON.parse(text); } catch { /* not JSON, fine */ }
  return { status: res.status, text, json, location: res.headers.get('location') };
}

const count = () => Number(db(`SELECT COUNT(*) FROM ${TABLE}`));
const startCount = count();

// a category and a unit that already exist, so the insert is valid
const categoryId = db('SELECT id FROM items_categories ORDER BY id LIMIT 1');
const unit = db(`SELECT unit_of_measure FROM ${TABLE} WHERE unit_of_measure <> '' LIMIT 1`) || 'pcs';
const stamp = Date.now();

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/item_names.php');
  log.push(`GET /item_names.php -> ${r.status}, table present=${/id="datatablesSimple"/.test(r.text)}, category options=${(r.text.match(/<option value="\d+"/g) || []).length}`);
  if (r.status !== 200) problems.push(`item_names.php HTTP ${r.status}`);
}

// ---------------------------------------------------- 2. add succeeds and redirects
const code = `VER-${stamp}`;
{
  const r = await req('/item_names.php', {
    item_form_action: 'add',
    item_code: code,
    item_name: `Verify Item ${stamp}`,
    category_id: categoryId,
    unit_of_measure: unit,
    min_stock_level: 3,
  });
  log.push(`POST add "${code}" -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`a successful add should redirect, got ${r.status}`);
  if (count() !== startCount + 1) problems.push(`row count ${startCount} -> ${count()}, expected +1`);
  const listed = await req('/item_names.php');
  if (!listed.text.includes(code)) problems.push('the new item is not listed after the redirect');
  else log.push('the new item is listed after the redirect');
  if (!/Item added successfully/.test(listed.text)) problems.push('the success message was not shown');
}

const id = db(`SELECT id FROM ${TABLE} WHERE item_code = '${code}'`);

// ------------------------------------------------------ 3. validation re-renders
{
  const r = await req('/item_names.php', {
    item_form_action: 'add', item_code: '', item_name: '', category_id: '', unit_of_measure: '', min_stock_level: 0,
  });
  log.push(`POST add with empty fields -> ${r.status}`);
  if (r.status !== 200) problems.push(`a validation error should re-render, got ${r.status}`);
  if (!/All fields are required/.test(r.text)) problems.push('the "all fields" message was not shown');
  else log.push('the "all fields" validation message is shown');
  if (count() !== startCount + 1) problems.push('the rejected add changed the row count');
}

{
  const r = await req('/item_names.php', {
    item_form_action: 'add', item_code: `NEG-${stamp}`, item_name: 'negative', category_id: categoryId,
    unit_of_measure: unit, min_stock_level: -5,
  });
  if (!/cannot be negative/.test(r.text)) problems.push('a negative stock level was not rejected');
  else log.push('a negative stock level is rejected');
}

// ------------------------------------------------------------ 4. duplicate rejected
{
  const r = await req('/item_names.php', {
    item_form_action: 'add', item_code: code, item_name: 'duplicate', category_id: categoryId,
    unit_of_measure: unit, min_stock_level: 0,
  });
  if (!/already exists/.test(r.text)) problems.push('a duplicate item code was not rejected');
  else log.push('a duplicate item code is rejected');
  if (count() !== startCount + 1) problems.push('the duplicate add changed the row count');
}

// ------------------------------------------------------------------ 5. view modal
{
  const r = await req('/item_names.php', { view_item_id: id });
  const ok = /id="viewItemModal"/.test(r.text) && r.text.includes(code);
  log.push(`POST view id=${id} -> ${r.status}, modal with the record=${ok}`);
  if (!ok) problems.push('the view modal did not render the requested item');
}

// -------------------------------------------------------------------- 6. update
{
  const renamed = `Verify Item ${stamp} edited`;
  const r = await req(ACTIONS, {
    action: 'update', id, item_code: code, item_name: renamed, category_id: categoryId,
    unit_of_measure: unit, min_stock_level: 7,
  });
  log.push(`AJAX update -> ${r.status} ${JSON.stringify(r.json)}`);
  if (!r.json || !r.json.success) problems.push(`update failed: ${r.text.slice(0, 100)}`);
  else if (db(`SELECT item_name FROM ${TABLE} WHERE id = ${id}`) !== renamed) problems.push('update did not persist');
  else log.push('update persisted');
}

{
  const other = db(`SELECT item_code FROM ${TABLE} WHERE id <> ${id} LIMIT 1`);
  if (other) {
    const r = await req(ACTIONS, { action: 'update', id, item_code: other, item_name: 'x', category_id: categoryId, unit_of_measure: unit, min_stock_level: 0 });
    if (!r.json || r.json.success) problems.push('update accepted a duplicate item code');
    else log.push('update rejects a duplicate item code');
  }
}

// -------------------------------------------------------------------- 7. delete
{
  const r = await req(ACTIONS, { action: 'delete', id });
  log.push(`AJAX delete -> ${r.status} ${JSON.stringify(r.json)}`);
  if (!r.json || !r.json.success) problems.push(`delete failed: ${r.text.slice(0, 100)}`);
  else if (db(`SELECT COUNT(*) FROM ${TABLE} WHERE id = ${id}`) !== '0') problems.push('delete did not remove the row');
  else log.push('delete removed the row');
}

// --------------------------------------------------------------- 8. no leftovers
{
  if (count() !== startCount) {
    problems.push(`the test left ${count() - startCount} row(s) behind`);
    db(`DELETE FROM ${TABLE} WHERE item_code LIKE 'VER-%' OR item_code LIKE 'NEG-%'`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
