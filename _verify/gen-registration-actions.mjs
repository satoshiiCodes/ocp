// Builds actions/registration-actions.php by lifting the four AJAX handlers out of
// registration.php verbatim, so no logic is retyped.
//
// The page's own handler block (lines 33-278) runs in the page's scope, where the
// session and $pdo are already open. registration.js posts straight to the actions
// file, where they are not, so a bootstrap is added for that case.
//
// Each handler is sliced by brace depth rather than by "the next if", because the
// dispatcher's closing brace sits between handlers.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const pageFile = `${ROOT}\\registration.php`;
const lines = fs.readFileSync(pageFile, 'utf8').replace(/\r\n/g, '\n').split('\n');

const first = lines[32];   // line 33
const last = lines[277];   // line 278
if (!/^\s*if \(\$_SERVER\['REQUEST_METHOD'\] === 'POST' && isset\(\$_POST\['action'\]\)\) \{/.test(first)) {
  console.error(`ABORT: line 33 is not the AJAX dispatcher: ${JSON.stringify(first)}`);
  process.exit(1);
}
if (last.trim() !== '}') {
  console.error(`ABORT: line 278 is not a closing brace: ${JSON.stringify(last)}`);
  process.exit(1);
}

/** Brace depth across a line, ignoring braces inside quotes and comments. */
function depthDelta(line) {
  let d = 0;
  let quote = null;
  for (let i = 0; i < line.length; i++) {
    const c = line[i];
    if (quote) {
      if (c === '\\') { i++; continue; }
      if (c === quote) quote = null;
      continue;
    }
    if (c === "'" || c === '"') { quote = c; continue; }
    if (c === '/' && line[i + 1] === '/') break;
    if (c === '#') break;
    if (c === '{') d++;
    else if (c === '}') d--;
  }
  return d;
}

/** The handler starting at 1-based line `start`, ending where its depth returns to 0. */
function handlerAt(start) {
  let d = 0;
  let i = start;
  for (; i <= lines.length; i++) {
    d += depthDelta(lines[i - 1]);
    if (d === 0) break;
  }
  if (d !== 0) throw new Error(`unbalanced handler starting at line ${start}`);
  return lines.slice(start - 1, i).join('\n');
}

// the dispatcher's own depth: handlers sit one level inside it
const marks = [];
let depth = 0;
lines.forEach((l, idx) => {
  const n = idx + 1;
  if (n > 33 && n < 278) {
    const m = /^(\s*)if \(\$action === '(\w+)'\) \{/.exec(l);
    if (m && depth === 1) marks.push({ action: m[2], start: n });
  }
  depth += depthDelta(l);
});

if (marks.length !== 4) {
  console.error(`ABORT: expected 4 handlers, found ${marks.length}: ${marks.map(m => m.action).join(', ')}`);
  process.exit(1);
}

const bodies = marks.map(m => ({ action: m.action, body: handlerAt(m.start) }));
for (const b of bodies) {
  console.log(`  ${b.action.padEnd(12)} lines ${b.body.split('\n').length}`);
}

const header = `<?php
/**
 * actions/registration-actions.php
 *
 * Every action for registration.php lives in this one file: looking a user up for
 * the edit form, and adding, updating and deleting users. All four answer JSON,
 * because the page's JavaScript reads the response.
 *
 * registration.js posts straight to this file, so the bootstrap below opens the
 * session and the database handle when they are not already open. The page also
 * pulls this file in, in which case they are already there.
 *
 * The handler bodies below are lifted verbatim from registration.php: the queries,
 * the validation messages and the JSON shape are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['action'])) {
    return;
}
if (defined('OCP_REGISTRATION_ACTIONS_RAN')) {
    return;
}
define('OCP_REGISTRATION_ACTIONS_RAN', true);

// Works both ways: pulled in by the page, or posted to directly by the page's JS.
$ocp_dir = __DIR__;
for ($ocp_i = 0; $ocp_i < 4 && !is_file($ocp_dir . '/config/db_config.php'); $ocp_i++) {
    $ocp_parent = dirname($ocp_dir);
    if ($ocp_parent === $ocp_dir) {
        break;
    }
    $ocp_dir = $ocp_parent;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($pdo)) {
    require_once $ocp_dir . '/config/db_config.php';
}
unset($ocp_dir, $ocp_i, $ocp_parent);

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$action = $_POST['action'];

`;

let out = header;
for (const b of bodies) {
  out += `// ${'-'.repeat(68)} ${b.action}\n`;
  out += b.body + '\n\n';
}

console.log(`actions file: ${out.length} bytes`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\actions\\registration-actions.php`, out);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
