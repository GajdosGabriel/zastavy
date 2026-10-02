import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const proxyTarget = { target: 'http://zastavy.local', changeOrigin: true, secure: false }

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    // Prehliadač volá len origin Vite; zastavy.local sa prekladá na počítači s Vite,
    // takže to funguje aj v prehliadačoch bez hosts záznamu a bez CORS.
    proxy: {
      '/api': proxyTarget,
      '/sanctum': proxyTarget,
      '/storage': proxyTarget,
      '/images': proxyTarget,
    },
  },
})
