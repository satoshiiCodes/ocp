// Adds ?? null to every entry of the dashboard endpoint's return list.
//
// Two variables in the list ($late_threshold and $current_year) are assigned inside
// role blocks only, so a viewer whose branch did not run never defines them. Reading
// each entry with ?? null hands back null for those and leaves every other value
// untouched.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const file = `${ROOT}\\api\\dashboard-endpoint.php`;
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

const before = [...code.matchAll(/^    '([a-z_]+)' => \$([a-z_]+),$/gm)];
console.log(`entries in the return list: ${before.length}`);
if (!before.length) {
  console.error('ABORT: no entries matched; the list shape has changed');
  process.exit(1);
}

code = code.replace(/^(    '([a-z_]+)' => \$([a-z_]+)),$/gm, '$1 ?? null,');

const after = [...code.matchAll(/^    '([a-z_]+)' => \$([a-z_]+) \?\? null,$/gm)];
console.log(`entries now guarded: ${after.length}`);
if (after.length !== before.length) {
  console.error('ABORT: not every entry was guarded');
  process.exit(1);
}

// the guard must sit inside the return list, not somewhere else
if (!/return \[\n(    '[a-z_]+' => \$[a-z_]+ \?\? null,\n)+\];/.test(code)) {
  console.error('ABORT: the return list does not look right after the change');
  process.exit(1);
}

if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
