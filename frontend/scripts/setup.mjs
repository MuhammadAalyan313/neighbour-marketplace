import {copyFileSync,constants,existsSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {join} from 'node:path';
const root=fileURLToPath(new URL('../',import.meta.url));
const target=join(root,'.env.local');
if(existsSync(target))console.log('Existing .env.local preserved.');
else{copyFileSync(join(root,'.env.example'),target,constants.COPYFILE_EXCL);console.log('Created .env.local. Review Laravel URL before running the frontend.');}
