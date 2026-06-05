import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    cssMinify: 'esbuild',
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('node_modules/vue/') || id.includes('node_modules/@vue/')) {
            return 'vue-vendor'
          }

          if (id.includes('node_modules/vue-router')) {
            return 'vue-router'
          }

          if (id.includes('node_modules/@tanstack/vue-query')) {
            return 'query'
          }

          if (
            id.includes('node_modules/vee-validate')
            || id.includes('node_modules/@vee-validate')
            || id.includes('node_modules/zod')
          ) {
            return 'forms'
          }

          if (id.includes('node_modules/reka-ui')) {
            return 'reka-ui'
          }

          if (id.includes('node_modules/@lucide')) {
            return 'icons'
          }

          if (id.includes('node_modules/pinia')) {
            return 'pinia'
          }
        },
      },
    },
  },
})
