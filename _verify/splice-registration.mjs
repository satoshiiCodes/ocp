// Splices registration.php: lines 12-278 (the user-details load and the AJAX
// dispatcher) become the actions require. The page has no rendering queries.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\registration.php';
const lines = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n').split('\n');

const FROM = 12;
const TO = 278;

const expect = (n, re, what) => {
  if (!re.test(lines[n - 1] ?? '')) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(lines[n - 1])}`);
    process.exit(1);
  }
};
expect(11, /^\s*$/, 'a blank line');
expect(FROM, /^\s*\/\/ Get user details/, 'the user-details comment');
expect(32, /^\s*\/\/ Handle AJAX requests/, 'the AJAX comment');
expect(TO, /^\s*\}\s*$/, 'the closing brace of the dispatcher');
expect(TO + 1, /^\?>/, 'the closing PHP tag');

const block = [
  '    // All of this page\'s actions live in one file: it looks a user up for the edit',
  '    // form and adds, updates and deletes users. registration.js posts straight to',
  '    // that file, and this page pulls it in too, so it runs in this scope.',
  '    if (!defined(\'OCP_REGISTRATION_ACTIONS_RAN\')) {',
  '        require __DIR__ . \'/actions/registration-actions.php\';',
  '    }',
];

const out = [...lines.slice(0, FROM - 1), ...block, ...lines.slice(TO)];
console.log(`registration.php: ${lines.length} -> ${out.length} lines`);
if (APPLY) {
  fs.writeFileSync(file, out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
