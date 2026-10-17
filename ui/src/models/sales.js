import axios from '../axiosInstance';
export function salesError(e) {
 if(e.name==='SalesResponseError') return e.message;
 const status=e.response?.status;
 if(status===404) return 'Záznam alebo odkaz nie je dostupný. Požiadajte obsluhu o nový odkaz.';
 if(status===401) return 'Pre túto operáciu sa prihláste.';
 if(status===403) return 'Nemáte oprávnenie na túto operáciu.';
 if(status>=500) return 'Operácia sa nepodarila. Skúste ju zopakovať.';
 return Object.values(e.response?.data?.errors??{}).flat().join(' ') || e.response?.data?.message || 'Operácia sa nepodarila. Skúste ju zopakovať.';
}
export const tokenHeaders=token=>({'X-Sales-Token':token});
export function shareUrl(kind,data){
 if(typeof data?.uuid!=='string' || typeof data?.token!=='string') {
  const error=new Error('Server vrátil neplatnú odpoveď. Obnovte detail a vytvorte nový odkaz.');
  error.name='SalesResponseError';throw error;
 }
 return location.origin+'/'+kind+'/'+data.uuid+'#token='+encodeURIComponent(data.token);
}
// Súkromná stránka správ a schvaľovania grafiky; rovnaký tvar generuje API (OrderProduction::shareUrl).
export const messagesUrl=data=>shareUrl('objednavka',data).replace('#token=','/spravy#token=');
export async function downloadSales(url,name,token=''){
 const {data}=await axios.get(url,{headers:tokenHeaders(token),responseType:'blob'});
 const href=URL.createObjectURL(data),a=document.createElement('a');a.href=href;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(href),1000);
}
export const productionLabels={awaiting_artwork:'Čaká na grafiku',ready:'Pripravené',in_progress:'Vo výrobe',completed:'Dokončené'};
export const quoteLabels={requested:'Dopyt',draft:'Koncept',offered:'Odoslaná',accepted:'Prijatá',withdrawn:'Stiahnutá'};
export const quoteTones={requested:'bg-amber-100 text-amber-800',draft:'bg-gray-100 text-gray-700',offered:'bg-blue-100 text-blue-800',accepted:'bg-green-100 text-green-800',withdrawn:'bg-red-100 text-red-700'};
export const VAT_RATES=[23,20,10,0];
export const SHIPPING_VAT=23;
export const money=v=>Number(v??0).toLocaleString('sk-SK',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';
const r2=v=>Math.round((Number(v)+Number.EPSILON)*100)/100;
export const netFromGross=(gross,vat)=>r2(Number(gross)/(1+Number(vat)/100));
// Rovnaký výpočet ako App\Support\QuotePricing — súčty v editore sa zhodujú s uloženou ponukou.
export function quoteLine(i){const unitNet=r2(Number(i.price_net||0)*(1-Number(i.discount_percent||0)/100)),unitGross=r2(unitNet*(1+Number(i.vat||0)/100)),q=Number(i.quantity||0);
 return {unitNet,unitGross,lineNet:r2(q*unitNet),lineGross:r2(q*unitGross)};}
export function quoteTotals(items,discount,shippingNet){const lines=items.map(quoteLine),itemsNet=r2(lines.reduce((s,l)=>s+l.lineNet,0)),itemsGross=r2(lines.reduce((s,l)=>s+l.lineGross,0));
 const d=Number(discount||0),discountNet=r2(itemsNet*d/100),discountGross=r2(itemsGross*d/100),sNet=r2(Number(shippingNet||0)),sGross=r2(sNet*(1+SHIPPING_VAT/100));
 const net=r2(itemsNet-discountNet+sNet),gross=r2(itemsGross-discountGross+sGross);return {itemsNet,discountNet,shippingNet:sNet,net,vat:r2(gross-net),gross};}
