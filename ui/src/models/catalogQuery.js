const allowed = ['bySearchInput', 'byCategory', 'byAttribute', 'inStock', 'priceFrom', 'priceTo', 'page'];
export function catalogQuery(query = {}) {
 /** @type {Record<string, string>} */
 const result = {};
 for (const key of allowed) {
  const raw = query[key];
  const value = typeof raw === 'number' ? String(raw) : raw;
  if (typeof value !== 'string' || !value.trim()) continue;
  if (['page','byCategory'].includes(key) && !/^[1-9][0-9]{0,8}$/.test(value)) continue;
  if (['priceFrom','priceTo'].includes(key) && (!Number.isFinite(Number(value)) || Number(value)<0)) continue;
  if (key==='inStock' && value!=='1') continue;
  result[key]=value.slice(0,key==='byAttribute'?2000:200);
 }
 return result;
}
export function changeCatalogQuery(query, patch) { const next=catalogQuery({...query,...patch}); if(!Object.hasOwn(patch,'page')) delete next.page; return next; }
