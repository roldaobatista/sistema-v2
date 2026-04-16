import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import tseslint from 'typescript-eslint'
import unusedImports from 'eslint-plugin-unused-imports'
import { defineConfig, globalIgnores } from 'eslint/config'

export default defineConfig([
  globalIgnores(['dist', 'coverage']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      js.configs.recommended,
      tseslint.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    plugins: {
      'unused-imports': unusedImports,
    },
    languageOptions: {
      ecmaVersion: 2020,
      globals: globals.browser,
    },
    rules: {
      '@typescript-eslint/no-explicit-any': 'error',
      // Desabilita o no-unused-vars nativo e usa o plugin unused-imports
      '@typescript-eslint/no-unused-vars': 'off',
      'unused-imports/no-unused-imports': 'error',
      'unused-imports/no-unused-vars': 'off',
      'react-refresh/only-export-components': 'off',
      // Hooks complexos em ERP: deps intencionais, setState em effects é pattern comum com async
      'react-hooks/set-state-in-effect': 'off',
      'react-hooks/incompatible-library': 'off',
      'react-hooks/exhaustive-deps': 'off',
      'react-hooks/use-memo': 'off',
    },
  },
  {
    files: [
      'src/**/*.{test,spec}.{ts,tsx}',
      'src/**/__tests__/**/*.{ts,tsx}',
    ],
    rules: {
      // Dívida histórica de testes: o gate bloqueia `any` no código de produção
      // enquanto os testes legados são tipados gradualmente em findings próprios.
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },
  {
    files: ['e2e/**/*.{ts,tsx}'],
    rules: {
      // Playwright fixtures usam callback `use(...)` por design, sem relação com React hooks.
      'react-hooks/rules-of-hooks': 'off',
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },
])
