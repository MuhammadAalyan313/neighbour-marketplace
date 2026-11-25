import {httpTransport} from './http-transport.mjs';
import {allowed,mutationAllowed,isPublic} from './policy.mjs';
const fail=(status,message,session)=>({status,data:{message},session});
export async function proxyRequest({method,parts,origin,fetchSite,expected,token,base,searchParams,contentType,readBody,readJson,fetcher=httpTransport}){
 const path=parts.join('/');const guest=method==='POST'&&path==='guest-track';
 if(!guest&&!allowed(method,parts))return fail(404,'Unknown API endpoint.');
 if(!mutationAllowed(method,origin,expected,fetchSite))return fail(403,'Invalid request origin.');
 if(!guest&&!isPublic(path,method)&&!token)return fail(401,'Please sign in.');
 if(!base)return fail(503,'Marketplace API is not configured.');
 const root=base.replace(/\/$/,'');const authRoute=['auth/login','auth/register','auth/password/forgot','auth/password/reset'].includes(path);
 const headers=new Headers({Accept:'application/json'});
 if(token&&!guest&&!authRoute)headers.set('Authorization',`Bearer ${token}`);
 const call=(url,opts)=>fetcher(url,{...opts,cache:'no-store',redirect:'error',signal:AbortSignal.timeout(18000)});
 try{
  let url=new URL(path==='broadcasting/auth'?root.replace(/\/v1$/,'')+'/broadcasting/auth':root+'/'+path),upstreamMethod=method,body;
  if(guest){let input;try{input=await readJson()}catch{return fail(400,'Invalid JSON request.')};if(typeof input?.order_number!=='string'||!/^[a-zA-Z0-9_-]{1,180}$/.test(input.order_number)||typeof input?.access_token!=='string'||input.access_token.length!==64)return fail(422,'Enter a valid order number and access token.');url=new URL(root+'/orders/'+input.order_number);url.searchParams.set('access_token',input.access_token);upstreamMethod='GET';}
  else if(method==='GET'){for(const [k,v] of searchParams||[])if(k!=='access_token')url.searchParams.append(k,v);}
  else{
   if(contentType)headers.set('content-type',contentType);
   body=await readBody();if(body.byteLength>3*1024*1024)return fail(413,'Request is too large.');if(!body.byteLength)body=undefined;
   if(path==='checkout'){
    let input;try{input=JSON.parse(new TextDecoder().decode(body))}catch{return fail(400,'Invalid checkout request.')};
    const {checkout_mode,...payload}=input||{};
    if(!['guest','account'].includes(checkout_mode))return fail(422,'Select a checkout account type.');
    if(checkout_mode==='account'){
     if(!token)return fail(401,'Your session has expired. Sign in before placing your order.');
     const me=await call(root+'/auth/me',{method:'GET',headers});
     if(me.status===401||me.status===403)return fail(401,'Your session is no longer active. Sign in before placing your order.',{clear:true});
     if(!me.ok)return fail(503,'Unable to verify your session. No order was submitted.');
     const identity=await me.json().catch(()=>null);if(!identity?.data?.user?.id)return fail(503,'Unable to verify your session. No order was submitted.');
    }else if(token){return fail(409,'Your sign-in state changed. Refresh the page before checking out.');}
    headers.set('content-type','application/json');body=JSON.stringify(payload);
   }
  }
  const upstream=await call(url,{method:upstreamMethod,headers,body});
  const data=await upstream.json().catch(()=>null);
  if(upstream.status>=500){console.warn(`[marketplace proxy] ${method} ${authRoute?path:'upstream endpoint'}: Laravel HTTP ${upstream.status}`);return fail(502,'Laravel returned a server error. Check the backend log.');}
  if(!data||typeof data!=='object')return fail(502,'Unexpected response from marketplace API.');
  if(['auth/login','auth/register'].includes(path)&&upstream.ok){const {token:secret,token_type,...safe}=data.data||{};if(typeof secret!=='string'||!secret||!safe.user)return fail(502,'Invalid authentication response.');return {data:{...data,data:safe},status:upstream.status,session:{token:secret}};}
  const clear=(['auth/logout','auth/logout-all'].includes(path)&&upstream.ok)||(upstream.status===401&&token&&!guest&&!isPublic(path,method));
  return {data,status:upstream.status,session:clear?{clear:true}:undefined};
 }catch(error){console.warn(`[marketplace proxy] ${method} ${authRoute?path:'upstream endpoint'}: ${error?.name==='AbortError'||error?.name==='TimeoutError'?'timeout':'transport failure'}`);return fail(503,'Cannot reach the marketplace. Check the backend server and try again.');}
}
