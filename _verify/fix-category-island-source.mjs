// Points the two category pages' island at the variable that actually holds the message.
//
//   node fix-category-island-source.mjs [--apply]
//
// The island tested isset($_SESSION['sweetalert']) - but the page reads that key into
// $sweetalert and unsets it near the top, so by island time the test was false and the
// title, text and icon were never published. hasMessage came from $sweetalert and was
// true, so the script fired Swal.fire with three undefined values: a blank alert.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

for (const slug of ['items_categories', 'spare_parts_categories']) {
  const file = `${ROOT}\\${slug}.php`;
  const raw = fs.readFileSync(file, 'utf8');
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  let code = raw.replace(/\r\n/g, '\n');
  const before = code;

  code = code.replace(
    /^([ \t]*)\/\* sESSIONSweetalertTitle = \$_SESSION\['sweetalert'\]\['title'\] \[guarded\] \*\/\n[ \t]*if \(isset\(\$_SESSION\['sweetalert'\]\)\) \{\n[ \t]*\$__ocp_data\["sESSIONSweetalertTitle"\] = \$_SESSION\['sweetalert'\]\['title'\];\n[ \t]*\}$/m,
    (m, ind) => `${ind}/* Read from $sweetalert, not $_SESSION: the page has already consumed and\n`
      + `${ind} * unset the session copy by this point. */\n`
      + `${ind}if (!empty($sweetalert)) {\n`
      + `${ind}    $__ocp_data["sESSIONSweetalertTitle"] = $sweetalert['title'] ?? '';\n`
      + `${ind}}`);
  code = code.replace(
    /^([ \t]*)\/\* sESSIONSweetalertText = \$_SESSION\['sweetalert'\]\['text'\] \[guarded\] \*\/\n[ \t]*if \(isset\(\$_SESSION\['sweetalert'\]\)\) \{\n[ \t]*\$__ocp_data\["sESSIONSweetalertText"\] = \$_SESSION\['sweetalert'\]\['text'\];\n[ \t]*\}$/m,
    (m, ind) => `${ind}if (!empty($sweetalert)) {\n`
      + `${ind}    $__ocp_data["sESSIONSweetalertText"] = $sweetalert['text'] ?? '';\n`
      + `${ind}}`);
  code = code.replace(
    /^([ \t]*)\/\* sESSIONSweetalertIcon = \$_SESSION\['sweetalert'\]\['icon'\] \[guarded\] \*\/\n[ \t]*if \(isset\(\$_SESSION\['sweetalert'\]\)\) \{\n[ \t]*\$__ocp_data\["sESSIONSweetalertIcon"\] = \$_SESSION\['sweetalert'\]\['icon'\];\n[ \t]*\}$/m,
    (m, ind) => `${ind}if (!empty($sweetalert)) {\n`
      + `${ind}    $__ocp_data["sESSIONSweetalertIcon"] = $sweetalert['icon'] ?? '';\n`
      + `${ind}}`);

  if (code === before) { console.log(`  ${slug}.php: no change made - check the pattern`); continue; }
  if (APPLY) fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  const left = (code.match(/\$_SESSION\['sweetalert'\]/g) || []).length;
  console.log(`  ${slug}.php: island now reads $sweetalert (${left} session reference(s) left)`);
}
console.log(APPLY ? 'APPLIED' : 'DRY RUN');
