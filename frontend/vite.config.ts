import path from 'path'
import react from '@vitejs/plugin-react'
import { sentryVitePlugin } from '@sentry/vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig, loadEnv } from 'vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const sentryRelease = env.SENTRY_RELEASE || env.VITE_SENTRY_RELEASE
  const sentryEnabled = mode === 'production' && env.SENTRY_AUTH_TOKEN && env.SENTRY_ORG && env.SENTRY_PROJECT
  const apiProxyTarget =
    env.VITE_PROXY_TARGET ||
    env.E2E_API_ORIGIN ||
    env.E2E_API_BASE?.replace(/\/api\/v1\/?$/, '') ||
    'http://127.0.0.1:8010'

  return {
    plugins: [
      react(),
      tailwindcss(),
      ...(sentryEnabled
        ? [sentryVitePlugin({
            authToken: env.SENTRY_AUTH_TOKEN,
            org: env.SENTRY_ORG,
            project: env.SENTRY_PROJECT,
            url: env.SENTRY_URL,
            release: {
              name: sentryRelease,
              inject: true,
              create: true,
              finalize: true,
              setCommits: {
                auto: true,
                ignoreEmpty: true,
                ignoreMissing: true,
              },
            },
            sourcemaps: {
              assets: ['./dist/assets/**'],
              ignore: ['./dist/**/*.css.map'],
            },
            reactComponentAnnotation: {
              enabled: true,
            },
            _experiments: {
              injectBuildInformation: true,
            },
          })]
        : []),
    ].filter(Boolean),
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
      },
    },
    server: {
      port: 3000,
      proxy: {
        '/api': {
          target: apiProxyTarget,
          changeOrigin: true,
        },
      },
    },
    build: {
      target: 'es2020',
      sourcemap: true,
      chunkSizeWarningLimit: 800,
      rolldownOptions: {
        output: {
          manualChunks(id: string) {
            if (id.includes('node_modules/react-router-dom/') || id.includes('node_modules/react-router/')) return 'vendor-router'
            if (id.includes('node_modules/@tanstack/react-query')) return 'vendor-query'
            if (id.includes('node_modules/@radix-ui/')) return 'vendor-ui'
            if (id.includes('node_modules/react-hook-form/') || id.includes('node_modules/@hookform/') || id.includes('node_modules/zod/')) return 'vendor-forms'
            if (id.includes('node_modules/recharts/') || id.includes('node_modules/d3-')) return 'vendor-charts'
            if (id.includes('node_modules/leaflet/') || id.includes('node_modules/react-leaflet/')) return 'vendor-maps'
            if (id.includes('node_modules/axios/') || id.includes('node_modules/date-fns/') || id.includes('node_modules/zustand/') || id.includes('node_modules/clsx/') || id.includes('node_modules/tailwind-merge/')) return 'vendor-utils'
          },
        },
      },
    },
  }
})
