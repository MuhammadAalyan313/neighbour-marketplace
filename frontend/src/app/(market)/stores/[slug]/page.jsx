import {StoreDetail} from '@/features/marketplace';export default async function Page({params}){const {slug}=await params;return <StoreDetail slug={encodeURIComponent(slug)}/>}
