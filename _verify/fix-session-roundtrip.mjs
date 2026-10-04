// Restores the session-message read to the four pages that report by redirecting.
//
//   node fix-session-roundtrip.mjs [--apply]
//
// Each original page read $_SESSION['swal_data'] in its own preamble, so a handler could
// store a message, redirect, and have the page it landed on show it. After the split that
// read lives only in the actions file, which runs on a POST - so on the redirected GET the
// message was never picked up and the page showed nothing.
//
// The read is put back in the page, immediately after the login guard, so it happens on
// every load and before the endpoint runs.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const PAGES = ['gasoline_purchase_order', 'inventory', 'purchase_request', 'purchase_request_spare_parts'];

const BLOCK = `
// Check for session-based SweetAlert data. This belongs in the page, not only in the
// actions file: a handler that stores a message and redirects is answered by a fresh GET,
// where the actions file does not run - so the message has to be picked up here.
if (!isset($swal_data) || !is_array($swal_data) || $swal_data === []) {
    $swal_data = array();
}
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}
`;

for (const slug of PAGES) {
  const file = `${ROOT}\\${slug}.php`;
  const raw = fs.readFileSync(file, 'utf8');
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  let code = raw.replace(/\r\n/g, '\n');

  if (/if \(isset\(\$_SESSION\['swal_data'\]\)\)/.test(code)) {
    console.log(`  ${slug}.php: already reads the session`);
    continue;
  }

  // insert after the login guard, before the actions require
  const anchor = /^if \(!defined\('OCP_[A-Z_]+_ACTIONS_RAN'\)\) \{$/m;
  const m = anchor.exec(code);
  if (!m) { console.log(`  ${slug}.php: could not find the actions require`); continue; }

  code = code.slice(0, m.index) + BLOCK.trimStart() + '\n' + code.slice(m.index);
  if (APPLY) fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log(`  ${slug}.php: session read restored`);
}
console.log(APPLY ? 'APPLIED' : 'DRY RUN');
