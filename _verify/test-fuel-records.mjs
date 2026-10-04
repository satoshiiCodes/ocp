// Data-driven test: actions/endpoint split for fuel_records.php.
//
// This page is read-only: a POST carrying a vehicle id renders that vehicle's
// fuel records, and anything else redirects back to the vehicle list.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8220';

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
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 130).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

// ---------------------------------------------------------- 1. no id redirects
{
  const r = await req('/fuel_records.php');
  log.push(`GET without a vehicle -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`a GET with no vehicle should redirect, got ${r.status}`);
  if (!/vehicles\.php/.test(r.location || '')) problems.push(`it should redirect to vehicles.php, got ${r.location}`);
  else log.push('it redirects to vehicles.php');
}

{
  const r = await req('/fuel_records.php', { something_else: '1' });
  log.push(`POST without vehicle_id -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`a POST with no vehicle_id should redirect, got ${r.status}`);
}

// ------------------------------------------------------ 2. a real vehicle works
{
  const id = db('SELECT id FROM vehicles ORDER BY id LIMIT 1');
  const expected = Number(db(`SELECT COUNT(*) FROM gasoline_movements WHERE vehicle_id = ${id}`));

  const r = await req('/fuel_records.php', { vehicle_id: id });
  log.push(`POST vehicle_id=${id} -> ${r.status}, ${r.text.length} bytes (${expected} movements exist)`);
  if (r.status !== 200) problems.push(`a real vehicle should render, got ${r.status}`);

  // The page's movement query joins suppliers on s.name, a column that does not
  // exist (the table has supplier_name). That is true of the original too, so the
  // page has always taken its own error path: it shows the message and no rows.
  // This test pins that behaviour so a future change to it is visible.
  const errored = /Error fetching vehicle details/.test(r.text);
  const noRows = /No Fuel Records Found/.test(r.text);
  log.push(`  query fails as it does in the original: ${errored}; empty-state shown: ${noRows}`);
  if (!errored) problems.push('the supplier join no longer fails - the original query has been changed');
  if (!/View Vehicle/.test(r.text)) problems.push('the page did not render its "View Vehicle" markup');
}

// -------------------------------------------------------- 3. an unknown id is reported
{
  const r = await req('/fuel_records.php', { vehicle_id: 999999999 });
  log.push(`POST an unknown vehicle_id -> ${r.status}`);
  if (r.status !== 200) problems.push(`an unknown vehicle should render a message, got ${r.status}`);
  if (!/Vehicle not found/.test(r.text)) problems.push('the "Vehicle not found" message was not shown');
  else log.push('the "Vehicle not found" message is shown');
}

// -------------------------------------------------- 4. the actions file alone is safe
{
  const r = await req('/actions/fuel_records-actions.php');
  log.push(`GET the actions file directly -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`the actions file should not render on its own, got ${r.status}`);
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
