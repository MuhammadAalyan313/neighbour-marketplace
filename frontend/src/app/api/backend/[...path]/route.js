import {NextResponse} from 'next/server';
import {proxyRequest} from '@/lib/proxy.mjs';
export const dynamic='force-dynamic';
export const runtime='nodejs';
const COOKIE='neighbour_session';
const opts={httpOnly:true,sameSite:'lax',secure:process.env.NODE_ENV==='production',path:'/',maxAge:60*60*24*7};
async function handler(req,{params}){
 const {path:parts}=await params;
 const result=await proxyRequest({method:req.method,parts,origin:req.headers.get('origin'),fetchSite:req.headers.get('sec-fetch-site'),expected:process.env.APP_ORIGIN||req.nextUrl.origin,token:req.cookies.get(COOKIE)?.value,base:process.env.LARAVEL_API_URL,searchParams:req.nextUrl.searchParams,contentType:req.headers.get('content-type'),readBody:()=>req.arrayBuffer(),readJson:()=>req.json()});
 const res=NextResponse.json(result.data,{status:result.status,headers:{'Cache-Control':'no-store'}});
 if(result.session?.token)res.cookies.set(COOKIE,result.session.token,opts);
 if(result.session?.clear)res.cookies.delete(COOKIE);
 return res;
}
export {handler as GET,handler as POST,handler as PATCH,handler as DELETE};
