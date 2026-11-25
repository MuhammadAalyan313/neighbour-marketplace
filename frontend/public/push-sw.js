self.addEventListener('push',event=>{event.waitUntil((async()=>{
 let data={};try{data=event.data?.json()||{}}catch{}
 // Keep lock-screen details private. Open the authenticated notification inbox.
 await self.registration.showNotification('Neighbour Marketplace',{body:'You have a new marketplace update.',tag:typeof data.tag==='string'?data.tag:'marketplace-update',data:{url:'/account/notifications'}});
})());});
self.addEventListener('notificationclick',event=>{event.notification.close();event.waitUntil((async()=>{
 const url=new URL('/account/notifications',self.location.origin).href;
 const clients=await self.clients.matchAll({type:'window',includeUncontrolled:true});
 for(const client of clients){if(client.url===url)return client.focus();}
 return self.clients.openWindow(url);
})());});
