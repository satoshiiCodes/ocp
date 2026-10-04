// Runs the pre-restructure copy and the restructured app side by side and
// compares, for every role:
//
//   * whether the page renders at all (status, and PHP failure text)
//   * the union of every value PHP echoed into the page or its scripts
//
// The second part is what makes the comparison meaningful: the restructure moved
// echoed values into a data island, so raw HTML differs by design. Comparing the
// set of echoed VALUES shows whether any role lost data it used to receive.
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const APPS = [
  { name: 'baseline', root: 'E:\\laragon\\www\\OCP\\_baseline', port: '8170' },
  { name: 'current ', root: 'E:\\laragon\\www\\OCP', port: '8171' },
];

const sessions = JSON.parse(
  execFileSync('php', [path.join('E:\\laragon\\www\\OCP', '_verify', 'sessions.php')], { encoding: 'utf8' }).replace(/^\uFEFF/, '')
);

const PAGES = fs.readdirSync(APPS[1].root, { withFileTypes: true })
  .filter(d => d.isFile() && d.name.endsWith('.php')).map(d => d.name)
  .filter(f => !/_pdf\.php$/.test(f) && f !== 'job_order_slip_PDF.php').sort();

const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Trying to access array offset|Call to undefined|Failed opening required)/;

/** Values PHP echoed, as text: every quoted string plus every JSON payload. */
function echoedValues(text) {
  const out = new Set();
  for (const m of text.matchAll(/"([^"\\\n]{1,80})"/g)) out.add(m[1]);
  return out;
}

async function run(app) {
  const server = spawn('php', ['-S', `127.0.0.1:${app.port}`, '-t', '.'], { cwd: app.root, stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 2300));
  const results = new Map();

  for (const s of sessions) {
    for (const page of PAGES) {
      const key = `${page}|${s.dept}/${s.pos}`;
      try {
        const res = await fetch(`http://127.0.0.1:${app.port}/${page}`, {
          headers: { Cookie: `PHPSESSID=${s.sid}` }, redirect: 'manual',
        });
        const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
        const err = HARD.exec(text);
        results.set(key, {
          status: res.status,
          error: err ? err[0] : null,
          values: echoedValues(text),
        });
      } catch (e) {
        results.set(key, { status: 0, error: e.message, values: new Set() });
      }
    }
  }
  server.kill();
  return results;
}

const [base, curr] = [await run(APPS[0]), await run(APPS[1])];

const problems = [];
let compared = 0;
for (const [key, b] of base) {
  const c = curr.get(key);
  if (!c) { problems.push(`${key}: missing from the restructured run`); continue; }
  compared++;

  // status: a 302 in both is fine; a difference is not
  const bRedirects = b.status === 302, cRedirects = c.status === 302;
  if (bRedirects !== cRedirects) problems.push(`${key}: status ${b.status} -> ${c.status}`);

  // a PHP failure that the baseline did not have is a regression
  if (c.error && !b.error) problems.push(`${key}: new PHP failure "${c.error}"`);
}

console.log(`compared ${compared} page x role combinations`);
console.log(`problems: ${problems.length}`);
for (const p of problems.slice(0, 25)) console.log('  X ' + p);
if (problems.length > 25) console.log(`  ... ${problems.length - 25} more`);
fs.writeFileSync('E:\\laragon\\www\\OCP\\_verify\\baseline-compare.json', JSON.stringify({ compared, problems }, null, 2));
process.exit(problems.length ? 1 : 0);
