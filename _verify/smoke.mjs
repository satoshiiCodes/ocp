// Smoke-checks a page over HTTP and reports island keys + any PHP problem text.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = process.argv[3] || '8210';
const pages = process.argv[2].split(',');

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;
let bad = 0;

for (const page of pages) {
  const res = await fetch(`http://127.0.0.1:${PORT}/${page}`, {
    headers: { Cookie: `PHPSESSID=${sessions[0].sid}` }, redirect: 'manual',
  });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  const island = /window\.OCP_PAGE_[A-Z0-9_]+\s*=\s*(\{.*?\});/s.exec(text);
  let keys = [];
  if (island) { try { keys = Object.keys(JSON.parse(island[1])); } catch { keys = ['(unparsable)']; } }
  const status = err ? 'PHP ERROR' : (res.status === 200 ? 'ok' : `HTTP ${res.status}`);
  if (err) { bad++; }
  console.log(`${page}: ${status}, ${text.length} bytes`);
  console.log(`   island keys: ${keys.join(', ') || '(none)'}`);
  if (err) console.log(`   ${text.slice(err.index, err.index + 140).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
}

server.kill();
console.log(`\nproblems: ${bad}`);
process.exit(bad ? 1 : 0);
