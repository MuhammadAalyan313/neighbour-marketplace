import test from 'node:test';
import assert from 'node:assert/strict';
import {hasInjectedWarningComponent,inspectDependencies,warningFiles} from '../scripts/dependency-check.mjs';
import {mkdtempSync,mkdirSync,writeFileSync,rmSync} from 'node:fs';
import {join,dirname} from 'node:path';
import {tmpdir} from 'node:os';
test('detects the accidental React component in a Next warning file',()=>{
 assert.equal(hasInjectedWarningComponent("import React from 'react'; const Warning = () => { return (<div></div>); }; export default Warning;"),true);
 assert.equal(hasInjectedWarningComponent('class Warning extends Error { constructor(message){super(message)} }'),false);
});
test('dependency check reports version mismatches and modified warning files',()=>{
 const root=mkdtempSync(join(tmpdir(),'neighbour-deps-'));
 try{const expected={next:'16.4.6',react:'19.2.7','react-dom':'19.2.7'};for(const [name,version] of Object.entries(expected)){const path=join(root,'node_modules',name,'package.json');mkdirSync(dirname(path),{recursive:true});writeFileSync(path,JSON.stringify({version}));}
 assert.deepEqual(inspectDependencies(root,expected).failures,[]);
 const path=join(root,'node_modules','next',warningFiles[0]);mkdirSync(dirname(path),{recursive:true});writeFileSync(path,'const Warning = () => <div></div>;');
 assert.equal(inspectDependencies(root,expected).failures.length,1);
 assert.equal(inspectDependencies(root,{...expected,next:'other'}).failures.length,2);
 }finally{rmSync(root,{recursive:true,force:true});}
});
