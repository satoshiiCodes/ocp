// Makes the user-input reads in actions/registration-actions.php defensive.
//
// The handler bodies were lifted verbatim from registration.php, which read
// $_POST keys directly. A browser sends a complete form, so that was fine; an
// incomplete request raised "Undefined array key" warnings, which PHP printed
// into the JSON response and broke it. `?? ''` gives the same result for a
// complete form and a clean answer for an incomplete one.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\actions\\registration-actions.php';
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

const re = /htmlspecialchars\(trim\(\$_POST\['(\w+)'\]\)\)/g;
const n = [...code.matchAll(re)].length;
console.log(`htmlspecialchars(trim($_POST[...])) reads found: ${n}`);
code = code.replace(re, "htmlspecialchars(trim($_POST['$1'] ?? ''))");

// the remaining direct reads
const more = [
  [/\$password = \$_POST\['password'\];/g, "$password = $_POST['password'] ?? '';"],
  [/\$confirmpassword = \$_POST\['confirmpassword'\];/g, "$confirmpassword = $_POST['confirmpassword'] ?? '';"],
  [/\$id = \$_POST\['id'\];/g, "$id = $_POST['id'] ?? 0;"],
  [/\$user_id = \$_POST\['id'\];/g, "$user_id = $_POST['id'] ?? 0;"],
  [/\$action = \$_POST\['action'\];/g, "$action = $_POST['action'] ?? '';"],
];
for (const [re2, to] of more) {
  const c = [...code.matchAll(re2)].length;
  console.log(`  ${re2.source}: ${c}`);
  code = code.replace(re2, to);
}

const left = [...code.matchAll(/\$_POST\['\w+'\]/g)].filter(m => {
  const after = code.slice(m.index + m[0].length, m.index + m[0].length + 6);
  return !after.startsWith(' ?? ');
}).length;
console.log(`unguarded $_POST reads left: ${left}`);

if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
