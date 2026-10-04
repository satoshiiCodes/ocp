// Extracts a page's generated scripts EXACTLY as the page renders them, by
// buffering the page's output and printing each script's content verbatim.
//
//   php _verify/extract-scripts.php <page> <sessionId> [script.js.php ...]
//
// A browser gets those scripts from a SECOND request, where the page's variables
// are not in scope. Comparing the two is the direct evidence of whether a script's
// server-side branches depend on page state they will not have.
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = process.env.OCP_PORT || '8203';

fs.writeFileSync(path.join(ROOT, '_verify', 'extract-scripts.php'), `<?php
ini_set('display_errors', '0');
error_reporting(0);
$page = $argv[1];
$sid  = $argv[2];
$wanted = array_slice($argv, 3);

session_id($sid);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME']    = '/' . $page;
$_SERVER['PHP_SELF']       = '/' . $page;
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../' . $page;
$_SERVER['HTTP_HOST']      = '127.0.0.1';
$_SERVER['REQUEST_URI']    = '/' . $page;
chdir(__DIR__ . '/..');

ob_start();
include __DIR__ . '/../' . $page;
ob_end_clean();

$out = [];
foreach ($wanted as $w) {
    $body = null;
    $path = __DIR__ . '/../assets/js/' . $w;
    if (is_file($path)) {
        // Same process, same scope the page had: this is the embedded rendering.
        ob_start();
        include $path;
        $body = ob_get_clean();
    }
    $out[$w] = $body;
}
echo json_encode($out);
`);

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const SID = sessions[0].sid;

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2500));

const REPORT_GENERATORS = new Set([
  'cash_on_hand_report_pdf.php', 'expenses_report_pdf.php', 'generate_gas_po_pdf.php',
  'generate_payroll_pdf.php', 'generate_payslip_pdf.php', 'generate_po_pdf.php',
  'generate_po_supplier_pdf.php', 'generate_pr_pdf.php', 'generate_pr_supplier_pdf.php',
  'generate_spare_parts_po_pdf.php', 'generate_spare_parts_pr_pdf.php',
  'generate_spare_parts_ws_pdf.php', 'generate_ws_pdf.php', 'job_order_slip_PDF.php',
  'fuel_report_pdf.php',
]);

const norm = (s) => String(s).replace(/\s+/g, ' ').trim();
const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const problems = [];
const skipped = [];
let compared = 0;
let identical = 0;

for (const page of fs.readdirSync(ROOT).filter(f => f.endsWith('.php') && !REPORT_GENERATORS.has(f) && f !== 'index.php').sort()) {
  const slug = page.replace(/\.php$/, '');
  const html = fs.readFileSync(path.join(ROOT, page), 'utf8');
  // the page's own script tags, in order
  const want = [...html.matchAll(new RegExp(`assets/js/(${esc(slug)}[\\w.-]*\\.js(?:\\.php)?)`, 'g'))].map(m => m[1]);
  const serverRendered = want.filter(w => w.endsWith('.js.php'));
  if (!serverRendered.length) continue;

  let out;
  try {
    out = execFileSync('php', [path.join(ROOT, '_verify', 'extract-scripts.php'), page, SID, ...serverRendered],
      { encoding: 'utf8', env: { ...process.env, OCP_PORT: PORT }, timeout: 60000 });
  } catch (e) {
    problems.push(`${page}: extractor failed (${String(e.message).slice(0, 90)})`);
    continue;
  }

  // A detail/routing page with no record id redirects and exits, so the extractor
  // never reaches its JSON. That is the page behaving correctly, not a failure.
  if (out.trim() === '') {
    if (!skipped.includes(page)) skipped.push(page);
    continue;
  }

  let embedded;
  try { embedded = JSON.parse(out); } catch { problems.push(`${page}: extractor returned no JSON (${out.slice(0, 90)})`); continue; }

  for (const [src, body] of Object.entries(embedded)) {
    if (body === null) { problems.push(`${page} -> ${src}: referenced but missing`); continue; }
    const res = await fetch(`http://127.0.0.1:${PORT}/assets/js/${src}`, { headers: { Cookie: `PHPSESSID=${SID}` } });
    const standalone = await res.text();
    compared++;
    if (norm(body) === norm(standalone)) { identical++; continue; }
    const a = norm(body);
    const b = norm(standalone);
    let at = 0;
    while (at < a.length && at < b.length && a[at] === b[at]) at++;
    problems.push(`${page} -> ${src}: differs at char ${at}\n      embedded  : ...${a.slice(Math.max(0, at - 50), at + 100)}\n      standalone: ...${b.slice(Math.max(0, at - 50), at + 100)}`);
  }
}

server.kill();
console.log(`server-rendered scripts compared : ${compared}`);
console.log(`identical in both contexts       : ${identical}`);
console.log(`divergent                        : ${problems.length}`);
console.log(`skipped (renders nothing without an id) : ${skipped.length}${skipped.length ? ' - ' + skipped.join(', ') : ''}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
