<script setup>
import {ref,computed,watch,onMounted,onBeforeUnmount} from 'vue';import {useRoute,useRouter} from 'vue-router';import {storeToRefs} from 'pinia';import axios from '../../axiosInstance';
import BaseLayout from '../layout/BaseLayout.vue';import buttonLink from '../layout/page/ButtonLink.vue';
import CustomerFormFields from '../forms/CustomerFormFields.vue';import CustomOrderItem from '../forms/CustomOrderItem.vue';import MessageTemplatePicker from './MessageTemplatePicker.vue';
import useCustomers from '../../store/StoreCustomers';import useErrors from '../../store/StoreErrors';
import {salesError,shareUrl,downloadSales,quoteLabels,quoteTones,quoteMessageLabels,money,quoteLine,quoteTotals,netFromGross,VAT_RATES,SHIPPING_VAT} from '../../models/sales';
const route=useRoute(),router=useRouter();
const quote=ref(null),error=ref(''),busy=ref(false),saved=ref(''),share=ref(''),methods=ref([]),notify=ref(true);

// Fakturačné údaje sú v store zákazníka ako v košíku — CustomerFormFields (IČO, rady k údajom) sa tak dá použiť bez zmeny.
const customersStore=useCustomers();const {getCustomer}=storeToRefs(customersStore);
const errorsStore=useErrors();const {getFieldErrors}=storeToRefs(errorsStore);
const CUSTOMER_FIELDS=['company','name','email','phone','street','postcode','city','ico','dic','ic_dic'];
const emptyCustomer=()=>Object.fromEntries(CUSTOMER_FIELDS.map(k=>[k,'']));
const isPrivatePerson=ref(false);
watch(isPrivatePerson,value=>{if(value)Object.assign(getCustomer.value,{company:'',ico:'',dic:'',ic_dic:''});});
const header=ref({customer_id:null,title:'',brief:'',internal_note:''});

const inDays=d=>{const t=new Date();t.setDate(t.getDate()+d);return t.toISOString().slice(0,10);};
const form=ref({items:[],discount_percent:0,shipping_method_id:'',shipping_price_net:0,terms:'',valid_until:inDays(14)});
const closed=computed(()=>['accepted','withdrawn'].includes(quote.value?.status));
const sent=computed(()=>(quote.value?.versions??[]).filter(v=>v.sent_at));
const hasDraft=computed(()=>!!quote.value?.has_draft);
const totals=computed(()=>quoteTotals(form.value.items,form.value.discount_percent,form.value.shipping_price_net));
const hasAlternatives=computed(()=>form.value.items.some(i=>i.alternatives?.length));
const date=v=>v?new Date(v).toLocaleString('sk-SK'):'';

function row(i){const vat=i.snapshot?.vat??i.vat??23;
 return {is_custom:!!i.is_custom,variant_id:i.variant_id??null,name:i.snapshot?.name??i.name??'',variant_name:i.snapshot?.variant_name??'',description:i.description??'',unit:i.snapshot?.unit_value??i.unit??'ks',vat,
  quantity:i.quantity??1,price_net:i.price_net??(i.price!=null?netFromGross(i.price,vat):''),discount_percent:i.discount_percent??0,min_order:i.min_order??null,
  alternatives:(i.alternatives??[]).map(a=>({quantity:a.quantity,price_net:a.price_net}))};}
function apply(data){quote.value=data;const c=data.customer??{};
 customersStore.setCustomer({...emptyCustomer(),...Object.fromEntries(Object.entries(c).map(([k,v])=>[k,v??'']))});
 isPrivatePerson.value=!c.company&&!c.ico&&!!c.name;
 header.value={customer_id:data.customer_id??null,title:data.title??'',brief:data.brief??'',internal_note:data.internal_note??''};
 const base=data.versions.find(v=>!v.sent_at)??data.versions[0];
 form.value=base?{items:base.items.map(row),discount_percent:base.discount_percent??0,shipping_method_id:base.shipping?.id??'',shipping_price_net:base.shipping?.price_net??(base.shipping?netFromGross(base.shipping.price,SHIPPING_VAT):0),terms:base.terms??'',valid_until:base.valid_until?.slice(0,10)??inDays(14)}
  :{...form.value,items:(data.requested_items??[]).map(i=>row({...i,name:i.name}))};}
async function load(){apply((await axios.get('/sales-quotes/'+route.params.id)).data);}
onMounted(async()=>{errorsStore.resetErrors();customersStore.setCustomer(emptyCustomer());busy.value=true;
 try{const m=axios.get('/shipping-methods');if(route.params.id)await load();methods.value=(await m).data.data;}catch(e){error.value=salesError(e);}finally{busy.value=false;}});
// Store zákazníka zdieľa aj košík a detail zákazníka — údaje ponuky v ňom nesmú ostať.
onBeforeUnmount(()=>{customersStore.resetCustomer();errorsStore.resetErrors();});

async function run(fn,msg=''){if(busy.value)return;busy.value=true;error.value='';saved.value='';
 try{await fn();errorsStore.resetErrors();saved.value=msg;}catch(e){errorsStore.setErrors(e);error.value=salesError(e);}finally{busy.value=false;}}
const customerPayload=()=>Object.fromEntries(CUSTOMER_FIELDS.map(k=>[k,String(getCustomer.value?.[k]??'').trim()||null]));
function payload(){return {version:quote.value?.version??0,...header.value,customer:customerPayload(),
 items:form.value.items.map(i=>({is_custom:i.is_custom,variant_id:i.variant_id,name:i.name,description:i.description||null,unit:i.unit,vat:i.vat,quantity:i.quantity,price_net:i.price_net===''?null:i.price_net,discount_percent:i.discount_percent||0,
  alternatives:(i.alternatives??[]).map(a=>({quantity:a.quantity,price_net:a.price_net===''?null:a.price_net}))})),
 discount_percent:form.value.discount_percent||0,shipping_method_id:form.value.shipping_method_id||null,shipping_price_net:form.value.shipping_price_net||0,terms:form.value.terms||null,valid_until:form.value.valid_until||null};}
async function ensureQuote(){if(quote.value)return;const {data}=await axios.post('/sales-quotes',{...header.value,customer:customerPayload()});quote.value=data;await router.replace('/predaj/ponuky/'+data.id);}
function saveDraft(){return run(async()=>{await ensureQuote();apply((await axios.put('/sales-quotes/'+quote.value.id,payload())).data);},'Koncept je uložený. Zákazník ho zatiaľ nevidí.');}
function send(){const email=getCustomer.value?.email;
 if(!window.confirm(notify.value?`Odoslať ponuku zákazníkovi na ${email}?`:'Sprístupniť ponuku bez e-mailu? Odkaz pošlete zákazníkovi sami.'))return;
 return run(async()=>{await ensureQuote();apply((await axios.post('/sales-quotes/'+quote.value.id+'/offer',{...payload(),notify:notify.value})).data);},notify.value?'Ponuka je odoslaná zákazníkovi e-mailom.':'Ponuka je sprístupnená.');}
function discard(){if(!window.confirm('Zahodiť rozpracované zmeny a vrátiť sa k poslednej odoslanej verzii?'))return;return run(async()=>apply((await axios.delete('/sales-quotes/'+quote.value.id+'/draft')).data));}
function withdraw(){if(!window.confirm('Stiahnuť ponuku? Zákazník ju už nebude môcť prijať.'))return;return run(async()=>apply((await axios.post('/sales-quotes/'+quote.value.id+'/withdraw')).data));}
function getShare(){return run(async()=>{share.value=shareUrl('ponuka',(await axios.post('/sales-quotes/'+quote.value.id+'/share')).data);});}
// PDF: bez verzie vráti API najnovšiu vrátane konceptu — náhľad pred odoslaním.
function pdf(version=null){return run(()=>downloadSales('/sales-quotes/'+quote.value.id+'/pdf'+(version?'?version='+version:''),'Cenova-ponuka-'+quote.value.number+(version>1?'-v'+version:'')+'.pdf'));}
const reply=ref('');
function sendMessage(){return run(async()=>{const {data}=await axios.post('/sales-quotes/'+quote.value.id+'/messages',{body:reply.value});quote.value={...quote.value,messages:data.messages};reply.value='';},'Odpoveď je odoslaná zákazníkovi e-mailom.');}
function addAlternative(item){const last=item.alternatives.at(-1)??item;item.alternatives.push({quantity:Number(last.quantity||1)*2,price_net:last.price_net});}

// Prepojenie so zákazníkom z adresára (IČO dopĺňa CustomerFormFields).
const customerSearch=ref(''),customerResults=ref([]);let customerTimer;
function searchCustomers(){clearTimeout(customerTimer);if(!customerSearch.value.trim()){customerResults.value=[];return;}
 customerTimer=setTimeout(async()=>{try{customerResults.value=(await axios.get('/customers',{params:{bySearchInput:customerSearch.value,per_page:8}})).data.data??[];}catch(e){error.value=salesError(e);}},300);}
function pickCustomer(c){header.value.customer_id=c.id;customersStore.setCustomer({...emptyCustomer(),...Object.fromEntries(CUSTOMER_FIELDS.map(k=>[k,c[k]??'']))});isPrivatePerson.value=!c.company&&!c.ico;customerSearch.value='';customerResults.value=[];}

// Položky
const productSearch=ref(''),products=ref([]);
function findProducts(){return run(async()=>{products.value=(await axios.get('/homes',{params:{bySearchInput:productSearch.value}})).data.data;});}
const thumb=item=>item.is_custom?null:(item.thumb??quote.value?.thumbs?.[item.variant_id]??null);
function addVariant(p,v){const min=Math.max(1,v.min_order??1);
 form.value.items.push({is_custom:false,variant_id:v.id,thumb:(v.thumb&&!v.thumb.startsWith('/')?v.thumb:p.thumb)||null,name:p.name,variant_name:v.name??'',description:'',unit:p.unit_value||'ks',vat:p.vat??23,quantity:min,price_net:netFromGross(v.active_price??v.price??0,p.vat??23),discount_percent:0,min_order:min,alternatives:[]});}
// CustomOrderItem z košíka; v ponuke zadávame cenu bez DPH.
function addCustom(c){form.value.items.push({is_custom:true,variant_id:null,name:c.name,variant_name:'',description:'',unit:c.unit_value||'ks',vat:c.vat,quantity:c.input_order,price_net:c.active_price,discount_percent:0,min_order:null,alternatives:[]});}
function move(i,d){const items=form.value.items,j=i+d;if(j<0||j>=items.length)return;[items[i],items[j]]=[items[j],items[i]];}
function pickShipping(){const m=methods.value.find(m=>m.id===form.value.shipping_method_id);if(m&&!Number(form.value.shipping_price_net))form.value.shipping_price_net=netFromGross(m.price??0,SHIPPING_VAT);}
const input='w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50';
const card='overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm';
const cardHead='border-b border-gray-100 bg-gray-50 px-6 py-4';
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">

                <!-- Hlavička -->
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h1 class="page-heading mb-0">{{ quote ? 'Cenová ponuka ' + (quote.number ?? '#' + quote.id) : 'Nová cenová ponuka' }}</h1>
                    <buttonLink class="text-sm" :item="{ name: 'Späť na ponuky', link: '/predaj/ponuky', icon: 'arrow-left' }" />
                </div>

                <div v-if="quote" class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="quote.is_expired ? 'bg-red-100 text-red-700' : quoteTones[quote.status]">{{ quote.is_expired ? 'Po platnosti' : quoteLabels[quote.status] }}</span>
                    <span v-if="quote.version" class="text-gray-600">odoslaná verzia {{ quote.version }}</span>
                    <span v-if="hasDraft" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">neodoslané zmeny</span>
                    <span v-if="quote.sent_at" class="text-gray-600">· odoslaná {{ date(quote.sent_at) }}{{ quote.sent_to ? ' na ' + quote.sent_to : '' }}</span>
                    <span v-if="quote.viewed_at" class="text-green-700">· zákazník ju zobrazil {{ date(quote.viewed_at) }}</span>
                    <span v-else-if="quote.status === 'offered'" class="text-gray-500">· zatiaľ nezobrazená</span>
                    <router-link v-if="quote.order_uuid" :to="'/objednavka/' + quote.order_uuid" class="font-semibold text-blue-700 underline">Objednávka {{ quote.order_serial_number }}</router-link>
                </div>

                <div class="mb-4 space-y-3">
                    <p v-if="error" role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ error }}</p>
                    <p v-if="saved" role="status" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ saved }}</p>
                    <p v-if="quote?.status === 'rejected'" class="rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
                        Zákazník ponuku odmietol {{ date(quote.rejected_at) }}<span v-if="quote.rejection_reason">: „{{ quote.rejection_reason }}“</span>. Môžete poslať upravenú verziu.
                    </p>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">

                    <!-- Ľavý stĺpec: komunikácia, položky, zákazník, zadanie -->
                    <div class="space-y-6 lg:col-span-2">

                        <!-- Komunikácia so zákazníkom -->
                        <div v-if="quote && (quote.messages?.length || quote.version)" :class="card">
                            <div :class="cardHead"><h2 class="text-base font-semibold text-gray-800">Komunikácia so zákazníkom</h2></div>
                            <div class="space-y-3 px-6 py-5">
                                <p v-if="!quote.messages.length" class="text-sm text-gray-600">Zatiaľ bez správ. Zákazník vám môže napísať alebo požiadať o úpravu priamo zo stránky ponuky.</p>
                                <div v-for="m in quote.messages" :key="m.id" class="max-w-3xl rounded-lg px-4 py-2 text-sm" :class="m.direction === 'staff' ? 'ml-auto bg-blue-50' : (m.kind === 'rejected' ? 'bg-orange-50' : 'bg-gray-50')">
                                    <p class="text-xs text-gray-600"><strong>{{ m.author }}</strong> · {{ date(m.created_at) }}<span v-if="m.kind !== 'message'" class="ml-1 rounded bg-white px-1.5 font-semibold text-gray-800">{{ quoteMessageLabels[m.kind] }}</span></p>
                                    <p class="whitespace-pre-wrap text-gray-900">{{ m.body }}</p>
                                </div>
                                <form v-if="!closed" class="space-y-2" @submit.prevent="sendMessage">
                                    <MessageTemplatePicker v-model="reply" />
                                    <label class="block text-sm font-semibold text-gray-700">Odpoveď zákazníkovi <span class="font-normal text-gray-500">(príde mu e-mailom s odkazom na ponuku)</span>
                                        <textarea v-model="reply" rows="3" maxlength="5000" required class="mt-1.5" :class="input" />
                                    </label>
                                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:bg-gray-400" :disabled="busy || !reply.trim()">Odoslať odpoveď</button>
                                </form>
                            </div>
                        </div>

                        <fieldset :disabled="closed" class="space-y-6">
                            <!-- Položky — rovnaká karta ako v košíku, ceny bez DPH -->
                            <div :class="card">
                                <div :class="cardHead"><h2 class="text-base font-semibold text-gray-800">Položky <span class="text-sm font-normal text-gray-500">— ceny bez DPH</span></h2></div>
                                <p v-if="!form.items.length" class="px-6 py-6 text-center text-sm text-gray-500">Ponuka je zatiaľ prázdna — pridajte tovar z katalógu alebo vlastnú položku.</p>
                                <template v-else>
                                    <div class="hidden grid-cols-12 gap-2 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 md:grid">
                                        <span class="col-span-4">Tovar</span><span>Počet</span><span>Jedn.</span><span class="col-span-2 text-right">Cena/j.</span><span class="text-right">Zľava %</span><span>DPH</span><span class="col-span-2 text-right">Spolu</span>
                                    </div>
                                    <ul class="divide-y divide-gray-100">
                                        <li v-for="(item, i) in form.items" :key="i" class="space-y-2 px-4 py-3 transition hover:bg-gray-50">
                                            <div class="grid grid-cols-2 items-center gap-2 md:grid-cols-12">
                                                <div class="col-span-2 flex items-center gap-3 md:col-span-4">
                                                    <img v-if="thumb(item)" :src="thumb(item)" width="48" height="48" loading="lazy" :alt="item.name" class="h-12 w-12 shrink-0 rounded-lg border border-gray-200 bg-white object-cover" />
                                                    <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-dashed border-gray-300 text-[10px] text-gray-500">vlastná</span>
                                                    <input v-if="item.is_custom" v-model="item.name" maxlength="200" required placeholder="Názov položky" aria-label="Názov položky" :class="input" />
                                                    <div v-else class="min-w-0">
                                                        <p class="font-semibold text-gray-900">{{ item.name }}</p>
                                                        <p v-if="item.variant_name" class="text-xs font-medium text-blue-700">{{ item.variant_name }}</p>
                                                        <p v-if="item.min_order > 1" class="text-xs text-gray-500">min. odber {{ item.min_order }}</p>
                                                    </div>
                                                </div>
                                                <label class="text-xs text-gray-600"><span class="md:sr-only">Množstvo</span><input v-model.number="item.quantity" type="number" min="1" max="100000" required class="text-center" :class="input" /></label>
                                                <label class="text-xs text-gray-600"><span class="md:sr-only">Jednotka</span><input v-model="item.unit" maxlength="20" :disabled="!item.is_custom" :class="input" /></label>
                                                <label class="text-xs text-gray-600 md:col-span-2"><span class="md:sr-only">Cena/j. bez DPH</span><input v-model.number="item.price_net" type="number" min="0" step="0.01" required class="text-right" :class="input" /></label>
                                                <label class="text-xs text-gray-600"><span class="md:sr-only">Zľava %</span><input v-model.number="item.discount_percent" type="number" min="0" max="100" step="0.01" class="text-right" :class="input" /></label>
                                                <label class="text-xs text-gray-600"><span class="md:sr-only">DPH</span>
                                                    <select v-model.number="item.vat" :disabled="!item.is_custom" :class="input"><option v-for="rate in VAT_RATES" :key="rate" :value="rate">{{ rate }} %</option></select>
                                                </label>
                                                <div class="col-span-2 flex items-center justify-end gap-2 md:col-span-2">
                                                    <div class="text-right">
                                                        <p class="text-sm font-semibold whitespace-nowrap text-gray-900">{{ money(quoteLine(item).lineNet) }}</p>
                                                        <p class="text-xs whitespace-nowrap text-gray-500">s DPH {{ money(quoteLine(item).lineGross) }}</p>
                                                    </div>
                                                    <button v-if="!closed" type="button" class="rounded-lg p-2 text-gray-500 transition hover:bg-red-50 hover:text-red-600" title="Odstrániť" :aria-label="`Odstrániť ${item.name}`" @click="form.items.splice(i, 1)">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-3 md:pl-[3.75rem]">
                                                <input v-model="item.description" maxlength="2000" placeholder="Popis — rozmer, materiál, potlač" aria-label="Popis položky" class="min-w-[14rem] flex-1" :class="input" />
                                                <div v-if="!closed" class="flex items-center gap-2 text-xs">
                                                    <button type="button" class="font-semibold text-blue-700" :disabled="item.alternatives.length >= 5" @click="addAlternative(item)">+ cena pre iné množstvo</button>
                                                    <button type="button" class="px-1 text-gray-500" aria-label="Posunúť vyššie" :disabled="i === 0" @click="move(i, -1)">▲</button>
                                                    <button type="button" class="px-1 text-gray-500" aria-label="Posunúť nižšie" :disabled="i === form.items.length - 1" @click="move(i, 1)">▼</button>
                                                </div>
                                            </div>
                                            <!-- Cenové varianty podľa množstva: zákazník si vyberie pri prijatí. -->
                                            <div v-for="(alt, a) in item.alternatives" :key="a" class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-3 py-1.5 text-xs md:ml-[3.75rem]">
                                                <span class="text-gray-600">alebo</span>
                                                <input v-model.number="alt.quantity" type="number" min="1" max="100000" aria-label="Množstvo" class="w-20 text-center" :class="input" /><span class="text-gray-600">{{ item.unit }} ×</span>
                                                <input v-model.number="alt.price_net" type="number" min="0" step="0.01" aria-label="Cena za jednotku bez DPH" class="w-24 text-right" :class="input" /><span class="text-gray-600">€ bez DPH</span>
                                                <span class="ml-auto font-semibold text-gray-800">{{ money(quoteLine({ ...item, ...alt }).lineNet) }}</span>
                                                <button v-if="!closed" type="button" class="rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-600" aria-label="Odobrať množstvo" @click="item.alternatives.splice(a, 1)">✕</button>
                                            </div>
                                        </li>
                                    </ul>
                                </template>
                            </div>

                            <!-- Pridanie položiek — rovnaké nástroje ako v košíku -->
                            <details v-if="!closed" class="staff-tools" open>
                                <summary class="staff-tools-summary">Pridať položky</summary>
                                <div class="staff-tools-body">
                                    <form class="flex gap-2" @submit.prevent="findProducts">
                                        <input v-model="productSearch" type="search" placeholder="Vyhľadať tovar v katalógu" aria-label="Vyhľadať tovar" :class="input" />
                                        <button type="submit" class="whitespace-nowrap rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-gray-400" :disabled="busy">Hľadať</button>
                                    </form>
                                    <ul v-if="products.length" class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white text-sm">
                                        <li v-for="p in products" :key="p.id" class="flex flex-wrap items-center gap-2 px-3 py-2">
                                            <img :src="p.thumb" width="32" height="32" loading="lazy" alt="" class="h-8 w-8 rounded border border-gray-200 object-cover" />
                                            <span class="font-medium">{{ p.name }}</span>
                                            <button v-for="v in p.variants" :key="v.id" type="button" class="rounded bg-gray-100 px-2 py-0.5 text-xs hover:bg-blue-100" @click="addVariant(p, v)">+ {{ v.name || v.code }} ({{ money(netFromGross(v.active_price ?? 0, p.vat ?? 23)) }} bez DPH)</button>
                                        </li>
                                    </ul>
                                    <CustomOrderItem price-label="Jednotková cena bez DPH (€)" @add="addCustom" />
                                </div>
                            </details>

                            <!-- Fakturačné údaje — CustomerFormFields z košíka -->
                            <div :class="card">
                                <div :class="cardHead"><h2 class="text-base font-semibold text-gray-800">Fakturačné údaje</h2></div>
                                <div class="px-6 py-5">
                                    <fieldset class="mb-4">
                                        <legend class="sr-only">Typ zákazníka</legend>
                                        <div class="filter-radio-group">
                                            <label :class="['filter-radio-label min-h-9 text-sm', !isPrivatePerson && 'filter-radio-active']"><input type="radio" :value="false" v-model="isPrivatePerson" class="sr-only" />Firma</label>
                                            <label :class="['filter-radio-label min-h-9 text-sm', isPrivatePerson && 'filter-radio-active']"><input type="radio" :value="true" v-model="isPrivatePerson" class="sr-only" />Súkromná osoba</label>
                                        </div>
                                    </fieldset>
                                    <div v-if="!closed" class="relative mb-4">
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700" for="quote-customer-search">Vybrať z adresára zákazníkov</label>
                                        <input id="quote-customer-search" v-model="customerSearch" type="search" placeholder="Firma, IČO, e-mail…" :class="input" @input="searchCustomers" />
                                        <ul v-if="customerResults.length" class="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                                            <li v-for="c in customerResults" :key="c.id"><button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-blue-50" @click="pickCustomer(c)">
                                                <strong>{{ c.company || c.name }}</strong> <span class="text-gray-600">{{ c.ico ? 'IČO ' + c.ico : '' }} {{ c.email }}</span></button></li>
                                        </ul>
                                        <p v-if="header.customer_id" class="mt-1.5 text-sm text-gray-700">Prepojené so zákazníkom <router-link :to="'/zakaznici/' + header.customer_id + '/show'" class="text-blue-700 underline">#{{ header.customer_id }}</router-link>
                                            <button type="button" class="ml-2 text-xs text-gray-600 underline" @click="header.customer_id = null">zrušiť prepojenie</button></p>
                                    </div>
                                    <CustomerFormFields :hideCompany="isPrivatePerson" :fieldErrors="getFieldErrors"
                                        :requiredFields="(isPrivatePerson ? [] : ['company']).concat(['name', 'email', 'street', 'postcode', 'city'])" />
                                </div>
                            </div>

                            <!-- Zadanie -->
                            <div :class="card">
                                <div :class="cardHead"><h2 class="text-base font-semibold text-gray-800">Zadanie</h2></div>
                                <div class="grid gap-4 px-6 py-5 md:grid-cols-2">
                                    <label class="block text-sm font-semibold text-gray-700 md:col-span-2">Predmet ponuky <span class="font-normal text-gray-500">(vidí zákazník)</span>
                                        <input v-model="header.title" maxlength="200" placeholder="Napr. Zástavy na námestie obce" class="mt-1.5" :class="input" />
                                    </label>
                                    <label class="block text-sm font-semibold text-gray-700">Požiadavka zákazníka
                                        <textarea v-model="header.brief" rows="4" maxlength="10000" class="mt-1.5" :class="input" />
                                    </label>
                                    <label class="block text-sm font-semibold text-gray-700">Interná poznámka <span class="font-normal text-gray-500">(zákazník ju nevidí)</span>
                                        <textarea v-model="header.internal_note" rows="4" maxlength="10000" class="mt-1.5" :class="input" />
                                    </label>
                                    <div v-if="quote?.attachments?.length" class="text-sm md:col-span-2"><p class="font-semibold text-gray-700">Podklady od zákazníka</p>
                                        <button v-for="file in quote.attachments" :key="file.id" type="button" class="mr-3 text-blue-700 underline" @click="run(() => downloadSales('/public-quotes/' + quote.uuid + '/files/' + file.id, file.name))">{{ file.name }}</button>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Pravý stĺpec: súhrn, doprava, podmienky, odoslanie (pb na mobile: miesto pre lepiacu lištu) -->
                    <aside class="space-y-4 pb-20 lg:pb-0">
                        <div class="sticky top-4 space-y-4">
                            <div v-if="form.items.length" :class="card">
                                <div class="border-b border-gray-100 bg-gray-50 px-5 py-3"><h2 class="text-sm font-semibold text-gray-700">Položky ({{ form.items.length }})</h2></div>
                                <div class="divide-y divide-gray-50 px-5 py-3">
                                    <div v-for="(item, i) in form.items" :key="i" class="flex items-start justify-between gap-2 py-1.5 text-sm">
                                        <span class="leading-snug text-gray-600">{{ item.name || 'Vlastná položka' }}<span v-if="item.variant_name" class="text-gray-500"> ({{ item.variant_name }})</span>
                                            <span class="text-gray-500"> × {{ item.quantity }}</span><span v-if="item.alternatives.length" class="text-amber-700"> +{{ item.alternatives.length }} mn.</span></span>
                                        <span class="shrink-0 font-medium text-gray-900">{{ money(quoteLine(item).lineNet) }}</span>
                                    </div>
                                </div>
                            </div>

                            <fieldset :disabled="closed" :class="card">
                                <div class="border-b border-gray-100 bg-gray-50 px-5 py-3"><h2 class="text-sm font-semibold text-gray-700">Doprava a podmienky</h2></div>
                                <div class="space-y-3 px-5 py-4 text-sm">
                                    <div class="grid grid-cols-2 gap-3">
                                        <label class="col-span-2 block font-semibold text-gray-700">Doprava
                                            <select v-model="form.shipping_method_id" class="mt-1" :class="input" @change="pickShipping"><option value="">Vyberte</option><option v-for="m in methods" :key="m.id" :value="m.id">{{ m.name }}</option></select>
                                        </label>
                                        <label class="block font-semibold text-gray-700">Doprava bez DPH<input v-model.number="form.shipping_price_net" type="number" min="0" step="0.01" class="mt-1 text-right" :class="input" /></label>
                                        <label class="block font-semibold text-gray-700">Zľava na ponuku %<input v-model.number="form.discount_percent" type="number" min="0" max="100" step="0.01" class="mt-1 text-right" :class="input" /></label>
                                        <label class="col-span-2 block font-semibold text-gray-700">Platná do<input v-model="form.valid_until" type="date" class="mt-1" :class="input" /></label>
                                        <label class="col-span-2 block font-semibold text-gray-700">Podmienky a termín dodania
                                            <textarea v-model="form.terms" rows="3" maxlength="10000" placeholder="Napr. Dodanie do 14 pracovných dní od schválenia grafiky." class="mt-1 font-normal" :class="input" />
                                        </label>
                                    </div>

                                    <dl class="space-y-1 border-t border-gray-100 pt-3">
                                        <div class="flex justify-between"><dt class="text-gray-600">Položky bez DPH</dt><dd>{{ money(totals.itemsNet) }}</dd></div>
                                        <div v-if="totals.discountNet" class="flex justify-between text-green-700"><dt>Zľava {{ form.discount_percent }} %</dt><dd>−{{ money(totals.discountNet) }}</dd></div>
                                        <div class="flex justify-between"><dt class="text-gray-600">Doprava bez DPH</dt><dd>{{ money(totals.shippingNet) }}</dd></div>
                                        <div class="flex justify-between font-semibold"><dt>Spolu bez DPH</dt><dd>{{ money(totals.net) }}</dd></div>
                                        <div class="flex justify-between"><dt class="text-gray-600">DPH</dt><dd>{{ money(totals.vat) }}</dd></div>
                                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold text-gray-900"><dt>Celkom s DPH</dt><dd>{{ money(totals.gross) }}</dd></div>
                                        <p v-if="hasAlternatives" class="text-xs text-gray-500">Súčet je pre základné množstvá; zákazník si môže vybrať iné.</p>
                                    </dl>

                                    <template v-if="!closed">
                                        <label class="flex items-center gap-2 text-gray-700"><input v-model="notify" type="checkbox" class="rounded" /> Poslať zákazníkovi e-mail s PDF</label>
                                        <button type="button" class="inline-flex w-full items-center justify-center rounded-lg bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-gray-400" :disabled="busy || !form.items.length" @click="send">
                                            {{ busy ? 'Ukladám...' : (quote?.version ? 'Odoslať novú verziu' : 'Odoslať ponuku') }}
                                        </button>
                                        <button type="button" class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:opacity-50" :disabled="busy" @click="saveDraft">Uložiť koncept</button>
                                    </template>
                                </div>
                            </fieldset>

                            <div v-if="quote" :class="card" class="px-5 py-4 text-sm">
                                <div class="flex flex-col items-start gap-2">
                                    <button v-if="quote.versions.length" type="button" class="font-semibold text-blue-700 underline" :disabled="busy" @click="pdf()">{{ hasDraft ? 'Náhľad PDF (koncept)' : 'Stiahnuť PDF' }}</button>
                                    <template v-if="!closed">
                                        <button v-if="hasDraft && quote.version" type="button" class="text-gray-700 underline" :disabled="busy" @click="discard">Zahodiť neodoslané zmeny</button>
                                        <button type="button" class="text-gray-700 underline" :disabled="busy" @click="getShare">Vytvoriť nový odkaz pre zákazníka</button>
                                        <button type="button" class="text-red-700 underline" :disabled="busy" @click="withdraw">Stiahnuť ponuku</button>
                                    </template>
                                </div>
                                <div v-if="share" class="mt-3"><p class="text-gray-600">Nový odkaz zneplatnil predchádzajúce.</p><input :value="share" readonly aria-label="Odkaz pre zákazníka" class="mt-1 w-full rounded border p-2" @focus="$event.target.select()" /></div>
                            </div>
                        </div>
                    </aside>
                </div>

                <details v-if="sent.length" class="mt-6 rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-sm">
                    <summary class="cursor-pointer font-semibold text-gray-700">Odoslané verzie ({{ sent.length }})</summary>
                    <div v-for="v in sent" :key="v.id" class="mt-3 border-t border-gray-100 pt-3">
                        <p class="font-semibold">Verzia {{ v.version }} · odoslaná {{ date(v.sent_at) }} · platná do {{ v.valid_until?.slice(0, 10) }}
                            <button type="button" class="ml-2 font-normal text-blue-700 underline" @click="pdf(v.version)">PDF</button></p>
                        <p v-for="(i, n) in v.items" :key="n" class="text-gray-700">{{ i.snapshot.name }}{{ i.snapshot.variant_name ? ' / ' + i.snapshot.variant_name : '' }}: {{ i.quantity }} × {{ money(i.unit_net ?? i.price) }}</p>
                        <p v-if="v.totals" class="text-gray-700">Spolu {{ money(v.totals.net) }} bez DPH · {{ money(v.totals.gross) }} s DPH</p>
                    </div>
                </details>

                <!-- Mobil: suma a odoslanie stále na dosah ako v košíku -->
                <div v-if="!closed && form.items.length" class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-4 border-t border-gray-200 bg-white px-4 py-3 shadow-[0_-4px_12px_rgba(0,0,0,0.08)] lg:hidden">
                    <div class="min-w-0">
                        <div class="text-xs text-gray-600">Celkom s DPH</div>
                        <div class="text-lg font-bold text-blue-800">{{ money(totals.gross) }}</div>
                    </div>
                    <button type="button" class="ml-auto inline-flex items-center justify-center rounded-lg bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-gray-400" :disabled="busy" @click="send">
                        {{ quote?.version ? 'Odoslať novú verziu' : 'Odoslať ponuku' }}
                    </button>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>
