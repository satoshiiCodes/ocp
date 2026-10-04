// Reconnaissance: how does each page's POST handling finish?
//
//   redirect  - the handler ends in a header('Location: ...') so it is a clean
//               POST-redirect-GET and can move to actions/<page>-actions.php
//   rerender  - the handler falls through to the markup, so the page re-renders
//               with $_POST still available (inline validation errors)
//   mixed     - some handlers redirect, some re-render
//
// It also reports where the fetching lives so the api/ side can be planned.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const pages = fs.readdirSync(ROOT, { withFileTypes: true })
  .filter(d => d.isFile() && d.name.endsWith('.php')).map(d => d.name)
  .filter(f => !/_pdf\.php$/.test(f) && f !== 'job_order_slip_PDF.php' && f !== 'index.php')
  .sort();

const REPORT = new Set([
  'cash_on_hand_report_pdf.php', 'expenses_report_pdf.php', 'generate_gas_po_pdf.php',
  'generate_payroll_pdf.php', 'generate_payslip_pdf.php', 'generate_po_pdf.php',
  'generate_po_supplier_pdf.php', 'generate_pr_pdf.php', 'generate_pr_supplier_pdf.php',
  'generate_spare_parts_po_pdf.php', 'generate_spare_parts_pr_pdf.php',
  'generate_spare_parts_ws_pdf.php', 'generate_ws_pdf.php', 'job_order_slip_PDF.php',
  'fuel_report_pdf.php',
]);

const rows = [];
const totals = { redirect: 0, rerender: 0, mixed: 0, none: 0 };

for (const page of pages) {
  if (REPORT.has(page)) continue;
  const src = fs.readFileSync(path.join(ROOT, page), 'utf8');
  const head = src.slice(0, src.indexOf('<!DOCTYPE'));

  const postChecks = (head.match(/\$_SERVER\['REQUEST_METHOD'\]\s*===\s*'POST'|isset\(\$_POST\[/g) || []).length;
  const redirects = (head.match(/header\(\s*["']Location:/g) || []).length;
  const fetchCalls = (head.match(/->prepare\(|->query\(/g) || []).length;
  const formTargetsSelf = (src.match(/action\s*=\s*"\s*"/g) || []).length;

  // which handler style dominates: redirect after write, or fall through
  let kind = 'none';
  if (postChecks === 0) kind = 'none';
  else if (redirects === 0) kind = 'rerender';
  else if (postChecks > redirects * 2) kind = 'mixed';
  else kind = 'redirect';

  totals[kind]++;
  rows.push({ page, kind, postChecks, redirects, fetchCalls, formTargetsSelf, headLines: head.split('\n').length });
}

rows.sort((a, b) => b.postChecks - a.postChecks || b.fetchCalls - a.fetchCalls);

console.log(`${'page'.padEnd(34)} ${'style'.padEnd(9)} ${'POST'.padStart(5)} ${'redir'.padStart(6)} ${'queries'.padStart(8)} ${'selfForm'.padStart(9)} ${'headLines'.padStart(10)}`);
for (const r of rows) {
  console.log(`${r.page.padEnd(34)} ${r.kind.padEnd(9)} ${String(r.postChecks).padStart(5)} ${String(r.redirects).padStart(6)} ${String(r.fetchCalls).padStart(8)} ${String(r.formTargetsSelf).padStart(9)} ${String(r.headLines).padStart(10)}`);
}
console.log(`\nredirect-style pages : ${totals.redirect}`);
console.log(`rerender-style pages : ${totals.rerender}`);
console.log(`mixed pages          : ${totals.mixed}`);
console.log(`no POST handling     : ${totals.none}`);
console.log(`total pages          : ${rows.length}`);
console.log(`total POST checks     : ${rows.reduce((a, r) => a + r.postChecks, 0)}`);
console.log(`total queries in head : ${rows.reduce((a, r) => a + r.fetchCalls, 0)}`);
