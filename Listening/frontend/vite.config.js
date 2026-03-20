import { defineConfig } from "vite";

export default defineConfig({
  esbuild: {
    jsx: "automatic"
  },
  server: {
    port: 5173,
    fs: {
      // 允许 Vite 向上两级访问
      allow: ['..', '../../Intensive_Listening', '../../Auth'] 
    }
  }
});