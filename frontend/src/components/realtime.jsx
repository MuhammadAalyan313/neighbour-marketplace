'use client';
import {useEffect,useState} from 'react';
import {api} from '@/lib/api';
const refreshEvent=()=>window.dispatchEvent(new Event('neighbour:refresh'));
export function useRealtime(user){
 const [state,setState]=useState('Polling');
 useEffect(()=>{
  let alive=true, client=null;
  const key=process.env.NEXT_PUBLIC_REVERB_APP_KEY;
  if(!user?.id||!key||!['buyer','seller'].includes(user.role)){setState('Polling');return;}
  setState('Connecting');
  const host=process.env.NEXT_PUBLIC_REVERB_HOST||'127.0.0.1';
  const port=Number(process.env.NEXT_PUBLIC_REVERB_PORT||8080);
  const tls=process.env.NEXT_PUBLIC_REVERB_SCHEME==='https';
  const channelName=`private-${user.role}.${user.id}`;
  import('pusher-js').then(({default:Pusher})=>{
   if(!alive)return;
   client=new Pusher(key,{cluster:'mt1',wsHost:host,wsPort:port,wssPort:port,forceTLS:tls,enabledTransports:['ws','wss'],enableStats:false,channelAuthorization:{customHandler:({socketId,channelName:name},callback)=>{
     api('broadcasting/auth',{method:'POST',body:{socket_id:socketId,channel_name:name}}).then(data=>{if(alive)callback(null,data)}).catch(error=>{if(alive)callback(error,null)});
   }}});
   const channel=client.subscribe(channelName);
   channel.bind('pusher:subscription_succeeded',()=>{if(alive){setState('Live');refreshEvent();}});
   channel.bind('pusher:subscription_error',()=>{if(alive)setState('Polling');});
   client.connection.bind('state_change',({current})=>{if(alive&&current!=='connected')setState(current==='connecting'?'Connecting':'Polling');});
   channel.bind('order.created',refreshEvent);
   channel.bind('order.status.updated',refreshEvent);
  }).catch(()=>{if(alive)setState('Polling')});
  return()=>{alive=false;if(client){client.unsubscribe(channelName);client.disconnect();}};
 },[user?.id,user?.role]);
 return state;
}
export function useUnread(userId){
 const [snapshot,setSnapshot]=useState({id:null,count:0});
 useEffect(()=>{
  if(!userId)return;
  let alive=true,controller=null;
  async function run(){if(controller)return;const current=new AbortController();controller=current;
   try{const r=await api('notifications/unread-count',{signal:current.signal});if(alive)setSnapshot({id:userId,count:Math.max(0,Number(r.unread_count)||0)});}catch{}finally{if(controller===current)controller=null;}
  }
  run();const timer=setInterval(()=>{if(document.visibilityState==='visible')run()},10000);
  window.addEventListener('neighbour:refresh',run);window.addEventListener('focus',run);
  return()=>{alive=false;controller?.abort();clearInterval(timer);window.removeEventListener('neighbour:refresh',run);window.removeEventListener('focus',run);};
 },[userId]);
 return snapshot.id===userId?snapshot.count:0;
}
const supported=()=>typeof window!=='undefined'&&window.isSecureContext&&'serviceWorker'in navigator&&'PushManager'in window&&'Notification'in window;
const publicKey=process.env.NEXT_PUBLIC_VAPID_PUBLIC_KEY;
function keyBytes(key){const raw=atob(key.replace(/-/g,'+').replace(/_/g,'/')+'='.repeat((4-key.length%4)%4));return Uint8Array.from(raw,c=>c.charCodeAt(0));}
async function currentSub(){const reg=await navigator.serviceWorker.getRegistration('/');return reg?reg.pushManager.getSubscription():null;}
export async function disablePush(){
 if(!supported())return;
 const sub=await currentSub();if(!sub)return;
 // Remove server registration where possible; local unsubscribe prevents future delivery.
 try{await api('push-subscriptions',{method:'DELETE',body:{endpoint:sub.endpoint}});}finally{await sub.unsubscribe();}
}
export function PushControls(){
 const [enabled,setEnabled]=useState(false),[busy,setBusy]=useState(false),[message,setMessage]=useState('');
 useEffect(()=>{if(supported())currentSub().then(s=>setEnabled(Boolean(s))).catch(()=>{});},[]);
 async function toggle(){setBusy(true);setMessage('');let sub=null;let created=false;try{
  if(!supported())throw new Error('Browser push requires localhost or HTTPS and a supported browser.');
  if(enabled){await disablePush();setEnabled(false);setMessage('Browser notifications disabled.');return;}
  if(!publicKey)throw new Error('Browser push is not configured. Set the public VAPID key.');
  if(await Notification.requestPermission()!=='granted')throw new Error('Notification permission was not granted. Check browser site settings.');
  await navigator.serviceWorker.register('/push-sw.js',{scope:'/'});
  const reg=await navigator.serviceWorker.ready;
  sub=await reg.pushManager.getSubscription();
  if(!sub){sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:keyBytes(publicKey)});created=true;}
  await api('push-subscriptions',{method:'POST',body:sub.toJSON()});setEnabled(true);setMessage('Browser notifications enabled.');
 }catch(e){if(created&&sub)try{await sub.unsubscribe()}catch{}setMessage(e.message||'Unable to change notification settings.');}finally{setBusy(false)}}
 return <section className="card panel push-settings"><div><h2>Browser notifications</h2><p className="muted">Receive order alerts even when this tab is closed, when supported.</p></div><button type="button" className="btn secondary" disabled={busy} onClick={toggle}>{busy?'Working…':enabled?'Disable notifications':'Enable notifications'}</button>{message&&<p role="status">{message}</p>}</section>;
}
