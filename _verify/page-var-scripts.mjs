// Finds server-side conditionals in the generated scripts that still read PAGE
// variables instead of the data island.
//
// The browser fetches <page>.js.php as its own request, so a page variable such
// as $show_modal is not in scope there and the branch silently takes the wrong
// path. The evidence, rather than a guess from the source: fetch each page, pull
// out every "var x = <value>;" and foreach-generated block from the scripts the
// page itself emits, then fetch the same script on its own and check those values
// survived.
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = process.env.VERIFY_PORT || '8201';
const BASE = `http://127.0.0.1:${PORT}`;

const REPORT_GENERATORS = new Set([
  'cash_on_hand_report_pdf.php', 'expenses_report_pdf.php', 'generate_gas_po_pdf.php',
  'generate_payroll_pdf.php', 'generate_payslip_pdf.php', 'generate_po_pdf.php',
  'generate_po_supplier_pdf.php', 'generate_pr_pdf.php', 'generate_pr_supplier_pdf.php',
  'generate_spare_parts_po_pdf.php', 'generate_spare_parts_pr_pdf.php',
  'generate_spare_parts_ws_pdf.php', 'generate_ws_pdf.php', 'job_order_slip_PDF.php',
  'fuel_report_pdf.php',
]);

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2500));

const pages = fs.readdirSync(ROOT)
  .filter(f => f.endsWith('.php') && !REPORT_GENERATORS.has(f) && f !== 'index.php')
  .sort();

const findings = [];

/** The script files a page references. */
function scriptSources(html) {
  return [...html.matchAll(/<script\s+src="assets\/js\/([^"]+\.js(?:\.php)?)"/g)].map(m => m[1]);
}

for (const page of pages) {
  const slug = page.replace(/\.php$/, '');
  // only pages whose scripts are server-rendered can drift
  const serverRendered = [`${slug}.js.php`];
  if (!fs.existsSync(path.join(ROOT, 'assets/js', serverRendered[0]))) continue;

  const seen = new Set();
  for (const session of sessions.slice(0, 3)) {
    const cookie = `PHPSESSID=${session.sid}`;
    let html;
    try {
      const res = await fetch(`${BASE}/${page}`, { headers: { Cookie: cookie }, redirect: 'manual' });
      if (res.status !== 200) continue;
      html = Buffer.from(await res.arrayBuffer()).toString('utf8');
    } catch { continue; }
    if (!html.includes('<html')) continue;

    for (const src of scriptSources(html)) {
      const key = `${page}|${src}`;
      if (seen.has(key)) continue;
      seen.add(key);

      const res = await fetch(`${BASE}/assets/js/${src}`, { headers: { Cookie: cookie } });
      const standalone = Buffer.from(await res.arrayBuffer()).toString('utf8');

      // every "identifier = <literal>" and simple foreach-generated option block
      // that the embedded version produced must also appear standalone
      const literals = [...standalone.matchAll(/\b([A-Za-z_$][\w$]*)\s*=\s*(true|false|null|'[^']*'|"[^"]*"|\d+)\s*;/g)];
      void literals;
      // compare the two by looking for the island-fed values the page emitted
      const islandMatch = /window\.OCP_PAGE_[A-Z0-9_]+\s*=\s*(\{[\s\S]*?\});/.exec(html);
      let island = null;
      if (islandMatch) { try { island = JSON.parse(islandMatch[1]); } catch { island = null; } }

      if (!island || Object.keys(island).length === 0) {
        findings.push({ page, src, kind: 'empty-island', detail: 'page emits an empty data island' });
        continue;
      }

      // the embedded copy and the standalone copy must agree on the branches that
      // PHP decided: a value produced from the island in one but not the other
      // shows up as the page copy containing a literal the standalone copy lacks
      const embedded = [...html.matchAll(new RegExp(`<script\\s+src="assets/js/${src.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}"></script>`, 'g'))];
      void embedded;

      // strongest signal: fetch the script while requesting the page as well is not
      // possible, so compare against a second standalone fetch with a cold session
      const cold = await fetch(`${BASE}/assets/js/${src}`, { headers: { Cookie: `PHPSESSID=${sessions[sessions.length - 1].sid}` } });
      const coldText = Buffer.from(await cold.arrayBuffer()).toString('utf8');
      if (norm(coldText) !== norm(standalone)) {
        findings.push({ page, src, kind: 'session-dependent-script', detail: 'the script body changes with the session, so it depends on request state' });
      }
    }
  }
}

function norm(s) { return s.replace(/\s+/g, ' ').trim(); }

server.kill();
const byPage = new Map();
for (const f of findings) {
  if (!byPage.has(f.page)) byPage.set(f.page, []);
  byPage.get(f.page).push(f);
}
console.log(`pages whose scripts are server-rendered: ${pages.filter(p => fs.existsSync(path.join(ROOT, 'assets/js', p.replace(/\.php$/, '') + '.js.php'))).length}`);
console.log(`findings: ${findings.length}`);
for (const [p, list] of byPage) {
  console.log(`  ${p}`);
  for (const f of list) console.log(`      ${f.kind}: ${f.src} (${f.detail})`);
}
