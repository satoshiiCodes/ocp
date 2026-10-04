// Checks that a page's data island actually carries a payload, not an empty value.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8239';
const page = process.argv[2];
const key = process.argv[3];

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const res = await fetch(`http://127.0.0.1:${PORT}/${page}`, { headers: { Cookie: `PHPSESSID=${sessions[0].sid}` } });
const html = await res.text();
const m = /window\.OCP_PAGE_[A-Z0-9_]+\s*=\s*(\{[\s\S]*?\});/.exec(html);
if (!m) { console.log('no island found'); server.kill(); process.exit(1); }
let island;
try { island = JSON.parse(m[1]); } catch (e) { console.log('island did not parse:', e.message); server.kill(); process.exit(1); }

console.log(`${page}: island keys -> ${Object.keys(island).join(', ')}`);
const v = island[key];
if (v === undefined) { console.log(`  ${key}: NOT PRESENT`); server.kill(); process.exit(1); }
const s = String(v);
console.log(`  ${key}: ${s.length} chars`);
console.log(`  contains "<option": ${s.includes('<option')}`);
console.log(`  option count: ${(s.match(/<option/g) || []).length}`);
console.log(`  sample: ${s.slice(0, 200).replace(/\s+/g, ' ')}`);
server.kill();
process.exit(s.includes('<option') ? 0 : 1);
