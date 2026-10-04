// Lifts a contiguous run of top-level blocks out of a page verbatim.
//
//   node lift-blocks.mjs <page> <label:startLine> [<label:startLine> ...] --last <endLine> [--apply]
//
// Each block is sliced to where its brace depth returns to zero, so the closing
// braces are always captured. Blocks are printed, not written: the caller pastes
// them into the actions file.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const args = process.argv.slice(2).filter(a => a !== '--apply');
const [page, ...rest] = args;
const lastIdx = rest.indexOf('--last');
const endLine = Number(rest[lastIdx + 1]);
const labels = rest.slice(0, lastIdx);

const lines = fs.readFileSync(`${ROOT}\\${page}.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const indent = Number(process.env.OCP_INDENT || 0);

function depthDelta(line) {
  let d = 0, quote = null;
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

function blockAt(start) {
  let d = 0, i = start;
  for (; i <= lines.length; i++) {
    d += depthDelta(lines[i - 1]);
    if (d === 0) break;
  }
  if (d !== 0) throw new Error(`unbalanced block at line ${start}`);
  return lines.slice(start - 1, i);
}

const pad = ' '.repeat(indent);
const out = [];
let prevEnd = 0;
for (const item of labels) {
  const [label, startStr] = item.split(':');
  const start = Number(startStr);
  if (!Number.isFinite(start) || start < 1) { console.error(`bad start: ${item}`); process.exit(2); }
  if (start <= prevEnd) { console.error(`block ${label} overlaps the previous one`); process.exit(2); }
  const body = blockAt(start);
  prevEnd = start + body.length - 1;
  out.push(`// ${'-'.repeat(64)} ${label}`);
  out.push(...body.map(l => (l.trim() ? pad + l : l)));
  out.push('');
}
// sanity: the blocks must sit inside the stated range and stop before the end line
if (prevEnd >= endLine) {
  console.error(`ABORT: the last block ends at ${prevEnd}, at or past --last ${endLine}`);
  process.exit(1);
}
console.log(out.join('\n'));
console.error(`# ${labels.length} block(s), lines up to ${prevEnd}, end marker ${endLine}`);
