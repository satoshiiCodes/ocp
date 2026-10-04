// Data-driven test: actions/endpoint split for subcon_name.php.
//
// The page reports errors by re-rendering with $swal_data and the submitted
// values, so the checks look for the SweetAlert payload and the repopulated
// form fields rather than a redirect.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8186';
const BASE = `http://127.0.0.1:${PORT}`;
const TABLE = 'subcons';
const NAME_FIELD = 'subcon_name';

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
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 120).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text };
}

const count = () => Number(db(`SELECT COUNT(*) FROM ${TABLE}`));
const start = count();
const stamp = Date.now();
const name = `VerifySubcon ${stamp}`;
const fields = (n) => ({
  subcon_name: n, contact_person: 'Verification Contact',
  phone: '0900-000-0000', email: 'verify@example.test', address: 'Verification Address',
});

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/subcon_name.php');
  log.push(`GET /subcon_name.php -> ${r.status}, table=${/id="datatablesSimple"/.test(r.text)}, rows=${(r.text.match(/<tr>/g) || []).length}`);
  if (r.status !== 200) problems.push(`subcon_name.php HTTP ${r.status}`);
}

// ------------------------------------------------------------------- 2. add
{
  const r = await req('/subcon_name.php', fields(name));
  log.push(`POST add "${name}" -> ${r.status}`);
  if (r.status !== 200) problems.push(`add should re-render, got ${r.status}`);
  if (count() !== start + 1) problems.push(`row count ${start} -> ${count()}, expected +1`);
  if (!/Subcontractor added successfully/.test(r.text)) problems.push('the add success message was not shown');
  else log.push('the add success message is shown');
  const listed = await req('/subcon_name.php');
  if (!listed.text.includes(name)) problems.push('the new subcontractor is not listed');
  else log.push('the new subcontractor is listed');
}

const id = db(`SELECT id FROM ${TABLE} WHERE ${NAME_FIELD} = '${name}'`);

// ------------------------------------------- 3. validation error repopulates
{
  const r = await req('/subcon_name.php', { ...fields(''), subcon_name: '' });
  log.push(`POST add with an empty name -> ${r.status}`);
  if (!/Subcontractor name is required/.test(r.text)) problems.push('the required-name message was not shown');
  else log.push('the required-name message is shown');
  if (!/Verification Address/.test(r.text)) problems.push('the add form was not repopulated with the submitted values');
  else log.push('the add form is repopulated');
  if (count() !== start + 1) problems.push('the rejected add changed the row count');
}

// ---------------------------------------------------------- 4. duplicate rejected
{
  const r = await req('/subcon_name.php', fields(name));
  if (!/already exists/.test(r.text)) problems.push('a duplicate name was not rejected');
  else log.push('a duplicate name is rejected');
  if (count() !== start + 1) problems.push('the duplicate add changed the row count');
}

// ------------------------------------------------------------------ 5. edit
{
  const renamed = `${name} edited`;
  const r = await req('/subcon_name.php', { edit_id: id, ...fields(renamed) });
  log.push(`POST edit id=${id} -> ${r.status}`);
  if (!/Subcontractor updated successfully/.test(r.text)) problems.push('the edit success message was not shown');
  else log.push('the edit success message is shown');
  const stored = db(`SELECT ${NAME_FIELD} FROM ${TABLE} WHERE id = ${id}`);
  if (stored !== renamed) problems.push(`edit did not persist: "${stored}"`);
  else log.push('edit persisted');
  // the edit form must reopen with the values, which the page decides from $_POST
  if (!/edit_subcon_name/.test(r.text)) problems.push('the edit repopulation path is gone');
}

// ------------------------------------------------------------- 6. edit rejects dup
{
  const other = db(`SELECT ${NAME_FIELD} FROM ${TABLE} WHERE id <> ${id} LIMIT 1`);
  if (other) {
    const r = await req('/subcon_name.php', { edit_id: id, ...fields(other) });
    if (!/already exists/.test(r.text)) problems.push('edit accepted a duplicate name');
    else log.push('edit rejects a duplicate name');
  }
}

// ------------------------------------------------------------------ 7. delete
{
  const r = await req('/subcon_name.php', { delete_id: id });
  log.push(`POST delete id=${id} -> ${r.status}`);
  if (!/Subcontractor deleted successfully/.test(r.text)) problems.push('the delete success message was not shown');
  else log.push('the delete success message is shown');
  if (db(`SELECT COUNT(*) FROM ${TABLE} WHERE id = ${id}`) !== '0') problems.push('delete did not remove the row');
  else log.push('delete removed the row');
}

// ------------------------------------------------------------- 8. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} row(s) behind`);
    db(`DELETE FROM ${TABLE} WHERE ${NAME_FIELD} LIKE 'VerifySubcon %'`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
