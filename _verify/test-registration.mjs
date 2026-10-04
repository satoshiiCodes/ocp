// Data-driven test: actions split for registration.php.
//
// registration.js posts straight to actions/registration-actions.php, so the test
// does the same. Everything is additive and removed again: it creates one
// throw-away user, exercises all four actions on it, and deletes it, comparing the
// row count before and after.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8223';
const ACTIONS = '/actions/registration-actions.php';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;
const db = (sql) => php('query.php', [sql]).trim();

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;

async function post(url, fields) {
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, {
    method: 'POST', body: new URLSearchParams(fields), headers: { Cookie: COOKIE }, redirect: 'manual',
  });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 130).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  let json = null;
  try { json = JSON.parse(text); } catch { /* reported by the caller */ }
  return { status: res.status, text, json };
}

async function get(url) {
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, { headers: { Cookie: COOKIE }, redirect: 'manual' });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 130).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text };
}

const count = () => Number(db('SELECT COUNT(*) FROM users'));
const start = count();
const stamp = Date.now();
const username = `verifyuser${stamp}`;
const email = `verify${stamp}@example.test`;

// ------------------------------------------------------------------ 1. renders
{
  const r = await get('/registration.php');
  log.push(`GET /registration.php -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`registration.php HTTP ${r.status}`);
  if (!/id="registrationForm"|User Registration|Register/i.test(r.text)) problems.push('the registration form is gone');
}

// ---------------------------------------- 2. validation is still reported as JSON
{
  const r = await post(ACTIONS, { action: 'add_user', lastname: '' });
  log.push(`add_user with no last name -> ${r.status} ${JSON.stringify(r.json)?.slice(0, 90)}`);
  if (!r.json || r.json.success) problems.push('an empty last name was accepted');
  else if (!/Last name is required/.test(r.json.message)) problems.push(`unexpected message: ${r.json.message}`);
  else log.push('  the required-field message is returned');
  if (count() !== start) problems.push('the rejected add changed the user count');
}

// ------------------------------------------------------------------- 3. add
{
  const r = await post(ACTIONS, {
    action: 'add_user',
    lastname: 'Verify', firstname: 'User', middlename: 'M', suffix: '',
    department: 'IT', position: 'Staff', address: 'Verify Address', contact: '09000000000',
    status: 'active', accounttype: 'Staff', email, username,
    password: 'verifypass123', confirmpassword: 'verifypass123',
  });
  log.push(`add_user "${username}" -> ${r.status} ${JSON.stringify(r.json)}`);
  if (!r.json || !r.json.success) problems.push(`add_user failed: ${r.text.slice(0, 120)}`);
  else log.push('  the user was added');
  if (count() !== start + 1) problems.push(`user count ${start} -> ${count()}, expected +1`);
}

const id = db(`SELECT id FROM users WHERE username = '${username}'`);
log.push(`  new user id: ${id}`);

// -------------------------------------------- 4. duplicate username is rejected
{
  const r = await post(ACTIONS, {
    action: 'add_user',
    lastname: 'Verify', firstname: 'User', middlename: '', suffix: '',
    department: 'IT', position: 'Staff', address: 'x', contact: '0',
    status: 'active', accounttype: 'Staff', email: `other${stamp}@example.test`, username,
    password: 'verifypass123', confirmpassword: 'verifypass123',
  });
  if (!r.json || r.json.success) problems.push('a duplicate username was accepted');
  else if (!/already exists/.test(r.json.message)) problems.push(`unexpected duplicate message: ${r.json.message}`);
  else log.push('  a duplicate username is rejected');
  if (count() !== start + 1) problems.push('the rejected duplicate changed the user count');
}

// ---------------------------------------------------------------- 5. get_user
{
  const r = await post(ACTIONS, { action: 'get_user', id });
  log.push(`get_user id=${id} -> ${r.status} ${r.json ? JSON.stringify(r.json).slice(0, 80) : r.text.slice(0, 80)}`);
  if (!r.json || !r.json.success) problems.push('get_user did not return the user');
  else if (r.json.user.username !== username) problems.push(`get_user returned "${r.json.user.username}"`);
  else log.push('  get_user returns the record');
}

{
  const r = await post(ACTIONS, { action: 'get_user', id: 999999999 });
  if (!r.json || r.json.success) problems.push('get_user accepted an unknown id');
  else log.push('  get_user reports an unknown id');
}

// ----------------------------------------------------------------- 6. update
{
  const renamed = `VerifyRenamed${stamp}`;
  const r = await post(ACTIONS, {
    action: 'update_user', id,
    lastname: 'Verify', firstname: 'Renamed', middlename: '', suffix: '',
    department: 'IT', position: 'Staff', address: 'Verify Address 2', contact: '09999999999',
    status: 'active', accounttype: 'Staff', email, username: renamed,
  });
  log.push(`update_user id=${id} -> ${r.status} ${JSON.stringify(r.json)}`);
  if (!r.json || !r.json.success) problems.push(`update_user failed: ${r.text.slice(0, 120)}`);
  const stored = db(`SELECT username FROM users WHERE id = ${id}`);
  if (stored !== renamed) problems.push(`update did not persist: "${stored}"`);
  else log.push('  update persisted');
}

// update must not change the password unless asked
{
  const before = db(`SELECT password FROM users WHERE id = ${id}`);
  await post(ACTIONS, {
    action: 'update_user', id,
    lastname: 'Verify', firstname: 'Renamed', middlename: '', suffix: '',
    department: 'IT', position: 'Staff', address: 'Verify Address 2', contact: '09999999999',
    status: 'active', accounttype: 'Staff', email, username: `VerifyRenamed${stamp}`,
  });
  const after = db(`SELECT password FROM users WHERE id = ${id}`);
  if (before !== after) problems.push('update_user changed the password without being asked');
  else log.push('  update leaves the password alone when not asked');
}

// ---------------------------------------------------------------- 7. delete
{
  const r = await post(ACTIONS, { action: 'delete_user', id });
  log.push(`delete_user id=${id} -> ${r.status} ${JSON.stringify(r.json)}`);
  if (!r.json || !r.json.success) problems.push(`delete_user failed: ${r.text.slice(0, 120)}`);
  if (db(`SELECT COUNT(*) FROM users WHERE id = ${id}`) !== '0') problems.push('delete_user did not remove the row');
  else log.push('  the user was deleted');
}

// -------------------------------------------------------- 8. unauthenticated is refused
{
  const res = await fetch(`http://127.0.0.1:${PORT}${ACTIONS}`, {
    method: 'POST', body: new URLSearchParams({ action: 'get_user', id: 1 }), redirect: 'manual',
  });
  const text = await res.text();
  let json = null; try { json = JSON.parse(text); } catch { /* fine */ }
  log.push(`no session -> ${res.status} ${text.slice(0, 60)}`);
  if (json && json.success) problems.push('an unauthenticated request was served');
  else log.push('  an unauthenticated request is refused');
}

// ------------------------------------------------------------- 9. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} user(s) behind`);
    db(`DELETE FROM users WHERE username LIKE 'VerifyRenamed%' OR username LIKE 'verifyuser%'`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
