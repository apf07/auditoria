import {cp,mkdir} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
await mkdir(path.join(root,'assets'),{recursive:true});
await cp(path.join(root,'frontend/dist/index.html'),path.join(root,'index.html'));
await cp(path.join(root,'frontend/dist/assets'),path.join(root,'assets'),{recursive:true});
console.log('Frontend publicado en http://localhost:8080/AUDITORIA/');
