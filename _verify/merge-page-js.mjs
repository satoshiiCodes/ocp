// Merges assets/js/employee_registration-2.js.php into
// assets/js/employee_registration.js.php so the page has ONE script file.
//
// The two blocks share no top-level declarations (checked: the first declares
// only showDeductionSection, the second only its own modal/table handles), so
// concatenating their bodies keeps both working exactly as before. Only the
// first block keeps the PHP bootstrap.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const JS = path.join(ROOT, 'assets/js');

const mainFile = path.join(JS, 'employee_registration.js.php');
const extraFile = path.join(JS, 'employee_registration-2.js.php');
const pageFile = path.join(ROOT, 'employee_registration.php');

const main = fs.readFileSync(mainFile, 'utf8');
const extra = fs.readFileSync(extraFile, 'utf8');

/** Everything after the bootstrap's closing tag: the JavaScript body. */
function bodyOf(code) {
  const i = code.indexOf('?>');
  if (i === -1) return code;
  return code.slice(i + 2).replace(/^\s*\n/, '');
}

const mainBody = bodyOf(main);
const extraBody = bodyOf(extra);

// Confirm the merge is safe: no shared top-level names.
const decls = (js) => new Set([...js.matchAll(/^[ \t]*(?:const|let|var|function)[ \t]+([A-Za-z_$][\w$]*)/gm)].map(m => m[1]));
const a = decls(mainBody);
const b = decls(extraBody);
const clash = [...b].filter(n => a.has(n));
if (clash.length) {
  console.error(`ABORT: top-level name clash: ${clash.join(', ')}`);
  process.exit(1);
}
console.log(`merged declarations: ${[...a, ...b].join(', ')}`);

const separator = [
  '',
  '/* ------------------------------------------------------------------',
  ' * Second inline <script> block of employee_registration.php, merged in',
  ' * so this page has a single script file. It reuses the island built',
  ' * above, so it carries no bootstrap of its own.',
  ' * ------------------------------------------------------------------ */',
  '',
].join('\n');

const merged = main.replace(/\s*$/, '\n') + separator + extraBody;
const page = fs.readFileSync(pageFile, 'utf8');

// The two blocks sat at different points in the page: the first just after the
// modals for the add form, the second at the very bottom after the modal markup.
// The merged file therefore takes the LATER position, so its code still runs
// once every modal element exists.
const earlyTag = /[ \t]*<script src="assets\/js\/employee_registration\.js\.php"><\/script>\r?\n/;
const lateTag = /([ \t]*)<script src="assets\/js\/employee_registration-2\.js\.php"><\/script>/;

if (!earlyTag.test(page) || !lateTag.test(page)) {
  console.error('ABORT: could not find both script tags in the page');
  process.exit(1);
}

const pageFixed = page
  .replace(earlyTag, '')                                   // drop the early tag
  .replace(lateTag, '$1<script src="assets/js/employee_registration.js.php"></script>'); // keep the late one

console.log(`main   : ${main.length} -> ${merged.length} bytes`);
console.log(`extra  : ${extra.length} bytes removed`);
console.log(`page   : two script tags -> one`);

if (APPLY) {
  fs.writeFileSync(mainFile, merged);
  fs.unlinkSync(extraFile);
  fs.writeFileSync(pageFile, pageFixed);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
