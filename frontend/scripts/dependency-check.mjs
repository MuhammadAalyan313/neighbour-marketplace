import {readFileSync,existsSync} from 'node:fs';
import {join} from 'node:path';
export const warningFiles=['dist/build/webpack/loaders/postcss-loader/src/Warning.js','dist/esm/build/webpack/loaders/postcss-loader/src/Warning.js'];
// Detect the specific React component insertion observed in the screenshot.
// This is a targeted corruption check, not a full package signature verifier.
export function hasInjectedWarningComponent(source){
 return /\b(?:const|let|var)\s+Warning\s*=\s*\([^)]*\)\s*=>/.test(source)&& /<div(?:\s|>)/.test(source);
}
export function inspectDependencies(root,expected){
 const failures=[];const versions={};
 for(const name of ['next','react','react-dom']){
  try{const pkg=JSON.parse(readFileSync(join(root,'node_modules',name,'package.json'),'utf8'));versions[name]=pkg.version;if(pkg.version!==expected[name])failures.push(`${name}: expected ${expected[name]}, installed ${pkg.version}`)}catch{failures.push(`${name} is missing or unreadable. Run npm install.`)}
 }
 for(const relative of warningFiles){const file=join(root,'node_modules','next',relative);if(existsSync(file)&&hasInjectedWarningComponent(readFileSync(file,'utf8')))failures.push(`Modified Next.js internal file: ${relative}. Reinstall dependencies in a clean folder.`)}
 return {failures,versions};
}
