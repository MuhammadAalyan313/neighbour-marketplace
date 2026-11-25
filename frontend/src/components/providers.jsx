'use client';
import {createContext,useContext,useEffect,useState,useCallback,useRef} from 'react';
import {api} from '@/lib/api';
import {useRealtime,useUnread,disablePush} from './realtime';
import {updateCart,restoreCart} from '@/lib/cart.mjs';
const Context=createContext(null);
export function Providers({children}){const [user,setUser]=useState(null),[authLoading,setAuthLoading]=useState(true),[authError,setAuthError]=useState(''),[cart,setCart]=useState([]),[ready,setReady]=useState(false),[toast,setToast]=useState('');
 const pending=useRef(null);
 const realtime=useRealtime(user);const unreadCount=useUnread(user?.id);
 const refresh=useCallback(()=>{
  if(pending.current)return pending.current;
  const task=(async()=>{try{const r=await api('auth/me');setUser(r.data.user);setAuthError('');return r.data.user}catch(e){setUser(null);setAuthError(e.status===401?'':e.message);return null}finally{setAuthLoading(false);pending.current=null}})();
  pending.current=task;return task;
 },[]);
 useEffect(()=>{refresh();try{const saved=JSON.parse(localStorage.getItem('neighbour-cart-v1')||'[]');setCart(restoreCart(saved))}catch{}setReady(true)},[refresh]);
 useEffect(()=>{if(ready)try{localStorage.setItem('neighbour-cart-v1',JSON.stringify(cart))}catch{}},[cart,ready]);
 useEffect(()=>{if(!toast)return;const t=setTimeout(()=>setToast(''),3000);return()=>clearTimeout(t)},[toast]);
 const change=(p,d)=>setCart(c=>updateCart(c,p,d));const signOut=async()=>{try{await disablePush()}catch{}await api('auth/logout',{method:'POST'});setUser(null);setAuthError('')};
 return <Context.Provider value={{user,realtime,unreadCount,setUser,authLoading,authError,refresh,signOut,cart,ready,change,clearCart:()=>setCart([]),remove:id=>setCart(c=>c.filter(x=>x.product.id!==id)),count:cart.reduce((s,x)=>s+x.quantity,0),notify:setToast}}>{children}{toast&&<div className="toast" role="status">{toast}</div>}</Context.Provider>}
export const useApp=()=>useContext(Context);
