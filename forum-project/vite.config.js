import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  base: '/forum-project/dist/',
  plugins: [react()],
  server: {
    host: '127.0.0.1',
    port: 5173,
    proxy: {
      '/shared': {
        target: 'http://127.0.0.1:8001',
        changeOrigin: true,
      },
    },
  },
})
