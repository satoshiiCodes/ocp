// Verifies the two routing pages, which need a record id to render at all.
//
// For each: fetch ?id=<real id>, compare the script content the page itself
// produced with the same script fetched on its own, and confirm the page is a
// full render rather than a redirect.
import { spawn, execSync, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8213';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const db = (sql) => php('query.php', [sql]).trim();

const cases = [
  { page: 'pr_view_routing.php', id: db('SELECT id FROM purchase_requests ORDER BY id LIMIT 1') },
  { page: 'pr_spare_view_routing.php', id: db('SELECT id FROM spare_parts_pr ORDER BY id LIMIT 1') },
];

// extracts the page's scripts verbatim, with ?id= set
fs.writeFileSync(path.join(ROOT, '_verify', 'extract-routing.php'), `<?php
ini_set('display_errors', '0');
error_reporting(0);
$page = $argv[1]; $sid = $argv[2]; $id = $argv[3];
$wanted = array_slice($argv, 4);
session_id($sid);
$_GET['id'] = $id;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/' . $page;
$_SERVER['PHP_SELF'] = '/' . $page;
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../' . $page;
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/' . $page . '?id=' . $id;
chdir(__DIR__ . '/..');
ob_start();
include __DIR__ . '/../' . $page;
$html = ob_get_clean();
$out = ['__bytes' => strlen($html)];
foreach ($wanted as $w) {
    $p = __DIR__ . '/../assets/js/' . $w;
    if (!is_file($p)) { $out[$w] = null; continue; }
    ob_start(); include $p; $out[$w] = ob_get_clean();
}
echo json_encode($out);
`);

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2500));

const norm = (s) => String(s).replace(/\s+/g, ' ').trim();
const problems = [];
const log = [];

for (const { page, id } of cases) {
  const slug = page.replace(/\.php$/, '');
  const html = fs.readFileSync(path.join(ROOT, page), 'utf8');
  // the page's script tags, in order. A page's JS may be a plain .js or a
  // server-rendered .js.php - taking the tag text avoids matching ".js" inside
  // ".js.php".
  const scripts = [...html.matchAll(/<script[^>]+src="assets\/js\/([^"]+)"/g)]
    .map(m => m[1])
    .filter(s => s.startsWith(slug));
  if (!scripts.length) { problems.push(`${page}: no script referenced`); continue; }

  const res = await fetch(`http://127.0.0.1:${PORT}/${page}?id=${id}`, {
    headers: { Cookie: `PHPSESSID=${sessions[0].sid}` }, redirect: 'manual',
  });
  const body = Buffer.from(await res.arrayBuffer()).toString('utf8');
  log.push(`${page}?id=${id} -> HTTP ${res.status}, ${body.length} bytes`);
  if (res.status !== 200 || !body.includes('<html')) {
    problems.push(`${page}?id=${id}: expected a full render, got HTTP ${res.status}`);
    continue;
  }

  let out;
  try {
    out = execFileSync('php', [path.join(ROOT, '_verify', 'extract-routing.php'), page, sessions[0].sid, String(id), ...scripts], { encoding: 'utf8', timeout: 60000 });
  } catch (e) { problems.push(`${page}: extractor failed (${String(e.message).slice(0, 80)})`); continue; }

  let embedded;
  try { embedded = JSON.parse(out); } catch { problems.push(`${page}: extractor returned no JSON (${out.slice(0, 80)})`); continue; }
  log.push(`  page rendered ${embedded.__bytes} bytes with id=${id}`);

  for (const [src, scriptBody] of Object.entries(embedded)) {
    if (src === '__bytes') continue;
    if (scriptBody === null) { problems.push(`${page}: ${src} referenced but missing`); continue; }
    const r2 = await fetch(`http://127.0.0.1:${PORT}/assets/js/${src}`, { headers: { Cookie: `PHPSESSID=${sessions[0].sid}` } });
    const standalone = await r2.text();
    if (norm(scriptBody) === norm(standalone)) {
      log.push(`  ${src}: identical in both contexts`);
    } else {
      const a = norm(scriptBody), b = norm(standalone);
      let at = 0; while (at < a.length && at < b.length && a[at] === b[at]) at++;
      problems.push(`${page} -> ${src}: differs at ${at}\n      embedded  : ...${a.slice(Math.max(0, at - 50), at + 100)}\n      standalone: ...${b.slice(Math.max(0, at - 50), at + 100)}`);
    }
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
void execSync;
process.exit(problems.length ? 1 : 0);
