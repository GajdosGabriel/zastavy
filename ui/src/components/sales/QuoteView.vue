<script setup>
import {ref,computed,onMounted} from 'vue';import {useRoute,useRouter} from 'vue-router';import BaseLayout from '../layout/BaseLayout.vue';import axios from '../../axiosInstance';import {salesError,tokenHeaders,downloadSales,quoteLabels,money} from '../../models/sales';
const route=useRoute(),router=useRouter(),token=new URLSearchParams(route.hash.slice(1)).get('token')??'';
const quote=ref(null),error=ref(''),busy=ref(false),name=ref(''),confirm=ref(false);
const latest=computed(()=>quote.value?.versions?.[0]);
// Staršie verzie nemajú uložené súčty — ceny v nich sú s DPH.
const totals=computed(()=>latest.value?.totals??(latest.value?{gross:latest.value.items.reduce((s,i)=>s+i.quantity*Number(i.price),Number(latest.value.shipping?.price??0))}:null));
const pct=v=>Number(v).toLocaleString('sk-SK',{maximumFractionDigits:2})+' %';
const date=v=>v?new Date(v).toLocaleDateString('sk-SK'):'';
async function run(fn){if(busy.value)return;busy.value=true;error.value='';try{await fn();}catch(e){error.value=salesError(e);}finally{busy.value=false;}}
async function load(){quote.value=(await axios.get('/public-quotes/'+route.params.uuid,{headers:tokenHeaders(token)})).data;}
onMounted(()=>run(load));
function accept(){return run(async()=>{const {data}=await axios.post('/public-quotes/'+quote.value.uuid+'/accept',{version:quote.value.version,name:name.value,confirm:confirm.value},{headers:tokenHeaders(token)});await router.push('/objednavka/'+data.order_uuid);});}
</script>
<template><BaseLayout><template #main><section class="col-span-12 mx-auto w-full max-w-4xl space-y-5 p-4">
<h1 class="text-2xl font-semibold">Cenová ponuka <span v-if="quote?.number">č. {{ quote.number }}</span></h1>
<p v-if="error" role="alert" class="text-red-700">{{ error }}</p>
<div v-if="quote" class="space-y-5">
<p class="font-semibold">{{ quote.title }}</p>
<p v-if="!latest" class="rounded bg-blue-50 p-3">{{ quoteLabels[quote.status] }}. Uložte si túto adresu stránky — obsahuje Váš prístup k dopytu. Keď ponuku nacenime, pošleme Vám e-mail a ponuku nájdete tu.</p>
<div class="grid gap-1 text-sm sm:grid-cols-2"><p><strong>{{ quote.customer.company }}</strong><br>{{ quote.customer.name }} · {{ quote.customer.email }}</p><p>{{ quote.customer.street }}<br>{{ quote.customer.postcode }} {{ quote.customer.city }}<span v-if="quote.customer.ico"><br>IČO {{ quote.customer.ico }}</span></p></div>
<details v-if="quote.brief || quote.attachments.length"><summary class="cursor-pointer font-semibold">Vaše zadanie</summary><p class="whitespace-pre-wrap">{{ quote.brief }}</p>
<ul><li v-for="file in quote.attachments" :key="file.id"><button @click="run(()=>downloadSales('/public-quotes/'+quote.uuid+'/files/'+file.id,file.name,token))" class="underline">{{ file.name }}</button></li></ul></details>

<div v-if="latest" class="space-y-3 rounded border p-4">
<h2 class="font-semibold">Ponuka{{ latest.version > 1 ? ' – verzia ' + latest.version : '' }}, platná do {{ date(latest.valid_until) }}</h2>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="py-1">Položka</th><th class="py-1 text-right">Množstvo</th><th class="py-1 text-right">Cena/j. bez DPH</th><th class="py-1 text-right">DPH</th><th class="py-1 text-right">Spolu bez DPH</th></tr></thead>
<tbody><tr v-for="(item, i) in latest.items" :key="i" class="border-b align-top"><td class="py-1">{{ item.snapshot.name }}<span v-if="item.snapshot.variant_name"> — {{ item.snapshot.variant_name }}</span>
<span v-if="item.description" class="block text-xs text-gray-600 whitespace-pre-wrap">{{ item.description }}</span><span v-if="item.discount_percent > 0" class="block text-xs text-green-700">zľava {{ pct(item.discount_percent) }} z {{ money(item.price_net) }}</span></td>
<td class="py-1 text-right whitespace-nowrap">{{ item.quantity }} {{ item.snapshot.unit_value }}</td>
<td class="py-1 text-right whitespace-nowrap">{{ item.unit_net != null ? money(item.unit_net) : money(item.price) + ' s DPH' }}</td><td class="py-1 text-right">{{ item.snapshot.vat ?? '' }} %</td>
<td class="py-1 text-right whitespace-nowrap">{{ item.line_net != null ? money(item.line_net) : '' }}</td></tr></tbody></table></div>
<dl class="ml-auto grid max-w-sm grid-cols-2 gap-1 text-sm">
<template v-if="latest.totals?.discount_net > 0"><dt>Zľava {{ pct(latest.totals.discount_percent) }}</dt><dd class="text-right text-green-700">−{{ money(latest.totals.discount_net) }}</dd></template>
<template v-if="latest.shipping"><dt>Doprava ({{ latest.shipping.name }})</dt><dd class="text-right">{{ money(latest.shipping.price_net ?? latest.shipping.price) }}</dd></template>
<template v-if="latest.totals"><dt>Spolu bez DPH</dt><dd class="text-right">{{ money(totals.net) }}</dd><dt>DPH</dt><dd class="text-right">{{ money(totals.vat) }}</dd></template>
<dt class="font-semibold">Spolu s DPH</dt><dd class="text-right font-semibold">{{ money(totals.gross) }}</dd></dl>
<p v-if="latest.terms" class="whitespace-pre-wrap text-sm">{{ latest.terms }}</p></div>

<details v-if="quote.versions.length > 1"><summary>Predchádzajúce verzie</summary><div v-for="v in quote.versions.slice(1)" :key="v.id" class="border p-3 text-sm"><p>Verzia {{ v.version }} (nahradená)</p><p v-for="(i, n) in v.items" :key="n">{{ i.snapshot.name }}{{ i.snapshot.variant_name ? ' / ' + i.snapshot.variant_name : '' }}: {{ i.quantity }} × {{ money(i.unit_net ?? i.price) }}</p></div></details>
<router-link v-if="quote.order_uuid" :to="'/objednavka/' + quote.order_uuid" class="text-blue-700 underline">Zobraziť vytvorenú objednávku</router-link>
<p v-if="quote.is_expired" class="text-red-700">Platnosť ponuky uplynula. Požiadajte nás o novú verziu.</p>
<p v-if="quote.status === 'withdrawn'" class="text-red-700">Ponuka bola stiahnutá.</p>
<form v-if="quote.status === 'offered' && latest && !quote.is_expired" @submit.prevent="accept" class="space-y-3 border p-4"><label class="block">Vaše meno<input v-model="name" required maxlength="150" class="block rounded border p-2" /></label>
<label class="flex gap-2"><input v-model="confirm" required type="checkbox" />Súhlasím s ponukou, cenou a podmienkami. Prijatím vytvorím záväznú objednávku.</label>
<button :disabled="busy" class="rounded bg-blue-700 p-3 text-white">Prijať ponuku a objednať</button></form>
<p class="text-sm text-gray-600">Máte otázku alebo chcete ponuku upraviť? Odpovedzte na e-mail s ponukou alebo nám zavolajte.</p>
</div>
</section></template></BaseLayout></template>
