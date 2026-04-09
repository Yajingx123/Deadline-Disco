import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ command }) => ({
  base: command === 'serve' ? '/' : '/GameUI/message-center-project-GameUI/dist/',
  plugins: [react()],
  server: {
    proxy: {
      '/shared': {
        target: 'http://127.0.0.1:8001',
        changeOrigin: true,
      },
    },
  },
}))
