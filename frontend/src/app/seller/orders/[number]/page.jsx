import {OrderDetail} from '@/features/dashboard';export default async function Page({params}){const {number}=await params;return <OrderDetail role="seller" number={encodeURIComponent(number)}/>}
