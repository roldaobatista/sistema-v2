/**
 * Commitlint configuration for Kalibrium ERP
 *
 * Enforces Conventional Commits standard:
 *   <type>(<scope>): <subject>
 *
 * @see https://www.conventionalcommits.org/
 */
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    // --- Type ---
    'type-enum': [
      2,
      'always',
      [
        'feat',      // New feature
        'fix',       // Bug fix
        'chore',     // Maintenance tasks
        'refactor',  // Code refactoring (no behavior change)
        'style',     // Code style/formatting (no logic change)
        'test',      // Adding or updating tests
        'ci',        // CI/CD changes
        'docs',      // Documentation only
        'perf',      // Performance improvement
        'build',     // Build system or dependencies
        'revert',    // Reverting a previous commit
        'wip',       // Work in progress (discouraged but allowed)
      ],
    ],
    'type-case': [2, 'always', 'lower-case'],
    'type-empty': [2, 'never'],

    // --- Scope (optional, Kalibrium modules) ---
    'scope-case': [2, 'always', 'lower-case'],
    'scope-enum': [
      1, // Warning (not error) — allows new scopes
      'always',
      [
        'auth',        // Authentication & authorization
        'tenant',      // Multi-tenancy
        'finance',     // Financial module
        'inventory',   // Inventory/stock
        'crm',         // CRM module
        'orders',      // Orders (sales/purchase)
        'reports',     // Reporting
        'pwa',         // PWA / mobile
        'portal',      // Client/supplier portals
        'fiscal',      // Fiscal/tax (NF-e, etc.)
        'hr',          // Human resources
        'fleet',       // Fleet management
        'service',     // Service calls/orders
        'workspace',   // Workspace configuration
        'frontend',    // Frontend general
        'backend',     // Backend general
        'api',         // API layer
        'db',          // Database/migrations
        'ci',          // CI/CD pipeline
        'deploy',      // Deployment
        'deps',        // Dependencies
        'audit',       // Audit/security
        'ui',          // UI components
      ],
    ],

    // --- Subject ---
    'subject-case': [2, 'never', ['sentence-case', 'start-case', 'pascal-case', 'upper-case']],
    'subject-empty': [2, 'never'],
    'subject-full-stop': [2, 'never', '.'],

    // --- Header ---
    'header-max-length': [2, 'always', 100],

    // --- Body ---
    'body-leading-blank': [1, 'always'],
    'body-max-line-length': [1, 'always', 200],

    // --- Footer ---
    'footer-leading-blank': [1, 'always'],
    'footer-max-line-length': [1, 'always', 200],
  },
};
