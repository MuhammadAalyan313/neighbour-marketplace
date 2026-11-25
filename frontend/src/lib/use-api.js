'use client';
import {useState,useEffect,useCallback} from 'react';
import {api} from './api';
export function useApi(path,{initial=null,poll=0}={}){
 const [state,setState]=useState(()=>({path,data:initial,error:'',loading:!initial}));
 const [revision,setRevision]=useState(0);
 const reload=useCallback(()=>setRevision(v=>v+1),[]);
 useEffect(()=>{
  if(!path)return;
  let active=true,controller;
  async function run(quiet=false){
   if(quiet&&controller)return;
   controller?.abort();const current=new AbortController();controller=current;
   const valid=()=>active&&!current.signal.aborted&&controller===current;
   if(!quiet)setState(s=>({path,data:s.path===path?s.data:null,error:'',loading:true}));
   try{const data=await api(path,{signal:current.signal});if(valid())setState({path,data,error:'',loading:false});}
   catch(e){if(valid())setState(s=>({path,data:s.path===path?s.data:null,error:e.message,loading:false}));}
   finally{if(controller===current)controller=null;}
  }
  // Server-rendered public data is already fresh; avoid a redundant hydration fetch.
  if(!initial||revision>0)run();
  const orderPath=/^(?:(?:seller|admin)\/(?:orders|dashboard)|orders|notifications)(?:[/?]|$)/.test(path);
  const onUpdate=()=>{if(orderPath)run(true)};
  window.addEventListener('neighbour:refresh',onUpdate);
  window.addEventListener('focus',onUpdate);
  const timer=poll?setInterval(()=>{if(document.visibilityState==='visible')run(true)},poll):null;
  return()=>{active=false;controller?.abort();if(timer)clearInterval(timer);window.removeEventListener('neighbour:refresh',onUpdate);window.removeEventListener('focus',onUpdate)};
 },[path,revision,poll,initial]);
 const current=state.path===path?state:{data:null,error:'',loading:!!path};
 return {...current,loading:!!path&&current.loading,reload};
}
