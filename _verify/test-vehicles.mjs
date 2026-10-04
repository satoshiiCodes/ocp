// Data-driven test: actions/endpoint split for vehicles.php.
//
// Covers the three paths this page uses: add (POST), edit (POST action=edit_vehicle)
// and delete (GET ?delete_id=). Every row it creates is removed again.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8189';
const BASE = `http://127.0.0.1:${PORT}`;
const TABLE = 'vehicles';

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
  const res = await fetch(`${BASE}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 120).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

const count = () => Number(db(`SELECT COUNT(*) FROM ${TABLE}`));
const start = count();
const stamp = Date.now();
const name = `VerifyVehicle ${stamp}`;
const plate = `VP-${stamp}`;

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/vehicles.php');
  log.push(`GET /vehicles.php -> ${r.status}, table=${/datatablesSimple/.test(r.text)}, add form=${/addVehicleForm|Vehicle/.test(r.text)}`);
  if (r.status !== 200) problems.push(`vehicles.php HTTP ${r.status}`);
}

// ------------------------------------------------------------ 2. add succeeds
{
  const r = await req('/vehicles.php', {
    vehicle_name: name, plate_number: plate, fuel_type: 'diesel',
    description: 'created by the verification run', is_active: '1',
  });
  log.push(`POST add "${name}" -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`a successful add should redirect, got ${r.status}`);
  if (count() !== start + 1) problems.push(`rows ${start} -> ${count()}, expected +1`);
  const listed = await req('/vehicles.php');
  if (!listed.text.includes(name)) problems.push('the new vehicle is not listed');
  else log.push('the new vehicle is listed');
  if (!/Vehicle added successfully/.test(listed.text)) problems.push('the add success message was not shown');
  else log.push('the add success message is shown');
}

const id = db(`SELECT id FROM ${TABLE} WHERE plate_number = '${plate}'`);

// ------------------------------------------- 3. validation error reopens modal
{
  const r = await req('/vehicles.php', { vehicle_name: '', plate_number: '', fuel_type: 'diesel' });
  log.push(`POST add with empty fields -> ${r.status}`);
  if (r.status !== 200) problems.push(`a validation error should re-render, got ${r.status}`);
  if (!/Vehicle name and plate number are required/.test(r.text)) problems.push('the validation message was not shown');
  else log.push('the validation message is shown');
  if (count() !== start + 1) problems.push('the rejected add changed the row count');
}

// ---------------------------------------------------------- 4. duplicate rejected
{
  const r = await req('/vehicles.php', { vehicle_name: 'dupe', plate_number: plate, fuel_type: 'diesel' });
  if (!/already exists/.test(r.text)) problems.push('a duplicate plate number was not rejected');
  else log.push('a duplicate plate number is rejected');
  if (count() !== start + 1) problems.push('the duplicate add changed the row count');
}

// ------------------------------------------------------------------ 5. edit
{
  const renamed = `${name} edited`;
  const r = await req('/vehicles.php', {
    action: 'edit_vehicle', vehicle_id: id, vehicle_name: renamed,
    plate_number: plate, fuel_type: 'gasoline', description: 'edited', is_active: '1',
  });
  log.push(`POST edit id=${id} -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`a successful edit should redirect, got ${r.status}`);
  const stored = db(`SELECT vehicle_name FROM ${TABLE} WHERE id = ${id}`);
  if (stored !== renamed) problems.push(`edit did not persist: "${stored}"`);
  else log.push('edit persisted');
}

{
  const other = db(`SELECT plate_number FROM ${TABLE} WHERE id <> ${id} LIMIT 1`);
  if (other) {
    const r = await req('/vehicles.php', {
      action: 'edit_vehicle', vehicle_id: id, vehicle_name: 'x', plate_number: other, fuel_type: 'diesel',
    });
    if (!/already exists/.test(r.text)) problems.push('edit accepted a duplicate plate number');
    else log.push('edit rejects a duplicate plate number');
  }
}

// ------------------------------------------------------- 6. delete (GET path)
{
  const r = await req(`/vehicles.php?delete_id=${id}`);
  log.push(`GET ?delete_id=${id} -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`delete should redirect, got ${r.status}`);
  if (db(`SELECT COUNT(*) FROM ${TABLE} WHERE id = ${id}`) !== '0') problems.push('delete did not remove the row');
  else log.push('delete removed the row');
  const after = await req('/vehicles.php');
  if (!/Vehicle deleted successfully/.test(after.text)) problems.push('the delete success message was not shown');
  else log.push('the delete success message is shown');
}

// ------------------------------------------------------------- 7. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} row(s) behind`);
    db(`DELETE FROM ${TABLE} WHERE vehicle_name LIKE 'VerifyVehicle %'`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
