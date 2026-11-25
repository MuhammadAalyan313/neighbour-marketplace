import {httpTransport} from '../src/lib/http-transport.mjs';
// Read-only smoke test for a running Laravel instance, never creates accounts or orders.
const base=(process.env.LARAVEL_API_URL||'http://127.0.0.1:8000/api/v1').replace(/\/$/,'');
const checks=[['marketplace/categories',200,true],['marketplace/stores?per_page=2',200,true],['marketplace/products?sort=popular&per_page=2',200,true],['auth/me',401,false],['admin/dashboard',401,false],['seller/dashboard',401,false]];
let failed=0;
for(const [path,expected,list] of checks){try{const r=await httpTransport(base+'/'+path,{headers:{Accept:'application/json'},redirect:'error',signal:AbortSignal.timeout(5000)});const data=await r.json().catch(()=>null);const ok=r.status===expected&&!!data&&(!list||Array.isArray(data.data));console.log(`${ok?'PASS':'FAIL'} ${path}: HTTP ${r.status} (expected ${expected})`);if(!ok)failed++;}catch(e){console.log(`FAIL ${path}: ${e.cause?.code||e.name||'connection error'}`);failed++;}}
console.log(`${checks.length-failed}/${checks.length} live API smoke checks passed`);process.exitCode=failed?1:0;
