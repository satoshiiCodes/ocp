// Fetches a page's generated script with a preview island and checks what it emits.
import { spawn } from 'node:child_process';
const ROOT = 'E:\\laragon\\www\\OCP';
const script = process.argv[2];
const needle = process.argv[3];
const PORT = process.argv[4] || '8200';
const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));
const res = await fetch(`http://127.0.0.1:${PORT}/${script}`);
const text = await res.text();
console.log(`${script} -> ${res.status}, ${text.length} bytes, content-type: ${res.headers.get('content-type')}`);
if (needle) {
  const idx = text.indexOf(needle);
  console.log(`contains ${JSON.stringify(needle)}:`, idx !== -1);
  if (idx !== -1) console.log('  context:', text.slice(Math.max(0, idx - 90), idx + 90).replace(/\s+/g, ' '));
}
console.log('emits any PHP notice:', /(Warning|Notice|Fatal|Undefined)/.test(text));
server.kill();
