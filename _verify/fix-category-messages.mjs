// Gives the two category pages the session read their island flag depends on.
//
//   node fix-category-messages.mjs [--apply]
//
// Both pages set $__ocp_data["hasMessage"] from $sweetalert - a variable neither page ever
// assigns. It was always false, so the alert the actions file stores in
// $_SESSION['sweetalert'] was never shown, and the key was never consumed either, so it
// would have kept firing had the flag been right. The read is added after the login guard.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const BLOCK = `
// The message the actions file leaves behind. It is read here, on every load, because a
// handler that stores it and redirects is answered by a fresh GET - and it is unset so it
// is shown once.
$sweetalert = [];
if (isset($_SESSION['sweetalert'])) {
    $sweetalert = $_SESSION['sweetalert'];
    unset($_SESSION['sweetalert']);
}
`;

for (const slug of ['items_categories', 'spare_parts_categories']) {
  const file = `${ROOT}\\${slug}.php`;
  const raw = fs.readFileSync(file, 'utf8');
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  let code = raw.replace(/\r\n/g, '\n');

  if (/\$sweetalert = \[\]/.test(code) || /\$sweetalert\s*=/.test(code)) {
    console.log(`  ${slug}.php: already reads it`);
    continue;
  }
  const anchor = /^if \(!defined\('OCP_[A-Z_]+_ACTIONS_RAN'\)\) \{$/m;
  const m = anchor.exec(code);
  if (!m) { console.log(`  ${slug}.php: no actions require found`); continue; }

  code = code.slice(0, m.index) + BLOCK.trimStart() + '\n' + code.slice(m.index);
  if (APPLY) fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log(`  ${slug}.php: session read added`);
}
console.log(APPLY ? 'APPLIED' : 'DRY RUN');
