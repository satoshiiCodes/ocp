// Compares the dashboard data island across every role, so an over-eager guard
// (a value missing for a role that should have it) becomes visible.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8161';
const sessions = JSON.parse(
  execFileSync('php', [path.join(ROOT, '_verify', 'sessions.php')], { encoding: 'utf8' }).replace(/^\uFEFF/, '')
);

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const perRole = [];
for (const s of sessions) {
  const res = await fetch(`http://127.0.0.1:${PORT}/dashboard.php`, { headers: { Cookie: `PHPSESSID=${s.sid}` }, redirect: 'manual' });
  const text = await res.text();
  const m = /<script>window\.OCP_PAGE_DASHBOARD = (\{[\s\S]*?\});<\/script>/.exec(text);
  let data = {};
  if (m) { try { data = JSON.parse(m[1]); } catch { data = { __parse_error: true }; } }
  perRole.push({ role: `${s.dept}/${s.pos}`, keys: Object.keys(data) });
  console.log(`  ${`${s.dept}/${s.pos}`.padEnd(26)} island keys: ${Object.keys(data).join(', ') || '(none)'}`);
}

server.kill();

const all = new Set(perRole.flatMap(r => r.keys));
console.log(`\nkeys seen across all roles: ${[...all].join(', ') || '(none)'}`);
for (const k of all) {
  const missing = perRole.filter(r => !r.keys.includes(k)).map(r => r.role);
  if (missing.length) console.log(`  ${k.padEnd(24)} missing for: ${missing.join(', ')}`);
}
