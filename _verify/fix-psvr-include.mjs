// Rebuilds includes/pr_spare_view_routing-functions.php from the pre-restructure page.
//
//   node fix-psvr-include.mjs [--apply]
//
// The first build over-extracted: the page's read block has a different leading comment
// ("// Fetch PR details - ADDED JOIN FOR TECHNICIAN AND DRIVER") than the marker the
// builder looked for, so the helper region ran to the end of the file and swallowed the
// whole template. Requiring that include rendered the page a second time (142 KB instead
// of 71 KB) and left a duplicated <!DOCTYPE>.
//
// The helpers are taken from _restructure_backup/, which is the source of truth; the two
// functions the markup also calls (getStatusBadge, formatUserName) are taken from the
// current include, where they were moved by the actions build.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const SLUG = 'pr_spare_view_routing';
const CONST = 'OCP_PR_SPARE_VIEW_ROUTING';

const backup = fs.readFileSync(`${ROOT}\\_restructure_backup\\${SLUG}.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');

// the helper region: from the first "// Function to" after the id guard, to the fetch comment
const startIdx = backup.findIndex((l, i) => i > 30 && /^\/\/ Function to /.test(l));
const endIdx = backup.findIndex((l, i) => i > startIdx && /^\/\/ Fetch PR details/.test(l));
if (startIdx < 0 || endIdx < 0) { console.error('ABORT: could not locate the helper region in the backup'); process.exit(1); }
const helpers = backup.slice(startIdx, endIdx);
const helperNames = [...helpers.join('\n').matchAll(/^function (\w+)\(/gm)].map(m => m[1]);
console.log(`helpers from the backup: lines ${startIdx + 1}-${endIdx} (${helperNames.length})`);
console.log(`  ${helperNames.join(', ')}`);

// getStatusBadge and formatUserName: the markup calls them too, so they live in the include
const cur = fs.readFileSync(`${ROOT}\\includes\\${SLUG}-functions.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const grab = (name) => {
  const i = cur.findIndex(l => new RegExp(`^function ${name}\\(`).test(l));
  if (i < 0) return null;
  let depth = 0, began = false, end = i;
  for (let j = i; j < cur.length; j++) {
    depth += (cur[j].match(/\{/g) || []).length - (cur[j].match(/\}/g) || []).length;
    if (cur[j].includes('{')) began = true;
    if (began && depth === 0) { end = j; break; }
  }
  return cur.slice(i, end + 1);
};
const extra = [];
for (const name of ['getStatusBadge', 'formatUserName']) {
  const body = grab(name);
  if (!body) { console.error(`ABORT: could not find ${name}()`); process.exit(1); }
  extra.push(body.join('\n'));
  console.log(`  also carried over: ${name}() (${body.length} lines)`);
}

const out = `<?php
/**
 * includes/${SLUG}-functions.php
 *
 * The helpers ${SLUG}.php and its actions file share: the document-number generators,
 * the date, request-type and status formatters, the stock-availability check, and
 * getStatusBadge() and formatUserName(), which the markup calls too.
 *
 * They live in their own file because both entry points need them. The routing handlers
 * call several of them after they write; the page's template calls the rest while
 * rendering. They must therefore be loaded before whichever runs first, which is why
 * actions/${SLUG}-actions.php and api/${SLUG}-endpoint.php both require this file.
 *
 * The bodies below are lifted verbatim from ${SLUG}.php. Nothing here prints, so this
 * file cannot disturb the page's output.
 */

if (defined('${CONST}_FUNCTIONS_LOADED')) {
    return;
}
define('${CONST}_FUNCTIONS_LOADED', true);

${helpers.join('\n')}

${extra.join('\n\n')}
`;

console.log(`\nincludes/${SLUG}-functions.php: ${out.length} bytes, ${out.split('\n').length} lines`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\includes\\${SLUG}-functions.php`, out);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
