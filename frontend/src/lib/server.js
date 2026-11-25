import React from 'react';
  
  const Server = () =>  {
	return (
	  <div>
	  </div>
	);
  }
  
  export default Server;
  import 'server-only';
import {httpTransport} from './http-transport.mjs';
export async function publicData(path){const base=process.env.LARAVEL_API_URL;if(!base)return null;try{const res=await httpTransport(base.replace(/\/$/,'')+'/'+path,{headers:{Accept:'application/json'},signal:AbortSignal.timeout(5000)});if(!res.ok)return null;return await res.json()}catch{return null}}
