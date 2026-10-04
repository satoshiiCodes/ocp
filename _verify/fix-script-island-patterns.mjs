// Rewrites the two recurring shapes in the server-rendered scripts.
//
//   node fix-script-island-patterns.mjs [--apply]
//
// A script under assets/js/*.js.php is fetched as its OWN request, so none of its page's
// variables are in scope. Two shapes therefore never do what they look like they do:
//
//   1. A SweetAlert block guarded by `<?php if (!empty($page_var)): ?>`. The guard is
//      evaluated in the script's request, where that variable does not exist, so the
//      whole block is dropped before the browser sees it - the alert never fires.
//      It becomes a client-side test on a boolean the page puts in the island:
//
//          if (SUPPLIERS_DATA.hasMessage) { Swal.fire({ ... }); }
//
//   2. Values echoed from the island inside that block. `ocp_island_get($__ocp_data, k)`
//      answers null there too, so every field comes out empty. They become plain island
//      reads:
//
//          icon: SUPPLIERS_DATA.swalMessageType,
//
// This handles the regular cases; the awkward ones are listed at the end of the run.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const SCRIPTS = path.join(ROOT, 'assets/js');

const constName = (slug) => slug.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase();
const islandName = (slug) => 'window.OCP_PAGE_' + constName(slug);

const report = [];
const skipped = [];

for (const file of fs.readdirSync(SCRIPTS).filter(f => f.endsWith('.js.php')).sort()) {
  const slug = file.replace(/\.js\.php$/, '');
  const full = path.join(SCRIPTS, file);
  const raw = fs.readFileSync(full, 'utf8');
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  let code = raw.replace(/\r\n/g, '\n');
  const before = code;
  const changes = [];

  // ---- 1. the island handle, once, right after the PHP prologue ---------------
  const HANDLE = `const ${constName(slug)}_DATA = ${islandName(slug)} || {};`;
  if (!code.includes(HANDLE)) {
    const m = /^(\?>)\n/m.exec(code);
    if (m) {
      const insertAt = m.index + m[0].length;
      const block = [
        `// The page's data island. This script is fetched as its own request, so the page's`,
        `// PHP variables are NOT in scope here: anything the page prepared is read from the`,
        `// island it rendered, in the browser.`,
        HANDLE,
        '',
      ].join('\n');
      code = code.slice(0, insertAt) + block + code.slice(insertAt);
      changes.push('added the island handle');
    }
  }

  // ---- 2. Swal.fire blocks: drop the PHP guard, read the island --------------
  // Match "<?php if (<cond>): ?>" ... "<?php endif; ?>" where the body is one Swal.fire.
  const guarded = /^([ \t]*)<\?php if \(([^)]*(?:\([^)]*\))?[^)]*)\): \?>\n([\s\S]*?)\n\1<\?php endif; \?>$/gm;
  code = code.replace(guarded, (match, indent, cond, body) => {
    // Work out which island flag the condition corresponds to.
    let flag = null;
    if (/\$swal_message\b/.test(cond)) flag = 'hasMessage';
    else if (/\$swal_data\b/.test(cond) && !/\$swal_data\[/.test(cond)) flag = 'hasMessage';
    else if (/\$message\b/.test(cond) && !/\$message_type/.test(cond)) flag = 'hasMessage';
    else if (/\$swal_data\[['"]icon['"]\]/.test(cond)) flag = 'isError';
    else if (/REQUEST_METHOD/.test(cond) && /swal_message_type/.test(cond)) flag = 'reopenAddModal';
    else if (/\$edit_supplier_name\b/.test(cond)) flag = 'editSupplierName';
    else if (/\$edit_subcon_name\b/.test(cond)) flag = 'editSubconName';
    else if (/\$edit_expense\b/.test(cond)) flag = 'editExpense';
    else if (/\$view_item\b/.test(cond)) flag = 'openViewModal';
    else if (/\$view_expense\b/.test(cond)) flag = 'openViewModal';
    else if (/\$view_employee\b/.test(cond)) flag = 'openViewModal';
    else if (/\$edit_deductions_data\b/.test(cond)) flag = 'openEditDeductions';
    else if (/\$edit_po_data\b/.test(cond)) flag = 'openEditPo';
    else if (/\$is_motorpool\b/.test(cond)) flag = 'isMotorpool';
    else if (/\$show_modal\b/.test(cond)) flag = 'showModal';
    else if (/\$form_type\b/.test(cond)) flag = null;   // handled per page
    else if (/\$_POST\b/.test(cond)) flag = null;        // handled per page
    else if (/\$alert\b/.test(cond)) flag = 'hasAlert';
    else if (/\$attendance_error\b/.test(cond)) flag = 'hasAttendanceError';
    else if (/\$is_issue_materials\b/.test(cond)) flag = 'isIssueMaterials';

    if (!flag) { skipped.push(`${file}: guard on ${cond.trim()}`); return match; }

    // values inside the body
    const newBody = body.replace(
      /'<\?php echo ocp_js_string\(ocp_island_get\(\$__ocp_data,\s*["']([^"']+)["']\)\); \?>'/g,
      (mm, key) => `${constName(slug)}_DATA.${key}`
    ).replace(
      /<\?php echo ocp_island_get\(\$__ocp_data,\s*["']([^"']+)["']\) \?\? ""; \?>/g,
      (mm, key) => `${constName(slug)}_DATA.${key} || ''`
    ).replace(
      /<\?php echo ocp_js_raw\(ocp_island_get\(\$__ocp_data,\s*["']([^"']+)["']\)\); \?>/g,
      (mm, key) => `${constName(slug)}_DATA.${key}`
    );
    changes.push(`guard on ${cond.trim()} -> ${constName(slug)}_DATA.${flag}`);
    return `${indent}// Tested in the browser: this script is its own request, so the page's\n`
      + `${indent}// variables are not available to it.\n`
      + `${indent}if (${constName(slug)}_DATA.${flag}) {\n${newBody}\n${indent}}`;
  });

  // ---- 3. leftover island echoes inside string literals ----------------------
  code = code.replace(
    /<\?php echo ocp_js_string\(ocp_island_get\(\$__ocp_data,\s*["']([^"']+)["']\)\); \?>/g,
    (mm, key) => `\${${constName(slug)}_DATA.${key} ?? ''}`.replace('${', '${')
  );

  if (code !== before) {
    if (APPLY) fs.writeFileSync(full, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
    report.push({ file, changes });
  }
}

console.log(`${report.length} file(s) changed${APPLY ? '' : ' (dry run)'}:`);
for (const r of report) {
  console.log(`  ${r.file}`);
  for (const c of r.changes) console.log(`      ${c}`);
}
if (skipped.length) {
  console.log(`\n${skipped.length} guard(s) left for hand editing:`);
  for (const s of skipped) console.log(`  ${s}`);
}
