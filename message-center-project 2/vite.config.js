import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  base: '/message-center-project%202/dist/',
  plugins: [react()],
  server: {
    proxy: {
      '/shared': {
        target: 'http://127.0.0.1:8001',
        changeOrigin: true,
      },
    },
  },
})
