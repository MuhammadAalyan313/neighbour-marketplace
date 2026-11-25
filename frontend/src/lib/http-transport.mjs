import http from 'node:http';
import https from 'node:https';

// Server-only transport: consume bounded responses completely before returning.
// Avoid Undici's socket-end parser path and never retry a mutation automatically.
export function httpTransport(input,{method='GET',headers,body,signal}={}){
 return new Promise((resolve,reject)=>{
  const url=new URL(input);
  if(!['http:','https:'].includes(url.protocol))return reject(new Error('Unsupported upstream protocol'));
  const client=url.protocol==='https:'?https:http;
  const outgoing=Object.fromEntries(new Headers(headers).entries());
  const payload=body==null?undefined:typeof body==='string'?Buffer.from(body):Buffer.from(body);
  outgoing.connection='close';outgoing['accept-encoding']='identity';
  if(payload!==undefined)outgoing['content-length']=String(payload.length);
  const req=client.request(url,{method,headers:outgoing,agent:false,signal},res=>{
   const chunks=[];let bytes=0;
   res.on('error',reject);
   res.on('aborted',()=>reject(new Error('Incomplete upstream response')));
   res.on('data',chunk=>{bytes+=chunk.length;if(bytes>8*1024*1024){res.destroy(new Error('Upstream response too large'));return;}chunks.push(chunk)});
   res.on('end',()=>{
    if(!res.complete)return reject(new Error('Incomplete upstream response'));
    const status=res.statusCode||502;
    if(status>=300&&status<400)return reject(new Error('Unexpected upstream redirect'));
    const text=Buffer.concat(chunks).toString('utf8');
    resolve({status,ok:status>=200&&status<300,json:async()=>JSON.parse(text)});
   });
  });
  req.on('error',reject);req.end(payload);
 });
}
