import {EditProduct} from '@/features/dashboard';export default async function Page({params}){const {id}=await params;return <EditProduct id={encodeURIComponent(id)}/> }
