// Builds the actions file for the two spare-parts pages by lifting the dispatcher
// verbatim.
//
//   node gen-spare-parts-actions.mjs <page> <fromLine> <toLine> <GUARD> [--apply]
//
// Both pages keep a single "if REQUEST_METHOD === POST" block that dispatches on
// $_POST keys, and neither page's JavaScript posts anywhere, so the block can move
// into the actions file whole.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const args = process.argv.slice(2).filter(a => a !== '--apply');
const [page, fromArg, toArg, guard] = args;

if (!page || !fromArg || !toArg || !guard) {
  console.error('usage: node gen-spare-parts-actions.mjs <page> <from> <to> <GUARD> [--apply]');
  process.exit(2);
}

const lines = fs.readFileSync(`${ROOT}\\${page}.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const FROM = Number(fromArg);
const TO = Number(toArg);

const at = (n) => lines[n - 1] ?? '';
if (!/^\s*\/\/ Process form submission/.test(at(FROM))) {
  console.error(`ABORT: line ${FROM} is not the form-submission comment: ${JSON.stringify(at(FROM))}`);
  process.exit(1);
}
if (at(TO).trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(at(TO))}`);
  process.exit(1);
}

const title = page.replace(/\.php$/, '');
const body = lines.slice(FROM - 1, TO).join('\n');

const header = `<?php
/**
 * actions/${title}-actions.php
 *
 * Every action for ${page} lives in this one file.
 *
 * The page pulls this file in at the top, so it runs in the page's scope: the
 * database handle, the session and $_POST behave exactly as they did when this
 * code sat inline. A rejected submission leaves its message set and the markup
 * below repopulates the form from $_POST; a success redirects.
 *
 * The block below is lifted verbatim from ${page}: the queries, the messages and
 * the validation are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('${guard}')) {
    return;
}
define('${guard}', true);

// The message variables the page's markup reads. Both are filled in below.
$message = '';
$message_type = '';

`;

const out = header + body + '\n';
console.log(`${title}: lifted lines ${FROM}-${TO} (${TO - FROM + 1})`);
console.log(`actions file: ${out.length} bytes`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\actions\\${title}-actions.php`, out);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
