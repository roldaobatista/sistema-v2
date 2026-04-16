/**
 * Fix: Move misplaced safeArray imports that were inserted inside multi-line import blocks
 *
 * Fixes pattern:
 *   import {
 *   import { safeArray } from '@/lib/safe-array'
 *       SomeIcon, ...
 *   } from 'lucide-react'
 *
 * Into:
 *   import { safeArray } from '@/lib/safe-array'
 *   import {
 *       SomeIcon, ...
 *   } from 'lucide-react'
 */
const fs = require('fs');
const path = require('path');

const PAGES_DIR = path.join(__dirname, '..', 'src', 'pages');
let fixed = 0;

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

function fixFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf-8');
    const original = content;

    // Pattern: "import {\nimport { safeArray } from '@/lib/safe-array'\n"
    // Should become: "import { safeArray } from '@/lib/safe-array'\nimport {\n"
    const badPattern = /import\s*\{\s*\n\s*import\s*\{\s*safeArray\s*\}\s*from\s*'@\/lib\/safe-array'\s*\n/g;

    if (badPattern.test(content)) {
        content = content.replace(badPattern, "import { safeArray } from '@/lib/safe-array'\nimport {\n");
        fs.writeFileSync(filePath, content, 'utf-8');
        fixed++;
        const relPath = path.relative(PAGES_DIR, filePath);
        console.log(`  ✅ Fixed: ${relPath}`);
    }
}

console.log('🔧 Fixing misplaced safeArray imports...\n');

const files = walkDir(PAGES_DIR);
files.forEach(fixFile);

console.log(`\n📊 Fixed ${fixed} files`);
