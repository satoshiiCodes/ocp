// Restores one server-rendered script from its page's original inline block.
//
//   node restore-script-from-backup.mjs <page.php> [--apply]
//
// The codemod's guard regex was greedy: on a page whose inline block is an
// if/elseif/else chain (the dashboard's role-gated charts, vehicles' modal choice), it
// matched from the first `if` to a distant `endif`, converted the body and left the
// `elseif` arms orphaned - three files stopped parsing. This puts the original block
// back verbatim, with the header the other scripts carry.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const page = process.argv.slice(2).filter(a => a !== '--apply')[0];
if (!page) { console.error('usage: node restore-script-from-backup.mjs <page> [--apply]'); process.exit(2); }
const slug = page.replace(/\.php$/, '');

const backup = fs.readFileSync(`${ROOT}\\_restructure_backup\\${page}`, 'utf8').replace(/\r\n/g, '\n');
const blocks = [];
const re = /<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/g;
let m;
while ((m = re.exec(backup)) !== null) blocks.push(m[1]);
if (blocks.length !== 1) { console.error(`ABORT: expected 1 inline block, found ${blocks.length}`); process.exit(1); }

const body = blocks[0].replace(/^\n/, '').replace(/\s+$/, '');

// The header the generated scripts carry: it loads the island helpers and silences
// notices when the file is requested on its own.
const header = `/* ${slug}.js
 * Extracted from ${page} - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_${slug.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase()}
 * (rendered by includes/page_data.php).
 *
 * This file is a PHP script that prints JavaScript: PHP and JavaScript were
 * interleaved inside string literals in the original inline block, so the
 * PHP islands are kept and their values are read from the page data island
 * ($__ocp_data). It therefore has to be served through PHP, which is why it
 * is named .js.php.
 */
<?php
/* Load the data-island helpers: walk up from this file until includes/ is found. */
if (!function_exists('ocp_js_raw')) {
    $__ocp_dir = __DIR__;
    for ($__ocp_i = 0; $__ocp_i < 6; $__ocp_i++) {
        if (is_file($__ocp_dir . '/includes/page_data.php')) {
            require_once $__ocp_dir . '/includes/page_data.php';
            break;
        }
        $__ocp_parent = dirname($__ocp_dir);
        if ($__ocp_parent === $__ocp_dir) {
            break;
        }
        $__ocp_dir = $__ocp_parent;
    }
    unset($__ocp_dir, $__ocp_i, $__ocp_parent);
}
/*
 * Printed as part of its page, $__ocp_data is already the data island and
 * every page variable this script reads is in scope.
 *
 * Requested on its own (a direct hit or a cache miss) there is no page, no
 * island and none of those variables, so notices are silenced, the island
 * starts empty, and missing members answer null. The response still parses as
 * JavaScript; it simply does nothing.
 */
if (!isset($__ocp_data)) {
    $__ocp_data = [];
    if (isset($_SERVER['SCRIPT_FILENAME'])
        && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
        $GLOBALS['OCP_SCRIPT_STANDALONE'] = true;
        header('Content-Type: application/javascript; charset=utf-8');
        ini_set('display_errors', '0');
        error_reporting(0);
    }
}
?>`;

const out = header + '\n' + body + '\n';
console.log(`${slug}.js.php: ${out.split('\n').length} lines, ${out.length} bytes`);
if (APPLY) {
  fs.writeFileSync(path.join(ROOT, 'assets/js', `${slug}.js.php`), out);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
