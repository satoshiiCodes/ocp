// Data-driven test for the two "view a record" detail pages.
//
// Both are read-only: they are opened with a record id (POSTed from a list, or on
// the query string) and redirect back to their list without one.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8222';

const CASES = [
  { page: 'view_vehicle.php', table: 'vehicles', nameCol: 'vehicle_name', key: 'vehicle_id', list: 'vehicles.php', notFound: 'Vehicle not found', heading: 'View Vehicle' },
  { page: 'view_heavy_equipment.php', table: 'equipment', nameCol: 'equipment_name', key: 'equipment_id', list: 'heavy_equipment.php', notFound: 'Equipment not found', heading: 'View Equipment|View Heavy Equipment' },
];

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

for (const c of CASES) {
  log.push(`--- ${c.page}`);

  // 1. no id at all redirects to the list
  {
    const r = await req(`/${c.page}`);
    if (r.status !== 302) problems.push(`${c.page}: a GET with no id should redirect, got ${r.status}`);
    if (!(r.location || '').includes(c.list)) problems.push(`${c.page}: should redirect to ${c.list}, got ${r.location}`);
    else log.push(`  no id -> 302 ${c.list}`);
  }

  // 2. a POST without the key also redirects
  {
    const r = await req(`/${c.page}`, { unrelated: '1' });
    if (r.status !== 302) problems.push(`${c.page}: a POST with no ${c.key} should redirect, got ${r.status}`);
    else log.push(`  POST with no ${c.key} -> 302`);
  }

  // 3. a real record renders, by POST and by ?id=
  const id = db(`SELECT id FROM ${c.table} ORDER BY id LIMIT 1`);
  const name = db(`SELECT ${c.nameCol} FROM ${c.table} WHERE id = ${id}`);
  {
    const r = await req(`/${c.page}`, { [c.key]: id });
    log.push(`  POST ${c.key}=${id} -> ${r.status}, ${r.text.length} bytes (record "${name}")`);
    if (r.status !== 200) problems.push(`${c.page}: a real record should render, got ${r.status}`);
    if (!r.text.includes(name)) problems.push(`${c.page}: the record name is not shown`);
    else log.push('  the record name is shown');
    if (!new RegExp(c.heading).test(r.text)) problems.push(`${c.page}: the page heading is missing`);
  }
  {
    const r = await req(`/${c.page}?id=${id}`);
    log.push(`  GET ?id=${id} -> ${r.status}, ${r.text.length} bytes`);
    if (r.status !== 200) problems.push(`${c.page}: ?id= should render, got ${r.status}`);
    if (!r.text.includes(name)) problems.push(`${c.page}: ?id= did not render the record`);
  }

  // 4. an id that does not exist is reported, not crashed
  {
    const r = await req(`/${c.page}`, { [c.key]: 999999999 });
    if (r.status !== 200) problems.push(`${c.page}: an unknown id should render a message, got ${r.status}`);
    else if (!new RegExp(c.notFound).test(r.text)) problems.push(`${c.page}: the "${c.notFound}" message was not shown`);
    else log.push(`  unknown id -> "${c.notFound}" shown`);
  }

  // 5. the actions file on its own does not render
  {
    const r = await req(`/actions/${c.page.replace(/\.php$/, '')}-actions.php`);
    if (r.status !== 302) problems.push(`${c.page}: its actions file should not render on its own, got ${r.status}`);
    else log.push('  the actions file alone -> 302');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
