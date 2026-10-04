// Exercises the AJAX actions of expenses_type through actions/expenses_type-actions.php,
// the way the page's JavaScript calls them.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8181';
const BASE = `http://127.0.0.1:${PORT}`;

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const SID = sessions.find(s => s.dept === 'Admin' && s.pos === 'Purchaser')?.sid || sessions[0].sid;
const COOKIE = `PHPSESSID=${SID}`;
const ACTIONS = '/actions/expenses_type-actions.php';

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const db = (sql) => php('query.php', [sql]).trim();

async function postJson(url, fields) {
  const res = await fetch(`${BASE}${url}`, {
    method: 'POST', body: new URLSearchParams(fields), headers: { Cookie: COOKIE }, redirect: 'manual',
  });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  let json = null;
  try { json = JSON.parse(text); } catch { /* reported */ }
  return { status: res.status, json, text };
}

// a record to work with, inserted directly so the test does not depend on the add form
const name = `VerifyAjax ${Date.now()}`;
db(`INSERT INTO expenses_type (expense_name, description) VALUES ('${name}', 'temp')`);
const id = db(`SELECT id FROM expenses_type WHERE expense_name = '${name}'`);
log.push(`fixture: ${name} (id ${id})`);

// --- update ------------------------------------------------------------------
{
  const renamed = `${name} edited`;
  const r = await postJson(ACTIONS, { action: 'update', id, expense_name: renamed, description: 'updated' });
  log.push(`update -> ${r.status} ${JSON.stringify(r.json)}`);
  if (!r.json) problems.push(`update did not answer JSON: ${r.text.slice(0, 120)}`);
  else if (!r.json.success) problems.push(`update reported failure: ${r.json.message}`);
  const stored = db(`SELECT expense_name FROM expenses_type WHERE id = ${id}`);
  if (stored !== renamed) problems.push(`update did not persist: stored "${stored}"`);
  else log.push('update persisted');
}

// --- update rejects a duplicate name ----------------------------------------
{
  const other = db('SELECT expense_name FROM expenses_type WHERE id != ' + id + ' LIMIT 1');
  if (other) {
    const r = await postJson(ACTIONS, { action: 'update', id, expense_name: other, description: 'x' });
    log.push(`update to an existing name -> ${JSON.stringify(r.json)}`);
    if (!r.json || r.json.success) problems.push('update accepted a duplicate name');
  }
}

// --- update rejects bad input ------------------------------------------------
{
  const r = await postJson(ACTIONS, { action: 'update', id: 0, expense_name: '', description: '' });
  log.push(`update with bad input -> ${JSON.stringify(r.json)}`);
  if (!r.json || r.json.success) problems.push('update accepted bad input');
}

// --- delete ------------------------------------------------------------------
{
  const r = await postJson(ACTIONS, { action: 'delete', id });
  log.push(`delete -> ${r.status} ${JSON.stringify(r.json)}`);
  if (!r.json || !r.json.success) problems.push(`delete reported failure: ${r.text.slice(0, 120)}`);
  const left = db(`SELECT COUNT(*) FROM expenses_type WHERE id = ${id}`);
  if (Number(left) !== 0) problems.push('delete did not remove the row');
  else log.push('delete removed the row');
}

// --- delete rejects bad input ------------------------------------------------
{
  const r = await postJson(ACTIONS, { action: 'delete', id: 0 });
  log.push(`delete with a bad id -> ${JSON.stringify(r.json)}`);
  if (!r.json || r.json.success) problems.push('delete accepted a bad id');
}

// --- an unknown action leaves the page to deal with the request --------------
{
  const r = await postJson(ACTIONS, { action: 'nonsense' });
  log.push(`unknown action -> ${r.status}, ${r.text.length} bytes (page render expected)`);
  if (r.status !== 200) problems.push(`an unknown action should fall through to the page, got ${r.status}`);
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
