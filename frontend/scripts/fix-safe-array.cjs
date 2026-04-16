/**
 * Codemod v3: Replace ALL `EXPR ?? []` with safeArray(EXPR)
 *
 * Strategy:
 *   1. Find all `?? []` occurrences in non-import, non-comment lines
 *   2. Extract the EXPR before `?? []` (handling nested parens/brackets)
 *   3. Replace with safeArray(EXPR)
 *   4. Add import at proper location (after last complete single-line import)
 */
const fs = require('fs');
const path = require('path');

const PAGES_DIR = path.join(__dirname, '..', 'src', 'pages');
const ALREADY_FIXED = [
    'CentralPage.tsx',
    'CrmAlertsPage.tsx',
    'CrmScoringPage.tsx',
    'CrmGoalsPage.tsx',
    'CrmProposalsPage.tsx',
];

let totalFiles = 0;
let modifiedFiles = 0;
let totalReplacements = 0;
const report = [];
const errors = [];

function walkDir(dir) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    const files = [];
    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            files.push(...walkDir(fullPath));
        } else if (entry.name.endsWith('.tsx') || entry.name.endsWith('.ts')) {
            files.push(fullPath);
        }
    }
    return files;
}

/**
 * Find the last import line that is a COMPLETE single-line import.
 * Avoids inserting inside multi-line import blocks.
 */
function findImportInsertPosition(lines) {
    let lastCompleteImport = -1;
    let insideMultiLineImport = false;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();

        // Track multi-line imports
        if (line.startsWith('import ') && !line.includes(' from ')) {
            insideMultiLineImport = true;
            continue;
        }
        if (insideMultiLineImport) {
            if (line.includes(" from '") || line.includes(' from "')) {
                insideMultiLineImport = false;
                lastCompleteImport = i;
            }
            continue;
        }

        // Single-line import
        if (line.startsWith('import ') && (line.includes(" from '") || line.includes(' from "'))) {
            lastCompleteImport = i;
            continue;
        }

        // Stop after imports section (first non-import, non-empty, non-type line)
        if (lastCompleteImport > -1 && line !== '' && !line.startsWith('import ') && !line.startsWith('type ') && !line.startsWith('//') && !line.startsWith('*')) {
            break;
        }
    }

    return lastCompleteImport + 1;
}

function processFile(filePath) {
    const basename = path.basename(filePath);
    if (ALREADY_FIXED.includes(basename)) return;

    totalFiles++;
    let content = fs.readFileSync(filePath, 'utf-8');
    const original = content;
    let fileReplacements = 0;

    // Global replace: `EXPR ?? []` where EXPR does NOT start with `safeArray(`
    // Match pattern: any expression ending with `?? []`
    // We need to be careful about what we capture as EXPR

    const lines = content.split('\n');
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const trimmed = line.trim();

        // Skip comments, imports, and empty lines
        if (trimmed.startsWith('//') || trimmed.startsWith('*') || trimmed.startsWith('import ') || trimmed === '') continue;
        // Skip lines already using safeArray
        if (line.includes('safeArray(')) continue;

        // Replace pattern: `someExpr ?? []`
        // The regex captures everything before `?? []` on the right side of = or :
        // Use a non-greedy approach: find `?? []` and work backwards
        let newLine = line;
        let pos = 0;

        while (true) {
            const idx = newLine.indexOf('?? []', pos);
            if (idx === -1) break;

            // Find the start of the expression before `?? []`
            // Walk backwards from idx, tracking parens/brackets
            let start = idx - 1;
            // Skip whitespace
            while (start >= 0 && newLine[start] === ' ') start--;

            // Now find the start of the expression
            let parenDepth = 0;
            let bracketDepth = 0;
            let exprEnd = start + 1;

            while (start >= 0) {
                const ch = newLine[start];
                if (ch === ')') parenDepth++;
                else if (ch === '(') {
                    if (parenDepth === 0) { start++; break; }
                    parenDepth--;
                }
                else if (ch === ']') bracketDepth++;
                else if (ch === '[') {
                    if (bracketDepth === 0) { start++; break; }
                    bracketDepth--;
                }
                else if (parenDepth === 0 && bracketDepth === 0) {
                    // Stop at operators, assignment, comma, etc.
                    if (ch === '=' || ch === ',' || ch === ';' || ch === '{' || ch === ':' || ch === '|' || ch === '&') {
                        start++;
                        break;
                    }
                }
                start--;
            }
            if (start < 0) start = 0;

            // Skip leading whitespace in the expression
            while (start < exprEnd && newLine[start] === ' ') start++;

            const expr = newLine.substring(start, exprEnd).trim();

            // Don't wrap constants like `OPERATORS` or simple vars that are clearly arrays
            if (!expr || expr === '[]' || /^[A-Z_]+$/.test(expr)) {
                pos = idx + 5;
                continue;
            }

            // Replace: `EXPR ?? []` with `safeArray(EXPR)`
            const before = newLine.substring(0, start);
            const after = newLine.substring(idx + 5); // after `?? []`
            newLine = `${before}safeArray(${expr})${after}`;
            fileReplacements++;

            // Update pos for next search
            pos = before.length + `safeArray(${expr})`.length;
        }

        lines[i] = newLine;
    }

    if (fileReplacements > 0) {
        // Add import if needed
        if (!lines.join('\n').includes("from '@/lib/safe-array'")) {
            const insertPos = findImportInsertPosition(lines);
            lines.splice(insertPos, 0, "import { safeArray } from '@/lib/safe-array'");
        }

        const newContent = lines.join('\n');
        fs.writeFileSync(filePath, newContent, 'utf-8');
        modifiedFiles++;
        totalReplacements += fileReplacements;
        const relPath = path.relative(PAGES_DIR, filePath);
        report.push(`  ✅ ${relPath} (${fileReplacements} replacements)`);
    }
}

console.log('🔍 Codemod v3: Scanning pages directory...\n');

const files = walkDir(PAGES_DIR);
files.forEach(processFile);

console.log('📊 Results:');
console.log(`   Total files scanned: ${totalFiles}`);
console.log(`   Files modified: ${modifiedFiles}`);
console.log(`   Total replacements: ${totalReplacements}`);
console.log('');
if (report.length > 0) {
    console.log('📝 Modified files:');
    report.forEach(r => console.log(r));
}
if (errors.length > 0) {
    console.log('\n⚠️ Errors:');
    errors.forEach(e => console.log(e));
}
