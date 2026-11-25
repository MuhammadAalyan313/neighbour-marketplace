export type Role = 'buyer' | 'seller' | 'admin';
export type Fulfillment = 'pickup' | 'seller_delivery';
export type PaymentMethod = 'cash' | 'easypaisa' | 'jazzcash' | 'bank_transfer';
export type OrderStatus = 'pending' | 'accepted' | 'delivered' | 'cancelled';
export interface Envelope<T> {success?: boolean; message?: string; data: T; meta?: {current_page:number;last_page:number;per_page:number;total:number}}
export interface User {id:number;name:string;email:string;phone:string;house_number:string|null;role:Role;email_verified_at:string|null;is_active:boolean}
export interface Category {id:number;name:string;slug:string;icon:string|null}
export interface Store {id:number;store_name:string;slug:string;house_number:string;is_open:boolean;is_active:boolean;description:string|null;logo:string|null;products?:Product[]}
export interface Product {id:number;store_id:number;category_id:number;title:string;description:string|null;price:string;image:string|null;is_available:boolean;stock_quantity:number|null;store?:Store;category?:Category}
export interface CartItem {product:Product;quantity:number}
export interface CheckoutItem {product_id:number;quantity:number}
export interface CheckoutPreview {fulfillment_type:Fulfillment;store_count:number;subtotal:string;discount:string;delivery_fee:string;platform_fee:string;grand_total:string;available_payment_methods:PaymentMethod[];stores:Array<{store_id:number;store_name:string;total:string;items:Array<{product_id:number;title:string;quantity:number;unit_price:string;subtotal:string}>}>}
