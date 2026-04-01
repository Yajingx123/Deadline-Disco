import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  base: '/message-center-project/dist/',
  plugins: [react()],
})
