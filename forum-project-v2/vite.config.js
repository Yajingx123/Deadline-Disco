import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ command }) => ({
  // Dev uses root path; build keeps deployment base path.
  base: command === 'serve' ? '/' : '/forum-project-v2/dist/',
  plugins: [react()],
  server: {
    host: '127.0.0.1',
    port: 5176,
  },
}))
