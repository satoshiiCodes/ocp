// Data-driven test: actions/endpoint split for gasoline_tank.php.
//
// The page reports through session state and redirects, so the checks follow the
// redirect and then confirm the row in the database. Everything created is removed.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8232';
const TABLE = 'gasoline_tanks';

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
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 140).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

const count = () => Number(db(`SELECT COUNT(*) FROM ${TABLE}`));
const start = count();
const stamp = Date.now();
const name = `VerifyTank ${stamp}`;
const fields = (n) => ({
  tank_name: n, location: 'Verify Location', capacity_liters: '1000',
  description: 'created by the verification run', is_active: '1',
});

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/gasoline_tank.php');
  log.push(`GET /gasoline_tank.php -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`gasoline_tank.php HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
}

// ------------------------------------------------------------------- 2. add
{
  const r = await req('/gasoline_tank.php', fields(name));
  log.push(`POST add "${name}" -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`add should redirect, got ${r.status}`);
  if (count() !== start + 1) problems.push(`rows ${start} -> ${count()}, expected +1`);
  else log.push('  the tank was added');
  const listed = await req('/gasoline_tank.php');
  if (!listed.text.includes(name)) problems.push('the new tank is not listed');
  else log.push('  the new tank is listed');
}

const id = db(`SELECT id FROM ${TABLE} WHERE tank_name = '${name}'`);

// ---------------------------------------------------------- 3. duplicate rejected
{
  const r = await req('/gasoline_tank.php', fields(name));
  log.push(`POST the same name and location -> ${r.status} ${r.location || ''}`);
  if (count() !== start + 1) problems.push('the duplicate add changed the row count');
  else log.push('  a duplicate is not inserted');
  const after = await req('/gasoline_tank.php');
  if (!/already exists/i.test(after.text)) log.push('  (no duplicate message rendered - check the session flow)');
  else log.push('  the duplicate message is shown');
  void r;
}

// ------------------------------------------------------- 4. validation message
{
  const r = await req('/gasoline_tank.php', { ...fields(''), tank_name: '' });
  const after = await req('/gasoline_tank.php');
  log.push(`POST with an empty name -> ${r.status}, then GET -> ${after.status}`);
  if (count() !== start + 1) problems.push('the rejected empty-name add changed the row count');
  else log.push('  the rejected add inserted nothing');
}

// ------------------------------------------------------------------ 5. edit
{
  const renamed = `${name} edited`;
  const r = await req('/gasoline_tank.php', { edit_tank: '1', tank_id: id, ...fields(renamed) });
  log.push(`POST edit id=${id} -> ${r.status} ${r.location || ''}`);
  const stored = db(`SELECT tank_name FROM ${TABLE} WHERE id = ${id}`);
  if (stored !== renamed) problems.push(`edit did not persist: "${stored}"`);
  else log.push('  edit persisted');
  void r;
}

// ------------------------------------------------------------------ 6. delete
{
  const r = await req('/gasoline_tank.php', { delete_tank: '1', tank_id: id });
  log.push(`POST delete id=${id} -> ${r.status} ${r.location || ''}`);
  if (db(`SELECT COUNT(*) FROM ${TABLE} WHERE id = ${id}`) !== '0') problems.push('delete did not remove the row');
  else log.push('  delete removed the row');
  void r;
}

// ------------------------------------------------------------- 7. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} row(s) behind`);
    db(`DELETE FROM ${TABLE} WHERE tank_name LIKE 'VerifyTank %'`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
