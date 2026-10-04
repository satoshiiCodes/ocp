// Data-driven test: actions/endpoint split for gasoline_suppliers.php.
//
// The forms post back to the page, so the checks follow the $swal_* message the
// page renders. Everything created is removed again.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8231';
const TABLE = 'gasoline_suppliers';

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
const name = `VerifyGasSupplier ${stamp}`;
const fields = (n) => ({
  supplier_name: n, supplier_type: 'Regular', contact_person: 'Verify Contact',
  phone: '0900', email: `gs${stamp}@example.test`, address: 'Verify Address', is_active: '1',
});

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/gasoline_suppliers.php');
  log.push(`GET /gasoline_suppliers.php -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`gasoline_suppliers.php HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
}

// ------------------------------------------------------------------- 2. add
{
  const r = await req('/gasoline_suppliers.php', fields(name));
  log.push(`POST add "${name}" -> ${r.status} ${r.location || ''}`);
  if (count() !== start + 1) problems.push(`rows ${start} -> ${count()}, expected +1`);
  else log.push('  the supplier was added');
  const listed = await req('/gasoline_suppliers.php');
  if (!listed.text.includes(name)) problems.push('the new supplier is not listed');
  else log.push('  the new supplier is listed');
  void r;
}

const id = db(`SELECT id FROM ${TABLE} WHERE supplier_name = '${name}'`);

// ------------------------------------------------------- 3. validation message
{
  const r = await req('/gasoline_suppliers.php', { ...fields(''), supplier_name: '' });
  log.push(`POST with an empty name -> ${r.status}`);
  if (r.status !== 200) problems.push(`a validation error should re-render, got ${r.status}`);
  if (!/required|cannot be empty|Please enter/i.test(r.text)) problems.push('no validation message was shown');
  else log.push('  a validation message is shown');
  if (count() !== start + 1) problems.push('the rejected add changed the row count');
}

// ------------------------------------------------------------------ 4. update
{
  const renamed = `${name} edited`;
  const r = await req('/gasoline_suppliers.php', { update_supplier: '1', supplier_id: id, ...fields(renamed) });
  log.push(`POST update id=${id} -> ${r.status} ${r.location || ''}`);
  const stored = db(`SELECT supplier_name FROM ${TABLE} WHERE id = ${id}`);
  if (stored !== renamed) problems.push(`update did not persist: "${stored}"`);
  else log.push('  update persisted');
  void r;
}

// ------------------------------------------------------------------ 5. delete
{
  const r = await req('/gasoline_suppliers.php', { delete_supplier: '1', supplier_id: id });
  log.push(`POST delete id=${id} -> ${r.status} ${r.location || ''}`);
  if (db(`SELECT COUNT(*) FROM ${TABLE} WHERE id = ${id}`) !== '0') problems.push('delete did not remove the row');
  else log.push('  delete removed the row');
  void r;
}

// ------------------------------------------------------------- 6. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} row(s) behind`);
    db(`DELETE FROM ${TABLE} WHERE supplier_name LIKE 'VerifyGasSupplier %'`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
