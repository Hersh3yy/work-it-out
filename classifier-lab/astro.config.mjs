import { defineConfig } from 'astro/config';

// Throwaway lab UI for the intent classifier. Talks to the Laravel API
// (PUBLIC_API_URL, default http://localhost:8088). No server code here.
export default defineConfig({
  server: { host: '127.0.0.1', port: 4321 },
});
