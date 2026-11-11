import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'
import { fileURLToPath, URL } from 'node:url'

// Backend Laravel berjalan di BACKEND_URL (default http://127.0.0.1:8000).
// Proxy membuat browser melihat satu origin sehingga cookie Sanctum (HttpOnly) bekerja.
const backend = process.env.BACKEND_URL ?? 'http://127.0.0.1:8000'
const proxy = {
  '/api': { target: backend, changeOrigin: false },
  '/sanctum': { target: backend, changeOrigin: false },
}
// Tunnel sementara (Cloudflare Quick Tunnel) untuk uji dari perangkat lain
const allowedHosts = ['.trycloudflare.com', '.ngrok-free.app', '.ngrok.app', '.lhr.life', '.localhost.run']

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: { port: 5173, proxy, allowedHosts },
  preview: { port: 4173, proxy, allowedHosts },
  build: {
    rollupOptions: {
      output: {
        // Hanya vendor yang dipisah manual. Admin & User Panel terpisah otomatis lewat dynamic import
        // di router, sehingga bundle admin tidak terunduh oleh pengguna biasa (Design Spec §20.3).
        manualChunks(id) {
          if (id.includes('node_modules')) return 'vendor'
        },
      },
    },
  },
})
