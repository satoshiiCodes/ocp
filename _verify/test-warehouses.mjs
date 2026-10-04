// Data-driven test: actions/endpoint split for warehouses.php.
//
// The forms post back to the page, so the checks look for the SweetAlert payload
// the page renders rather than a redirect. Everything created is removed again.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8230';
const TABLE = 'warehouses';

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
const name = `VerifyWarehouse ${stamp}`;

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/warehouses.php');
  log.push(`GET /warehouses.php -> ${r.status}, ${r.text.length} bytes, table=${/id="datatablesSimple"/.test(r.text)}`);
  if (r.status !== 200) problems.push(`warehouses.php HTTP ${r.status}`);
  if (!/id="datatablesSimple"/.test(r.text)) problems.push('the warehouse table is gone');
  // the side menu prints the display name, which now comes from the endpoint
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
}

// ------------------------------------------------------------------- 2. add
// A successful add redirects (the original does the same, to clear the form), so
// the row is the evidence here rather than a message in the response.
{
  const r = await req('/warehouses.php', {
    warehouse_name: name, location: 'Verify Location', capacity: '100', manager: 'Verify Manager', phone: '0900',
  });
  log.push(`POST add "${name}" -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`a successful add should redirect, got ${r.status}`);
  if (count() !== start + 1) problems.push(`rows ${start} -> ${count()}, expected +1`);
  else log.push('  the warehouse was added');
  const listed = await req('/warehouses.php');
  if (!listed.text.includes(name)) problems.push('the new warehouse is not listed');
  else log.push('  the new warehouse is listed');
}

const id = db(`SELECT id FROM ${TABLE} WHERE warehouse_name = '${name}'`);

// ---------------------------------------------------------- 3. duplicate rejected
// The original treats a warehouse as a duplicate only when BOTH the name and the
// location match, so this posts the same pair.
{
  const r = await req('/warehouses.php', {
    warehouse_name: name, location: 'Verify Location', capacity: '100', manager: 'Verify Manager', phone: '0900',
  });
  log.push(`POST the same name and location -> ${r.status}`);
  if (r.status !== 200) problems.push(`a duplicate should re-render, got ${r.status}`);
  if (!/already exists/.test(r.text)) problems.push('a duplicate warehouse was not rejected');
  else log.push('  a duplicate name+location is rejected');
  if (count() !== start + 1) problems.push('the duplicate add changed the row count');
}

// ------------------------------------------------- 4. validation keeps the input
{
  const r = await req('/warehouses.php', {
    warehouse_name: '', location: 'Kept Location', capacity: '5', manager: 'Kept', phone: '1',
  });
  log.push(`POST with an empty name -> ${r.status}`);
  if (r.status !== 200) problems.push(`a validation error should re-render, got ${r.status}`);
  if (!/name and location are required/.test(r.text)) problems.push('the required-fields message was not shown');
  else log.push('  the required-fields message is shown');
  if (!/Kept Location/.test(r.text)) problems.push('the rejected input was not kept in the form');
  else log.push('  the rejected input is kept');
  if (count() !== start + 1) problems.push('the rejected add changed the row count');
}

// ------------------------------------------------------------------ 5. update
{
  const renamed = `${name} edited`;
  const r = await req('/warehouses.php', {
    update_id: id, warehouse_name: renamed, location: 'Edited Location', capacity: '200',
    manager: 'Edited Manager', phone: '0999',
  });
  log.push(`POST update id=${id} -> ${r.status}`);
  if (!/Warehouse updated successfully/.test(r.text)) problems.push('the update success message was not shown');
  else log.push('  the update success message is shown');
  const stored = db(`SELECT warehouse_name FROM ${TABLE} WHERE id = ${id}`);
  if (stored !== renamed) problems.push(`update did not persist: "${stored}"`);
  else log.push('  update persisted');
}

// ------------------------------------------------------------------ 6. delete
{
  const r = await req('/warehouses.php', { delete_id: id });
  log.push(`POST delete id=${id} -> ${r.status}`);
  if (!/Warehouse deleted successfully/.test(r.text)) problems.push('the delete success message was not shown');
  else log.push('  the delete success message is shown');
  if (db(`SELECT COUNT(*) FROM ${TABLE} WHERE id = ${id}`) !== '0') problems.push('delete did not remove the row');
  else log.push('  delete removed the row');
}

// ------------------------------------------------------------- 7. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} row(s) behind`);
    db(`DELETE FROM ${TABLE} WHERE warehouse_name LIKE 'VerifyWarehouse %'`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
