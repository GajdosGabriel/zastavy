<script setup>
import {ref,computed,onMounted} from 'vue';import {useRoute,useRouter} from 'vue-router';import BaseLayout from '../layout/BaseLayout.vue';import PageHeader from '../layout/page/pageHeader.vue';import axios from '../../axiosInstance';
import {salesError,shareUrl,downloadSales,quoteLabels,quoteTones,money,quoteLine,quoteTotals,netFromGross,VAT_RATES,SHIPPING_VAT} from '../../models/sales';
const route=useRoute(),router=useRouter();
const quote=ref(null),error=ref(''),busy=ref(false),saved=ref(''),share=ref(''),methods=ref([]),notify=ref(true);
const emptyCustomer=()=>({company:'',name:'',email:'',phone:'',street:'',postcode:'',city:'',ico:'',dic:'',ic_dic:''});
const header=ref({customer:emptyCustomer(),customer_id:null,title:'',brief:'',internal_note:''});
const inDays=d=>{const t=new Date();t.setDate(t.getDate()+d);return t.toISOString().slice(0,10);};
const form=ref({items:[],discount_percent:0,shipping_method_id:'',shipping_price_net:0,terms:'',valid_until:inDays(14)});
const closed=computed(()=>['accepted','withdrawn'].includes(quote.value?.status));
const sent=computed(()=>(quote.value?.versions??[]).filter(v=>v.sent_at));
const hasDraft=computed(()=>!!quote.value?.has_draft);
const totals=computed(()=>quoteTotals(form.value.items,form.value.discount_percent,form.value.shipping_price_net));
const fields={company:'Firma / meno zákazníka',name:'Kontaktná osoba',email:'E-mail',phone:'Telefón',street:'Ulica a číslo',postcode:'PSČ',city:'Mesto',ico:'IČO',dic:'DIČ',ic_dic:'IČ DPH'};
const date=v=>v?new Date(v).toLocaleString('sk-SK'):'';

function row(i){const vat=i.snapshot?.vat??i.vat??23;
 return {is_custom:!!i.is_custom,variant_id:i.variant_id??null,name:i.snapshot?.name??i.name??'',variant_name:i.snapshot?.variant_name??'',description:i.description??'',unit:i.snapshot?.unit_value??i.unit??'ks',vat,
  quantity:i.quantity??1,price_net:i.price_net??(i.price!=null?netFromGross(i.price,vat):''),discount_percent:i.discount_percent??0,min_order:i.min_order??null};}
function apply(data){quote.value=data;const c=data.customer??{};
 header.value={customer:{...emptyCustomer(),...Object.fromEntries(Object.entries(c).map(([k,v])=>[k,v??'']))},customer_id:data.customer_id??null,title:data.title??'',brief:data.brief??'',internal_note:data.internal_note??''};
 const base=data.versions.find(v=>!v.sent_at)??data.versions[0];
 form.value=base?{items:base.items.map(row),discount_percent:base.discount_percent??0,shipping_method_id:base.shipping?.id??'',shipping_price_net:base.shipping?.price_net??(base.shipping?netFromGross(base.shipping.price,SHIPPING_VAT):0),terms:base.terms??'',valid_until:base.valid_until?.slice(0,10)??inDays(14)}
  :{...form.value,items:(data.requested_items??[]).map(i=>row({...i,name:i.name}))};}
async function load(){apply((await axios.get('/sales-quotes/'+route.params.id)).data);}
onMounted(async()=>{busy.value=true;try{const m=axios.get('/shipping-methods');if(route.params.id)await load();methods.value=(await m).data.data;}catch(e){error.value=salesError(e);}finally{busy.value=false;}});

async function run(fn,msg=''){if(busy.value)return;busy.value=true;error.value='';saved.value='';try{await fn();saved.value=msg;}catch(e){error.value=salesError(e);}finally{busy.value=false;}}
const clean=o=>Object.fromEntries(Object.entries(o).map(([k,v])=>[k,v===''?null:v]));
function payload(){return {version:quote.value?.version??0,...header.value,customer:clean(header.value.customer),
 items:form.value.items.map(i=>({is_custom:i.is_custom,variant_id:i.variant_id,name:i.name,description:i.description||null,unit:i.unit,vat:i.vat,quantity:i.quantity,price_net:i.price_net===''?null:i.price_net,discount_percent:i.discount_percent||0})),
 discount_percent:form.value.discount_percent||0,shipping_method_id:form.value.shipping_method_id||null,shipping_price_net:form.value.shipping_price_net||0,terms:form.value.terms||null,valid_until:form.value.valid_until||null};}
async function ensureQuote(){if(quote.value)return;const {data}=await axios.post('/sales-quotes',{...header.value,customer:clean(header.value.customer)});quote.value=data;await router.replace('/predaj/ponuky/'+data.id);}
function saveDraft(){return run(async()=>{await ensureQuote();apply((await axios.put('/sales-quotes/'+quote.value.id,payload())).data);},'Koncept je uložený. Zákazník ho zatiaľ nevidí.');}
function send(){const email=header.value.customer.email;
 if(!window.confirm(notify.value?`Odoslať ponuku zákazníkovi na ${email}?`:'Sprístupniť ponuku bez e-mailu? Odkaz pošlete zákazníkovi sami.'))return;
 return run(async()=>{await ensureQuote();apply((await axios.post('/sales-quotes/'+quote.value.id+'/offer',{...payload(),notify:notify.value})).data);},notify.value?'Ponuka je odoslaná zákazníkovi e-mailom.':'Ponuka je sprístupnená.');}
function discard(){if(!window.confirm('Zahodiť rozpracované zmeny a vrátiť sa k poslednej odoslanej verzii?'))return;return run(async()=>apply((await axios.delete('/sales-quotes/'+quote.value.id+'/draft')).data));}
function withdraw(){if(!window.confirm('Stiahnuť ponuku? Zákazník ju už nebude môcť prijať.'))return;return run(async()=>apply((await axios.post('/sales-quotes/'+quote.value.id+'/withdraw')).data));}
function getShare(){return run(async()=>{share.value=shareUrl('ponuka',(await axios.post('/sales-quotes/'+quote.value.id+'/share')).data);});}

// Zákazník: z adresára alebo podľa IČO z registra.
const customerSearch=ref(''),customerResults=ref([]);let customerTimer;
function searchCustomers(){clearTimeout(customerTimer);if(!customerSearch.value.trim()){customerResults.value=[];return;}
 customerTimer=setTimeout(async()=>{try{customerResults.value=(await axios.get('/customers',{params:{bySearchInput:customerSearch.value,per_page:8}})).data.data??[];}catch(e){error.value=salesError(e);}},300);}
function pickCustomer(c){header.value.customer_id=c.id;for(const k of Object.keys(fields))header.value.customer[k]=c[k]??'';customerSearch.value='';customerResults.value=[];}
function unlinkCustomer(){header.value.customer_id=null;}
function findByIco(){const ico=String(header.value.customer.ico).replace(/\D/g,'');if(!ico)return;
 return run(async()=>{const c=(await axios.get('/checkouts/'+ico)).data.data||{};for(const k of ['company','street','postcode','city','dic','ic_dic'])if(c[k])header.value.customer[k]=c[k];header.value.customer.ico=c.ico||ico;},'Údaje firmy sú doplnené z registra.');}

// Položky
const productSearch=ref(''),products=ref([]);
function findProducts(){return run(async()=>{products.value=(await axios.get('/homes',{params:{bySearchInput:productSearch.value}})).data.data;});}
function addVariant(p,v){const min=Math.max(1,v.min_order??1);
 form.value.items.push({is_custom:false,variant_id:v.id,name:p.name,variant_name:v.name??'',description:'',unit:p.unit_value||'ks',vat:p.vat??23,quantity:min,price_net:netFromGross(v.active_price??v.price??0,p.vat??23),discount_percent:0,min_order:min});}
function addCustom(){form.value.items.push({is_custom:true,variant_id:null,name:'',variant_name:'',description:'',unit:'ks',vat:23,quantity:1,price_net:'',discount_percent:0,min_order:null});}
function move(i,d){const items=form.value.items,j=i+d;if(j<0||j>=items.length)return;[items[i],items[j]]=[items[j],items[i]];}
function pickShipping(){const m=methods.value.find(m=>m.id===form.value.shipping_method_id);if(m&&!Number(form.value.shipping_price_net))form.value.shipping_price_net=netFromGross(m.price??0,SHIPPING_VAT);}
const input='mt-1 block w-full rounded border border-gray-300 px-2 py-1.5 text-sm disabled:bg-gray-50';
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12 space-y-5">
                <PageHeader :item="{ title: quote ? 'Cenová ponuka ' + (quote.number ?? '#' + quote.id) : 'Nová cenová ponuka', buttonLink: { name: 'Späť na ponuky', link: '/predaj/ponuky', icon: 'arrow-left' } }" />

                <div v-if="quote" class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="quote.is_expired ? 'bg-red-100 text-red-700' : quoteTones[quote.status]">{{ quote.is_expired ? 'Po platnosti' : quoteLabels[quote.status] }}</span>
                    <span v-if="quote.version" class="text-gray-600">odoslaná verzia {{ quote.version }}</span>
                    <span v-if="hasDraft" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">neodoslané zmeny</span>
                    <span v-if="quote.sent_at" class="text-gray-600">· odoslaná {{ date(quote.sent_at) }}{{ quote.sent_to ? ' na ' + quote.sent_to : '' }}</span>
                    <span v-if="quote.viewed_at" class="text-green-700">· zákazník ju zobrazil {{ date(quote.viewed_at) }}</span>
                    <span v-else-if="quote.status === 'offered'" class="text-gray-500">· zatiaľ nezobrazená</span>
                    <router-link v-if="quote.order_uuid" :to="'/objednavka/' + quote.order_uuid" class="font-semibold text-blue-700 underline">Objednávka {{ quote.order_serial_number }}</router-link>
                </div>

                <p v-if="error" role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ error }}</p>
                <p v-if="saved" role="status" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ saved }}</p>

                <fieldset :disabled="closed" class="space-y-5">
                    <!-- Zákazník -->
                    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <h2 class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-semibold text-gray-700">Zákazník</h2>
                        <div class="space-y-3 p-5">
                            <div v-if="!closed" class="relative">
                                <label class="text-sm text-gray-700">Vybrať z adresára zákazníkov
                                    <input v-model="customerSearch" type="search" placeholder="Firma, IČO, e-mail…" :class="input" @input="searchCustomers" />
                                </label>
                                <ul v-if="customerResults.length" class="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded border border-gray-200 bg-white shadow-lg">
                                    <li v-for="c in customerResults" :key="c.id"><button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-blue-50" @click="pickCustomer(c)">
                                        <strong>{{ c.company || c.name }}</strong> <span class="text-gray-600">{{ c.ico ? 'IČO ' + c.ico : '' }} {{ c.email }}</span></button></li>
                                </ul>
                            </div>
                            <p v-if="header.customer_id" class="text-sm text-gray-700">Prepojené so zákazníkom v adresári <router-link :to="'/zakaznici/' + header.customer_id + '/show'" class="text-blue-700 underline">#{{ header.customer_id }}</router-link>
                                <button v-if="!closed" type="button" class="ml-2 text-xs text-gray-600 underline" @click="unlinkCustomer">zrušiť prepojenie</button></p>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                <label v-for="(label, key) in fields" :key="key" class="text-sm text-gray-700" :class="{ 'lg:col-span-2': key === 'company' || key === 'street' }">{{ label }}
                                    <span v-if="key === 'ico' && !closed" class="flex gap-1">
                                        <input v-model="header.customer.ico" inputmode="numeric" maxlength="8" :class="input" />
                                        <button type="button" class="mt-1 rounded border border-gray-300 px-2 text-xs" :disabled="busy || !header.customer.ico" @click="findByIco">Register</button>
                                    </span>
                                    <input v-else v-model="header.customer[key]" :type="key === 'email' ? 'email' : 'text'" :class="input" />
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- Zadanie -->
                    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <h2 class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-semibold text-gray-700">Zadanie</h2>
                        <div class="grid gap-3 p-5 lg:grid-cols-2">
                            <label class="text-sm text-gray-700 lg:col-span-2">Predmet ponuky (vidí zákazník)
                                <input v-model="header.title" maxlength="200" placeholder="Napr. Zástavy na námestie obce" :class="input" />
                            </label>
                            <label class="text-sm text-gray-700">Požiadavka zákazníka
                                <textarea v-model="header.brief" rows="4" maxlength="10000" :class="input" />
                            </label>
                            <label class="text-sm text-gray-700">Interná poznámka (zákazník ju nevidí)
                                <textarea v-model="header.internal_note" rows="4" maxlength="10000" :class="input" />
                            </label>
                            <div v-if="quote?.attachments?.length" class="text-sm lg:col-span-2"><p class="font-semibold text-gray-700">Podklady od zákazníka</p>
                                <button v-for="file in quote.attachments" :key="file.id" type="button" class="mr-3 text-blue-700 underline" @click="run(() => downloadSales('/public-quotes/' + quote.uuid + '/files/' + file.id, file.name))">{{ file.name }}</button>
                            </div>
                        </div>
                    </section>

                    <!-- Položky -->
                    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <h2 class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-semibold text-gray-700">Položky <span class="font-normal text-gray-500">— ceny bez DPH</span></h2>
                        <div class="space-y-3 p-5">
                            <p v-if="!form.items.length" class="text-sm text-gray-600">Pridajte tovar z katalógu alebo vlastnú položku (atypický rozmer, grafika, montáž…).</p>
                            <div v-for="(item, i) in form.items" :key="i" class="grid grid-cols-2 gap-2 rounded-lg border border-gray-200 p-3 sm:grid-cols-12">
                                <div class="col-span-2 sm:col-span-4">
                                    <template v-if="item.is_custom">
                                        <label class="text-xs text-gray-600">Vlastná položka<input v-model="item.name" maxlength="200" required placeholder="Názov položky" :class="input" /></label>
                                    </template>
                                    <p v-else class="text-sm font-medium text-gray-900">{{ item.name }}<span v-if="item.variant_name" class="text-gray-600"> — {{ item.variant_name }}</span>
                                        <span v-if="item.min_order > 1" class="block text-xs text-gray-500">min. odber {{ item.min_order }}</span></p>
                                    <label class="mt-1 block text-xs text-gray-600">Popis (rozmer, materiál, potlač)<textarea v-model="item.description" rows="1" maxlength="2000" :class="input" /></label>
                                </div>
                                <label class="text-xs text-gray-600 sm:col-span-1">Množstvo<input v-model.number="item.quantity" type="number" min="1" max="100000" required :class="input" /></label>
                                <label class="text-xs text-gray-600 sm:col-span-1">Jednotka<input v-model="item.unit" maxlength="20" :disabled="!item.is_custom" :class="input" /></label>
                                <label class="text-xs text-gray-600 sm:col-span-2">Cena/j. bez DPH<input v-model.number="item.price_net" type="number" min="0" step="0.01" required :class="input" /></label>
                                <label class="text-xs text-gray-600 sm:col-span-1">Zľava %<input v-model.number="item.discount_percent" type="number" min="0" max="100" step="0.01" :class="input" /></label>
                                <label class="text-xs text-gray-600 sm:col-span-1">DPH
                                    <select v-model.number="item.vat" :disabled="!item.is_custom" :class="input"><option v-for="rate in VAT_RATES" :key="rate" :value="rate">{{ rate }} %</option></select>
                                </label>
                                <div class="text-right text-sm sm:col-span-2">
                                    <p class="text-xs text-gray-600">Spolu bez DPH</p>
                                    <p class="font-semibold">{{ money(quoteLine(item).lineNet) }}</p>
                                    <p class="text-xs text-gray-500">s DPH {{ money(quoteLine(item).lineGross) }}</p>
                                    <div v-if="!closed" class="mt-1 flex justify-end gap-2 text-xs">
                                        <button type="button" class="text-gray-600" aria-label="Posunúť vyššie" :disabled="i === 0" @click="move(i, -1)">▲</button>
                                        <button type="button" class="text-gray-600" aria-label="Posunúť nižšie" :disabled="i === form.items.length - 1" @click="move(i, 1)">▼</button>
                                        <button type="button" class="text-red-700 underline" @click="form.items.splice(i, 1)">Odobrať</button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="!closed" class="flex flex-wrap items-end gap-2 border-t border-gray-100 pt-3">
                                <form class="flex gap-2" @submit.prevent="findProducts">
                                    <input v-model="productSearch" type="search" placeholder="Vyhľadať tovar v katalógu" aria-label="Vyhľadať tovar" class="rounded border border-gray-300 px-2 py-1.5 text-sm" />
                                    <button type="submit" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold" :disabled="busy">Hľadať</button>
                                </form>
                                <button type="button" class="rounded border border-blue-300 bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-800" @click="addCustom">+ Vlastná položka</button>
                            </div>
                            <ul v-if="products.length && !closed" class="divide-y divide-gray-100 rounded border border-gray-200 text-sm">
                                <li v-for="p in products" :key="p.id" class="flex flex-wrap items-center gap-2 px-3 py-2"><span class="font-medium">{{ p.name }}</span>
                                    <button v-for="v in p.variants" :key="v.id" type="button" class="rounded bg-gray-100 px-2 py-0.5 text-xs hover:bg-blue-100" @click="addVariant(p, v)">+ {{ v.name || v.code }} ({{ money(netFromGross(v.active_price ?? 0, p.vat ?? 23)) }} bez DPH)</button>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <!-- Doprava, zľava, platnosť -->
                    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <h2 class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-semibold text-gray-700">Doprava a podmienky</h2>
                        <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="text-sm text-gray-700">Doprava
                                <select v-model="form.shipping_method_id" :class="input" @change="pickShipping"><option value="">Vyberte</option><option v-for="m in methods" :key="m.id" :value="m.id">{{ m.name }}</option></select>
                            </label>
                            <label class="text-sm text-gray-700">Cena dopravy bez DPH<input v-model.number="form.shipping_price_net" type="number" min="0" step="0.01" :class="input" /></label>
                            <label class="text-sm text-gray-700">Zľava na celú ponuku %<input v-model.number="form.discount_percent" type="number" min="0" max="100" step="0.01" :class="input" /></label>
                            <label class="text-sm text-gray-700">Platná do<input v-model="form.valid_until" type="date" :class="input" /></label>
                            <label class="text-sm text-gray-700 sm:col-span-2 lg:col-span-4">Podmienky a termín dodania (vidí zákazník)
                                <textarea v-model="form.terms" rows="3" maxlength="10000" placeholder="Napr. Dodanie do 14 pracovných dní od schválenia grafiky." :class="input" />
                            </label>
                        </div>
                    </section>
                </fieldset>

                <!-- Súčty a akcie -->
                <section class="sticky bottom-0 z-10 rounded-xl border border-blue-200 bg-white p-4 shadow-lg">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-4">
                            <div><dt class="text-gray-600">Spolu bez DPH</dt><dd class="font-semibold">{{ money(totals.net) }}</dd></div>
                            <div v-if="totals.discountNet"><dt class="text-gray-600">z toho zľava</dt><dd class="text-green-700">−{{ money(totals.discountNet) }}</dd></div>
                            <div><dt class="text-gray-600">DPH</dt><dd>{{ money(totals.vat) }}</dd></div>
                            <div><dt class="text-gray-600">Spolu s DPH</dt><dd class="text-lg font-bold text-blue-900">{{ money(totals.gross) }}</dd></div>
                        </dl>
                        <div v-if="!closed" class="flex flex-wrap items-center gap-2">
                            <label class="flex items-center gap-1 text-sm text-gray-700"><input v-model="notify" type="checkbox" class="h-4 w-4" /> poslať e-mail zákazníkovi</label>
                            <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:opacity-50" :disabled="busy" @click="saveDraft">Uložiť koncept</button>
                            <button type="button" class="rounded bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50" :disabled="busy || !form.items.length" @click="send">{{ quote?.version ? 'Odoslať novú verziu' : 'Odoslať ponuku' }}</button>
                        </div>
                    </div>
                </section>

                <section v-if="quote && !closed" class="flex flex-wrap gap-4 text-sm">
                    <button v-if="hasDraft && quote.version" type="button" class="text-gray-700 underline" :disabled="busy" @click="discard">Zahodiť neodoslané zmeny</button>
                    <button type="button" class="text-gray-700 underline" :disabled="busy" @click="getShare">Vytvoriť nový odkaz pre zákazníka</button>
                    <button type="button" class="text-red-700 underline" :disabled="busy" @click="withdraw">Stiahnuť ponuku</button>
                </section>
                <div v-if="share" class="text-sm"><p class="text-gray-600">Nový odkaz zneplatnil predchádzajúce.</p><input :value="share" readonly aria-label="Odkaz pre zákazníka" class="w-full rounded border p-2" @focus="$event.target.select()" /></div>

                <details v-if="sent.length" class="rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-sm">
                    <summary class="cursor-pointer font-semibold text-gray-700">Odoslané verzie ({{ sent.length }})</summary>
                    <div v-for="v in sent" :key="v.id" class="mt-3 border-t border-gray-100 pt-3">
                        <p class="font-semibold">Verzia {{ v.version }} · odoslaná {{ date(v.sent_at) }} · platná do {{ v.valid_until?.slice(0, 10) }}</p>
                        <p v-for="(i, n) in v.items" :key="n" class="text-gray-700">{{ i.snapshot.name }}{{ i.snapshot.variant_name ? ' / ' + i.snapshot.variant_name : '' }}: {{ i.quantity }} × {{ money(i.unit_net ?? i.price) }}</p>
                        <p v-if="v.totals" class="text-gray-700">Spolu {{ money(v.totals.net) }} bez DPH · {{ money(v.totals.gross) }} s DPH</p>
                    </div>
                </details>
            </div>
        </template>
    </BaseLayout>
</template>
