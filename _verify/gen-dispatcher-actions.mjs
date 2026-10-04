// Builds one of the "single POST dispatcher" actions files.
//
//   node gen-dispatcher-actions.mjs <page> <guard> <fromLine> <toLine> [<helperFrom> <helperTo>] [--apply]
//
// Both pages keep one "if REQUEST_METHOD === POST" block, and neither page's
// JavaScript posts anywhere, so the block moves into the actions file whole. An
// optional helper range is carried across too, before the block that uses it.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const a = process.argv.slice(2).filter(x => x !== '--apply');
const [page, guard, fromArg, toArg, helperFrom, helperTo] = a;

if (!page || !guard || !fromArg || !toArg) {
  console.error('usage: node gen-dispatcher-actions.mjs <page> <GUARD> <from> <to> [<helperFrom> <helperTo>] [--apply]');
  process.exit(2);
}

const lines = fs.readFileSync(`${ROOT}\\${page}.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const at = (n) => lines[n - 1] ?? '';
const FROM = Number(fromArg);
const TO = Number(toArg);

if (!/^\s*if \(\$_SERVER\['REQUEST_METHOD'\] === 'POST'\) \{/.test(at(FROM))) {
  console.error(`ABORT: line ${FROM} is not the POST dispatcher: ${JSON.stringify(at(FROM))}`);
  process.exit(1);
}
if (at(TO).trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(at(TO))}`);
  process.exit(1);
}

let helper = '';
if (helperFrom && helperTo) {
  const hf = Number(helperFrom), ht = Number(helperTo);
  if (!/^\s*\/\/ Function/.test(at(hf))) {
    console.error(`ABORT: line ${hf} is not a helper comment: ${JSON.stringify(at(hf))}`);
    process.exit(1);
  }
  if (at(ht).trim() !== '}') {
    console.error(`ABORT: line ${ht} is not a closing brace: ${JSON.stringify(at(ht))}`);
    process.exit(1);
  }
  helper = lines.slice(hf - 1, ht).join('\n');
}

const title = page.replace(/\.php$/, '');
const dispatcher = lines.slice(FROM - 1, TO).join('\n');

const header = `<?php
/**
 * actions/${title}-actions.php
 *
 * Every action for ${page} lives in this one file.
 *
 * The page pulls this file in at the top, so it runs in the page's scope: the
 * database handle, the session and $_POST behave exactly as they did when this
 * code sat inline. A rejected submission leaves the message variables set and the
 * markup below shows them; a success redirects.
 *
 * The blocks below are lifted verbatim from ${page}: the queries, the messages
 * and the validation are unchanged.
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
$swal_data = []; // For SweetAlert2 data

`;

let out = header;
if (helper) {
  out += `// ${'-'.repeat(68)} helpers\n${helper}\n\n`;
}
out += `// ${'-'.repeat(68)} ${title}\n${dispatcher}\n`;

console.log(`${title}: dispatcher ${FROM}-${TO}${helper ? `, helper ${helperFrom}-${helperTo}` : ''}`);
console.log(`actions file: ${out.length} bytes`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\actions\\${title}-actions.php`, out);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
