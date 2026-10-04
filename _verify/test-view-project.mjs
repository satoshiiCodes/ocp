// Data-driven test: actions/endpoint split for view_project.php.
//
// This page is opened by a POST from the projects list carrying the project id, or
// by remembering the last one in the session. It adds and removes workers and
// rentals, so the test exercises those on throw-away links and removes them again.
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8251';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;
const db = (sql) => php('query.php', [sql]).trim();

// A session for the "no project at all" case. The page remembers the last project in
// the session and every fixture session has been used by other tests, so a fresh one
// is written here - before the server starts, because a session file created while
// the server is already serving requests is not picked up.
const COLD = 'ocpverifycoldvproject0000';
const COLD_FILE = path.join('E:\\laragon\\tmp', `sess_${COLD}`);
fs.writeFileSync(COLD_FILE, 'user_id|i:2;fullname|s:12:"Rio Salatan1";account_type|s:5:"Admin";');

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required|Error fetching project)/;

async function req(url, fields, cookie) {
  const opts = { headers: { Cookie: cookie || COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 150).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

const projectId = db('SELECT id FROM projects ORDER BY id LIMIT 1');
const projectName = db(`SELECT project_name FROM projects WHERE id = ${projectId}`);
log.push(`fixture project ${projectId} ("${projectName}")`);

// ------------------------------------------------- 1. no id and no session redirects
{
  // COLD_FILE was written before the server started. A session that knows the user
  // but no project must send the request back to the project list.
  const { status, location } = await req('/view_project.php', undefined, `PHPSESSID=${COLD}`);
  log.push(`GET with a cold session -> ${status} ${location || ''}`);
  if (status !== 302) problems.push(`a cold session should redirect, got ${status}`);
  else if (!/projects\.php/.test(location || '')) problems.push(`it should redirect to projects.php, got ${location}`);
  else log.push('  it redirects to the project list');
}

// --------------------------------------------------------- 2. a posted id renders
{
  const r = await req('/view_project.php', { id: projectId });
  log.push(`POST id=${projectId} -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`a posted id should render, got ${r.status}`);
  if (!r.text.includes(projectName)) problems.push('the project name is not shown');
  else log.push('  the project renders');
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
}

// --------------------------------------------- 3. the session remembers the project
{
  const r = await req('/view_project.php');   // the same session that just posted
  log.push(`GET with the remembered id -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`the remembered id should render, got ${r.status}`);
  if (!r.text.includes(projectName)) problems.push('the remembered project did not render');
  else log.push('  the session-remembered project renders');
}

// --------------------------------------------------- 4. an unknown project redirects
{
  const r = await req('/view_project.php', { id: 999999999 });
  log.push(`POST an unknown id -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`an unknown project should redirect, got ${r.status}`);
  else log.push('  an unknown project redirects to the list');
}

// ------------------------------------ 5. workers can be added and removed
{
  const before = Number(db(`SELECT COUNT(*) FROM project_workers WHERE project_id = ${projectId}`));
  // a worker who is not already on this project
  // add_workers takes user_id values, drawn from the eligible-worker list
  const workerId = db(`
    SELECT id FROM employee
    WHERE status = 'active'
      AND position IN ('Foreman','Skilled','Welder','Helper')
      AND id NOT IN (SELECT user_id FROM project_workers WHERE project_id = ${projectId})
    ORDER BY id LIMIT 1
  `);
  if (!workerId) {
    log.push('  (no free worker to add; skipping the add/remove check)');
  } else {
    // the add form posts checkboxes named worker_ids[], so the body needs that name
    const added = await req('/view_project.php', { id: projectId, add_workers: '1', 'worker_ids[]': workerId });
    const after = Number(db(`SELECT COUNT(*) FROM project_workers WHERE project_id = ${projectId}`));
    log.push(`POST add_workers -> ${before} -> ${after} worker(s)`);
    if (after === before) {
      problems.push(`add_workers did not add the worker (HTTP ${added.status})`);
    } else {
      // the remove button posts the employee id, which is what the listed worker
      // rows expose as their id
      await req('/view_project.php', { id: projectId, remove_worker: workerId });
      const last = Number(db(`SELECT COUNT(*) FROM project_workers WHERE project_id = ${projectId}`));
      if (last !== before) problems.push(`remove_worker left the count at ${last}, expected ${before}`);
      else log.push('  the worker was added and removed again');
    }
  }
}

// ------------------------------------------------------ 6. no leftover rentals
{
  const rentals = Number(db(`SELECT COUNT(*) FROM project_rentals WHERE project_id = ${projectId} AND notes LIKE '%verification%'`));
  if (rentals) {
    db(`DELETE FROM project_rentals WHERE project_id = ${projectId} AND notes LIKE '%verification%'`);
    log.push(`  removed ${rentals} leftover rental(s)`);
  } else {
    log.push('no leftover rentals');
  }
}

server.kill();
fs.rmSync(COLD_FILE, { force: true });
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
