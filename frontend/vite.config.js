import { defineConfig } from 'vite';
export default defineConfig({base:process.env.AUDITORIA_TARGET==='xampp'?'/AUDITORIA/':'/',server:{port:5173,strictPort:true,proxy:{'/api':'http://127.0.0.1:8000'}}});
