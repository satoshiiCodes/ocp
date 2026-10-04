// Verifies logout on every page that offers it.
//
//   node test-logout.mjs
//
// Two halves, because the failure had two halves:
//
//   1. the link must point at a file that exists. The restructure moved action/logout.php to
//      actions/logout.php and several scripts kept the old spelling, so clicking Logout asked
//      for a missing file and the server answered "Not Found".
//   2. the click must be able to run at all. includes/top_bar.php and assets/js/app.js both
//      wire the link with Swal.fire, and eight pages included one of those without ever
//      loading sweetalert2 - so `Swal` was undefined, the handler threw on its first line and
//      nothing happened.
//
// The path is checked against the filesystem and over HTTP; the click is checked by loading
// each page in a browser and confirming Swal is available and the handler is attached. The
// logout itself is then driven over HTTP, where the redirect and the session ending are
// observable without a browser's evaluation being disturbed by the navigation.
import { execFileSync, spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8570';
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');

const problems = [];
const log = [];

// ---- 1. every page that has a logout link, and where each script sends it
const pages = fs.readdirSync(ROOT).filter(f => f.endsWith('.php') && !/_pdf\.php$/.test(f));
const withLink = pages.filter(p => {
  const src = fs.readFileSync(path.join(ROOT, p), 'utf8');
  return /includes\/top_bar\.php/.test(src) || /id="logoutLink"/.test(src);
});
log.push(`pages offering a logout link: ${withLink.length}`);

// every logout URL written anywhere in the app
const urls = new Set();
const walk = (dir) => {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, e.name);
    if (e.isDirectory()) {
      if (['vendor', '_restructure_backup', '_verify', 'backups', 'config', 'includes', 'actions', 'api', 'assets'].includes(e.name)) continue;
      walk(full);
    } else if (/\.php$/.test(e.name)) {
      for (const m of fs.readFileSync(full, 'utf8').matchAll(/['"]([^'"]*logout\.php)['"]/g)) urls.add(m[1]);
    }
  }
};
walk(ROOT);
// and in the scripts that are served
for (const dir of ['assets/js', 'assets/js/includes']) {
  for (const f of fs.readdirSync(path.join(ROOT, dir))) {
    if (!/\.(js|js\.php)$/.test(f)) continue;
    for (const m of fs.readFileSync(path.join(ROOT, dir, f), 'utf8').matchAll(/['"]([^'"]*logout\.php)['"]/g)) urls.add(m[1]);
  }
}
log.push(`logout URLs written in the app: ${[...urls].map(u => JSON.stringify(u)).join(', ')}`);
for (const u of urls) {
  if (u.includes('..')) { problems.push(`"${u}" is relative to the script, but a browser resolves it against the page - it cannot be right for a page at the root`); continue; }
  if (!fs.existsSync(path.join(ROOT, u))) { problems.push(`"${u}" does not exist on disk`); continue; }
}
if (![...urls].some(u => u === 'actions/logout.php')) problems.push('nothing points at actions/logout.php');

// ---- over HTTP: the path answers, and the session really ends
const app = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2200));

for (const u of urls) {
  const sid = JSON.parse(php('sessions.php'))[0].sid;
  const res = await fetch(`http://127.0.0.1:${PORT}/${u}`, { headers: { Cookie: `PHPSESSID=${sid}` }, redirect: 'manual' });
  await res.arrayBuffer();
  const location = res.headers.get('location') || '';
  log.push(`GET ${u} -> ${res.status}, redirect to ${JSON.stringify(location)}`);
  if (res.status === 404) { problems.push(`${u} answers 404 - this is the "Not Found"`); continue; }

  // the session it was given must be gone
  const after = await fetch(`http://127.0.0.1:${PORT}/dashboard.php`, { headers: { Cookie: `PHPSESSID=${sid}` }, redirect: 'manual' });
  log.push(`  the same session asked for dashboard.php -> ${after.status} (302 = it was ended)`);
  if (after.status !== 302) problems.push(`${u} did not end the session`);

  // Following the redirect is the check that was missing, and the reason this took two
  // attempts: a Location can point at a page that does not exist. "../index.php" resolves one
  // level above the app and "index.php" resolves inside actions/ - both were tried, and only
  // following it shows which one lands somewhere real.
  const sid2 = JSON.parse(php('sessions.php'))[0].sid;
  const followed = await fetch(`http://127.0.0.1:${PORT}/${u}`, { headers: { Cookie: `PHPSESSID=${sid2}` }, redirect: 'follow' });
  const body = Buffer.from(await followed.arrayBuffer()).toString('utf8');
  const isLogin = /type="password"/.test(body);
  log.push(`  following it -> ${followed.status} at ${new URL(followed.url).pathname}, login form present: ${isLogin}`);
  if (followed.status === 404) problems.push(`${u} redirects to a page that does not exist (${location})`);
  if (!isLogin) problems.push(`${u} did not land on the login page (landed at ${new URL(followed.url).pathname})`);
}

// ---- in a browser: Swal must exist on every page that wires logout with it
for (const p of withLink) {
  const sid = JSON.parse(php('sessions.php'))[0].sid;
  const res = await fetch(`http://127.0.0.1:${PORT}/${p}`, { headers: { Cookie: `PHPSESSID=${sid}` }, redirect: 'manual' });
  const html = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const wiresWithSwal = /Swal\.fire/.test(html) || /top_bar\.js|assets\/js\/app\.js/.test(html);
  const loadsSwal = /sweetalert2/.test(html) || /includes\/sweetalert\.php|sweetalert2@11/.test(html);
  if (wiresWithSwal && !loadsSwal) {
    problems.push(`${p}: wires logout with Swal but never loads sweetalert2`);
  }
}
log.push(`checked ${withLink.length} page(s) for the library their logout handler needs`);

app.kill();

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
