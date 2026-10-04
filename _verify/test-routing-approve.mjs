// Runs the routing approve walk against a live server.
//
//   node test-routing-approve.mjs
import { spawn } from 'node:child_process';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8640';

const app = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2200));

let out = '';
let failed = false;
try {
  out = execFileSync('php', [path.join(ROOT, '_verify', 'check-routing-approve.php'), PORT], { encoding: 'utf8' });
} catch (e) {
  out = ((e.stdout || '') + (e.stderr || '')).toString();
  failed = true;
}
app.kill();

console.log(out.split('\n').map(l => (l.trim() ? '  ' + l.replace(/^\s+/, '') : l)).join('\n'));
console.log(`\nproblems: ${failed ? 1 : 0}`);
process.exit(failed ? 1 : 0);
