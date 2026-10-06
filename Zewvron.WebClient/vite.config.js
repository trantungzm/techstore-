import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')
    return {
        plugins: [react(), tailwindcss()],
        build: {
        outDir: '../Zewvron.ApiGateway/wwwroot',
        emptyOutDir: true,
    },
    server: {
        host: '0.0.0.0',
        strictPort: true,
        port: parseInt(env.VITE_PORT) || 3000,
        proxy: {
            '/api': {
                target: 'http://localhost:5000',
                changeOrigin: true,
                secure: false,
                configure: (proxy, options) => {
                    proxy.on('error', (err, req, res) => {
                        console.log('Proxy error:', err.message);
                        res.writeHead(500, { 'Content-Type': 'application/json' });
                        res.end(JSON.stringify({ message: 'Backend not available. Make sure backend is running on port 5000' }));
                    });
                }
            },
            // Uploaded files (product images, attachments) are served as static
            // files directly by the API service on port 5001. The Ocelot gateway
            // on 5000 only routes /api/*, so /uploads must go straight to 5001.
            '/uploads': {
                target: 'http://localhost:5001',
                changeOrigin: true,
                secure: false,
            }
            ,
            // Proxy SignalR hub negotiate & websocket traffic to API Gateway
            '/zewvronChatHub': {
                // Directly proxy SignalR hub to API service (5001) in dev
                target: 'http://localhost:5001',
                changeOrigin: true,
                secure: false,
                ws: true,
            }
        }
    }
    }
})
