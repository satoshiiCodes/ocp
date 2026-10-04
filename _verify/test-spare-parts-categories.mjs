// Data-driven page test: actions/endpoint split for items_categories.php.
//
// Every operation is additive and removed again; the row count is compared
// before and after so the test leaves no data behind.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8184';
const BASE = `http://127.0.0.1:${PORT}`;
const TABLE = 'spare_parts_categories';

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
  return { status: res.status, text, location: res.headers.get('location') };
}

const count = () => Number(db(`SELECT COUNT(*) FROM ${TABLE}`));
const start = count();
const stamp = Date.now();
const name = `VerifySpareCat ${stamp}`;

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/spare_parts_categories.php');
  log.push(`GET /spare_parts_categories.php -> ${r.status}, table=${/id="datatablesSimple"/.test(r.text)}, rows=${(r.text.match(/<tr>/g) || []).length}`);
  if (r.status !== 200) problems.push(`items_categories.php HTTP ${r.status}`);
}

// ------------------------------------------------------------------- 2. add
{
  const r = await req('/spare_parts_categories.php', { category_name: name, description: 'created by the verification run' });
  log.push(`POST add "${name}" -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`add should redirect, got ${r.status}`);
  if (count() !== start + 1) problems.push(`row count ${start} -> ${count()}, expected +1`);
  const listed = await req('/spare_parts_categories.php');
  if (!listed.text.includes(name)) problems.push('the new category is not listed after the redirect');
  else log.push('the new category is listed');
  if (!/Category added successfully/.test(listed.text)) problems.push('the success message was not shown');
  else log.push('the success message is shown');
}

const id = db(`SELECT id FROM ${TABLE} WHERE category_name = '${name}'`);

// ------------------------------------------------------- 3. validation message
{
  const r = await req('/spare_parts_categories.php', { category_name: '', description: 'x' });
  log.push(`POST add with an empty name -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`the empty-name case should still redirect, got ${r.status}`);
  const after = await req('/spare_parts_categories.php');
  if (!/Category name is required/.test(after.text)) problems.push('the required-name message was not shown');
  else log.push('the required-name message is shown');
  if (count() !== start + 1) problems.push('the rejected add changed the row count');
}

// ---------------------------------------------------------- 4. duplicate rejected
{
  const r = await req('/spare_parts_categories.php', { category_name: name, description: 'dupe' });
  const after = await req('/spare_parts_categories.php');
  if (!/already exists/.test(after.text)) problems.push('a duplicate name was not rejected');
  else log.push('a duplicate name is rejected');
  if (count() !== start + 1) problems.push('the duplicate add changed the row count');
  void r;
}

// ------------------------------------------------------------------ 5. edit
{
  const renamed = `${name} edited`;
  const r = await req('/spare_parts_categories.php', { edit_id: id, category_name: renamed, description: 'edited' });
  log.push(`POST edit id=${id} -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`edit should redirect, got ${r.status}`);
  const stored = db(`SELECT category_name FROM ${TABLE} WHERE id = ${id}`);
  if (stored !== renamed) problems.push(`edit did not persist: "${stored}"`);
  else log.push('edit persisted');
}

// ------------------------------------------------------------------ 6. delete
{
  const r = await req('/spare_parts_categories.php', { delete_id: id });
  log.push(`POST delete id=${id} -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`delete should redirect, got ${r.status}`);
  if (db(`SELECT COUNT(*) FROM ${TABLE} WHERE id = ${id}`) !== '0') problems.push('delete did not remove the row');
  else log.push('delete removed the row');
}

// ------------------------------------------------------------- 7. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} row(s) behind`);
    db(`DELETE FROM ${TABLE} WHERE category_name LIKE 'VerifySpareCat %'`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
