// Finds endpoints whose "is this the AJAX call?" test would also fire for one of the page's
// own form posts.
//
//   node audit-endpoint-trigger.mjs
//
// api/gasoline_purchase_order-endpoint.php tested `array_key_exists('po_id', $_POST)`. Every
// form on that page carries po_id, so approve_po / delete_po / complete_po / update_invoice /
// cancel_po / issue_gasoline were all answered by the single-PO lookup instead of falling
// through to the page's listing: the handlers never ran, and the browser rendered the
// lookup's answer - a PO-details fragment, or {"error":"Purchase Order not found"} - as the
// page. An endpoint that answers JSON must key on something only its AJAX caller sends.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const findings = [];

for (const file of fs.readdirSync(path.join(ROOT, 'api')).filter(f => f.endsWith('.php')).sort()) {
  const slug = file.replace(/-endpoint\.php$/, '');
  const src = fs.readFileSync(path.join(ROOT, 'api', file), 'utf8');
  if (!/Content-Type: application\/json/.test(src)) continue;

  // what the endpoint treats as its AJAX trigger. A negated test is the opposite - it is how
  // an endpoint guards itself against the page's own posts - so it is not a trigger.
  const triggers = [...src.matchAll(/(?<!!)\barray_key_exists\(\s*'([A-Za-z0-9_]+)'\s*,\s*\$_POST\s*\)/g)].map(m => m[1]);
  const issetTriggers = [...src.matchAll(/(?<!!)\bisset\(\$_POST\[['"]([A-Za-z0-9_]+)['"]\]\)/g)].map(m => m[1]);
  const all = [...new Set([...triggers, ...issetTriggers])];
  if (!all.length) continue;

  // the form fields on the page, and whether an action field is present
  const page = path.join(ROOT, `${slug}.php`);
  const pageSrc = fs.existsSync(page) ? fs.readFileSync(page, 'utf8') : '';
  const names = new Set([...pageSrc.matchAll(/name="([A-Za-z0-9_\[\]]+)"/g)].map(m => m[1]));
  const hasActionField = names.has('action');

  for (const t of all) {
    if (!names.has(t)) continue;
    // a trigger that is also a form field on the page, with no guard against the page's
    // own action post, will swallow that post
    const guarded = new RegExp(`array_key_exists\\(\\s*'${t}'[\\s\\S]{0,120}?!array_key_exists\\(\\s*'action'`).test(src)
      || new RegExp(`!isset\\(\\$_POST\\['action'\\]\\)[\\s\\S]{0,160}?isset\\(\\$_POST\\['${t}'\\]\\)`).test(src);
    findings.push({ file, trigger: t, hasActionField, guarded });
  }
}

const unguarded = findings.filter(f => !f.guarded);
if (!findings.length) {
  console.log('OK: no endpoint keys its JSON answer on a field the page also posts');
} else if (!unguarded.length) {
  console.log(`OK: ${findings.length} endpoint key(s) the page also posts, and every one is guarded`);
  for (const f of findings) {
    console.log(`  ${f.file.padEnd(42)} trigger "${f.trigger}"  page posts it  action field=${f.hasActionField}  guarded against an action post=${f.guarded}`);
  }
} else {
  console.log(`${unguarded.length} endpoint(s) whose JSON trigger is also a form field, with no guard:`);
  for (const f of unguarded) {
    console.log(`  ${f.file.padEnd(42)} trigger "${f.trigger}"  page posts it  action field=${f.hasActionField}`);
  }
  console.log('\n  such an endpoint answers the page\'s own form post with JSON, so the form never submits.');
}
process.exit(unguarded.length ? 1 : 0);
