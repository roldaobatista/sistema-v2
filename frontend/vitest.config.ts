import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './src'),
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: ['./src/__tests__/setup.ts'],
        include: ['src/**/*.{test,spec}.{ts,tsx}'],
        css: false,
        testTimeout: 15000,
        hookTimeout: 15000,
        // The suite has many file-level mocks for '@/lib/api'. Keep file execution
        // serialized until those mocks are migrated to isolated MSW fixtures.
        maxWorkers: 1,
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html', 'lcov'],
            exclude: [
                'node_modules/**',
                'src/__tests__/**',
                'src/main.tsx',
                '**/*.d.ts',
                '**/*.config.*',
            ],
        },
    },
})
