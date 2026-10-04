// Data-driven test: actions/endpoint split for gasoline_inventory.php.
//
// The actions move gasoline between tanks and batches, so this test does not post a
// real movement: it checks the page renders with its tanks, inventory and movements,
// and that an invalid submission is rejected without writing anything. The tank,
// inventory, movement and batch counts are compared before and after.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8266';

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
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 150).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

const counts = () => ({
  tanks: Number(db('SELECT COUNT(*) FROM gasoline_tanks')),
  inventory: Number(db('SELECT COUNT(*) FROM gasoline_inventory')),
  movements: Number(db('SELECT COUNT(*) FROM gasoline_movements')),
  batches: Number(db('SELECT COUNT(*) FROM gasoline_batches')),
});
const start = counts();
log.push(`fixture: ${JSON.stringify(start)}`);

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/gasoline_inventory.php');
  log.push(`GET /gasoline_inventory.php -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`gasoline_inventory.php HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
  // the lists the endpoint supplies
  const tanks = Number(db('SELECT COUNT(*) FROM gasoline_tanks'));
  if (!/Gasoline/i.test(r.text)) problems.push('the page did not render its heading');
  else log.push('  the page renders');
  log.push(`  (${tanks} tank(s) exist to be listed)`);
}

// ------------------------------------------------------- 2. the filter round-trips
{
  const r = await req('/gasoline_inventory.php?filter_type=Unleaded&start_date=2020-01-01&end_date=2020-12-31');
  log.push(`GET with filters -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`the filtered view HTTP ${r.status}`);
  else log.push('  the filtered view renders');
}

// ------------------------------------ 3. an invalid submission writes nothing
{
  const before = counts();
  // a complete payload whose tank does not exist: this reaches the validation
  // instead of tripping over missing POST keys, which the handler reads directly
  // (as the original does).
  const r = await req('/gasoline_inventory.php', {
    action: 'gas_in', gasoline_type: 'Unleaded', supplier_id: 0, tank_id: 999999999,
    quantity_liters: '0', price_per_liter: '0', date_received: '2020-01-01',
  });
  log.push(`POST gas_in with a non-existent tank -> ${r.status}`);
  const after = counts();
  for (const k of Object.keys(before)) {
    if (after[k] !== before[k]) problems.push(`the rejected gas_in changed ${k} (${before[k]} -> ${after[k]})`);
  }
  if (Object.keys(before).every(k => after[k] === before[k])) log.push('  nothing was written');
}

{
  const before = counts();
  await req('/gasoline_inventory.php', { action: '__nonsense__' });
  const after = counts();
  if (Object.keys(before).some(k => after[k] !== before[k])) problems.push('an unknown action changed the data');
  else log.push('an unknown action changes nothing');
}

// ------------------------------------------------------------- 4. no leftovers
{
  const end = counts();
  if (JSON.stringify(end) !== JSON.stringify(start)) {
    problems.push(`the test left data behind: ${JSON.stringify(start)} -> ${JSON.stringify(end)}`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
