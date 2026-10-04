// Runs the spare-parts stock check against a live server.
//
//   node test-spare-parts-stock-sync.mjs
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const rows = (s) => php('rows.php', [s]).split('\n').map(l => l.trim()).filter(l => l.startsWith('{')).map(l => JSON.parse(l));

const user = rows("SELECT id FROM users WHERE accounttype = 'Admin' ORDER BY id LIMIT 1")[0];
const sid = execFileSync('php', [path.join(ROOT, '_verify', 'make-session.php'), String(user.id)], { encoding: 'utf8' }).trim();
const PORT = '8550';
const app = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2000));

let out = '';
let failed = false;
try {
  out = execFileSync('php', [path.join(ROOT, '_verify', 'check-spare-parts-stock.php'), String(user.id), PORT, sid], { encoding: 'utf8' });
} catch (e) {
  out = (e.stdout || '') + (e.stderr || '');
  failed = true;
}
app.kill();

console.log(out.split('\n').map(l => (l.trim() ? '  ' + l.replace(/^\s+/, '') : l)).join('\n'));
const problems = failed ? 1 : 0;
console.log(`\nproblems: ${problems}`);
process.exit(problems ? 1 : 0);
