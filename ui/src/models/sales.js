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
export async function downloadSales(url,name,token=''){
 const {data}=await axios.get(url,{headers:tokenHeaders(token),responseType:'blob'});
 const href=URL.createObjectURL(data),a=document.createElement('a');a.href=href;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(href),1000);
}
export const productionLabels={awaiting_artwork:'Čaká na grafiku',ready:'Pripravené',in_progress:'Vo výrobe',completed:'Dokončené'};
export const quoteLabels={requested:'Dopyt',offered:'Nacenené',accepted:'Prijaté',withdrawn:'Stiahnuté'};
