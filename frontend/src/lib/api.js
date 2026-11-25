import React from 'react';
  
  const Api = () =>  {
	return (
	  <div>
	  </div>
	);
  }
  
  export default Api;
  export class ApiError extends Error {constructor(message,status,errors){super(message);this.status=status;this.errors=errors}}
export async function api(path,{method='GET',body,signal}={}){const headers={Accept:'application/json'};let payload=body;if(body!==undefined&&!(body instanceof FormData)){headers['Content-Type']='application/json';payload=JSON.stringify(body)}const res=await fetch('/api/backend/'+path,{method,headers,body:payload,signal,cache:'no-store',credentials:'same-origin'});const data=await res.json().catch(()=>null);if(!data||typeof data!=='object')throw new ApiError('Unexpected response.',res.ok?502:res.status);if(!res.ok||data.success===false){const details=data.errors?Object.values(data.errors).flat().join(' '):'';throw new ApiError(details||data.message||'Something went wrong.',res.status,data.errors)}return data}
export const query=(path,params={})=>{const p=new URLSearchParams();for(const [k,v] of Object.entries(params))if(v!==''&&v!==undefined&&v!==null)p.set(k,String(v));return path+(p.size?'?'+p:'')};
export function unpack(response){const d=response?.data;return {rows:Array.isArray(d)?d:Array.isArray(d?.data)?d.data:[],meta:response?.meta||(d?.current_page?d:null)}}
export const money=(v)=>'Rs. '+new Intl.NumberFormat('en-PK',{maximumFractionDigits:2}).format(Number(v)||0);
export const label=v=>String(v??'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
export function mediaUrl(path){if(!path)return null;try{const origin=process.env.NEXT_PUBLIC_MEDIA_ORIGIN;if(/^https?:\/\//i.test(path)){const u=new URL(path);return origin&&u.origin===new URL(origin).origin?u.href:null}if(!origin)return null;return new URL(path.startsWith('/storage/')?path:'/storage/'+path.replace(/^\/+/,''),origin).href}catch{return null}}
