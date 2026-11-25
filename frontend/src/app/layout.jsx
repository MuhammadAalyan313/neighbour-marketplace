import './globals.css';
import {Providers} from '@/components/providers';
export const metadata={title:{default:'Neighbour — Shop local',template:'%s | Neighbour'},description:'Discover local stores and shop your neighbourhood.',manifest:'/manifest.webmanifest',appleWebApp:{capable:true,statusBarStyle:'default',title:'Neighbour'}};
export const viewport={width:'device-width',initialScale:1,themeColor:'#ffffff',viewportFit:'cover'};
export default function RootLayout({children}){return <html lang="en"><body><a className="skip" href="#main">Skip to content</a><Providers>{children}</Providers></body></html>}
