import {spawnSync} from 'node:child_process';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const result=spawnSync(process.execPath,[path.join(root,'frontend/node_modules/vite/bin/vite.js'),'build'],{cwd:path.join(root,'frontend'),env:{...process.env,AUDITORIA_TARGET:'xampp'},stdio:'inherit'});
if(result.error)throw result.error;
if(result.status!==0)process.exit(result.status??1);
await import('./publish-xampp.mjs');
