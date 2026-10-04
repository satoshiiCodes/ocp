// Gives purchase_request_spare_parts.php the data island its script needs.
//
//   node fix-prsp-island.mjs [--apply]
//
// The page's inline block used to embed six arrays as JSON (allParts, issueParts,
// issueMaterials, formattedEmployees, formattedMechanics, formattedDrivers). When the
// block was extracted, those became ocpRaw(DATA, "...") reads - but the page was never
// given an island, so DATA is not defined and the script throws on its first line: the
// part dropdowns and the edit modal are dead on arrival.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const file = `${ROOT}\\purchase_request_spare_parts.php`;
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

// 1. the page needs the island helper
if (!/includes\/page_data\.php/.test(code)) {
  code = code.replace(
    /^(require_once 'config\/db_config\.php';)$/m,
    `$1\nrequire_once __DIR__ . '/includes/page_data.php';`
  );
}

// 2. render the island just before the scripts that read it
if (!/ocp_page_data\("purchase_request_spare_parts"/.test(code)) {
  const anchor = /^(\s*)<script src="assets\/js\/app\.js"><\/script>$/m;
  const m = anchor.exec(code);
  if (!m) { console.error('ABORT: could not find the app.js script tag'); process.exit(1); }
  const ind = m[1];
  const block = [
    `<?php`,
    `${ind}/* Data island consumed by assets/js/purchase_request_spare_parts.js. The script`,
    `${ind} * is a separate request, so it cannot see this page's variables: the part lists`,
    `${ind} * and the employee lists it builds its dropdowns from are handed over here. */`,
    `${ind}$__ocp_data = [];`,
    `${ind}$__ocp_data["allParts"] = $all_parts ?? [];`,
    `${ind}$__ocp_data["issueParts"] = $issue_parts ?? [];`,
    `${ind}$__ocp_data["issueMaterials"] = $issue_materials ?? [];`,
    `${ind}$__ocp_data["formattedEmployees"] = $formatted_employees ?? [];`,
    `${ind}$__ocp_data["formattedMechanics"] = $formatted_mechanics ?? [];`,
    `${ind}$__ocp_data["formattedDrivers"] = $formatted_drivers ?? [];`,
    `${ind}/* swalData = $swal_data [guarded] */`,
    `${ind}if (!empty($swal_data)) {`,
    `${ind}    $__ocp_data["swalData"] = $swal_data['title'] ?? '';`,
    `${ind}    $__ocp_data["swalData2"] = $swal_data['text'] ?? '';`,
    `${ind}    $__ocp_data["swalData3"] = $swal_data['icon'] ?? '';`,
    `${ind}}`,
    `${ind}ocp_page_data("purchase_request_spare_parts", $__ocp_data);`,
    `${ind}unset($__ocp_data);`,
    `?>`,
    '',
  ].join('\n');
  code = code.slice(0, m.index) + block + code.slice(m.index);
}

if (APPLY) fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);

// 3. the script reads DATA; bind it to the island
const jsFile = `${ROOT}\\assets\\js\\purchase_request_spare_parts.js`;
const jsRaw = fs.readFileSync(jsFile, 'utf8');
const jsEol = jsRaw.includes('\r\n') ? '\r\n' : '\n';
let js = jsRaw.replace(/\r\n/g, '\n');
if (!/const DATA = window\.OCP_PAGE_PURCHASE_REQUEST_SPARE_PARTS/.test(js)) {
  js = js.replace(
    /^        \/\/ PHP parts data for JavaScript$/m,
    `        // The page's data island. This script is a separate request, so the page's\n`
    + `        // PHP variables are NOT in scope: everything it needs is read from the\n`
    + `        // island the page rendered.\n`
    + `        const DATA = window.OCP_PAGE_PURCHASE_REQUEST_SPARE_PARTS || {};\n`
    + `\n`
    + `        // PHP parts data for JavaScript`
  );
}
if (APPLY) fs.writeFileSync(jsFile, jsEol === '\r\n' ? js.replace(/\n/g, '\r\n') : js);

console.log(`  page: island added (${/ocp_page_data\("purchase_request_spare_parts"/.test(code)})`);
console.log(`  script: DATA bound (${/const DATA = window/.test(js)})`);
console.log(APPLY ? 'APPLIED' : 'DRY RUN');
