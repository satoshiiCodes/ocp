// Shows the PHP notices a server-rendered asset emits when it is requested
// directly (no page context) - the "cache miss" case.
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8151';
const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const files = fs.readdirSync(path.join(ROOT, 'assets/js')).filter(f => f.endsWith('.js.php')).sort();
const seen = new Map();

for (const f of files) {
  const res = await fetch(`http://127.0.0.1:${PORT}/assets/js/${f}`);
  const text = await res.text();
  const clean = text.replace(/<br\s*\/?>/g, ' ').replace(/<[^>]*>/g, '');
  const notices = [...clean.matchAll(/(Warning|Notice|Deprecated|Fatal error)\s*:[^:]{0,110}/g)].map(m => m[0].replace(/\s+/g, ' '));
  if (!notices.length) continue;
  const uniq = [...new Set(notices.map(n => n.replace(/ in [A-Z]:\\[^\s]*/g, '').slice(0, 90)))];
  seen.set(f, uniq.slice(0, 4));
}

server.kill();
console.log(`assets with notices on a direct request: ${seen.size} of ${files.length}\n`);
for (const [f, list] of seen) {
  console.log(`${f}`);
  for (const n of list) console.log(`    ${n}`);
}
