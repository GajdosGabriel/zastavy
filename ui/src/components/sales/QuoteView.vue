<script setup>
import {ref,computed,onMounted} from 'vue';import {useRoute,useRouter} from 'vue-router';import BaseLayout from '../layout/BaseLayout.vue';import axios from '../../axiosInstance';
import {salesError,tokenHeaders,downloadSales,quoteLabels,quoteMessageLabels,money,quoteTotals,chosenItems} from '../../models/sales';
const route=useRoute(),router=useRouter(),hash=new URLSearchParams(route.hash.slice(1)),token=hash.get('token')??'';
// Tlačidlo „Prijať ponuku“ z e-mailu: objednávku potvrdí až klik na stránke, nie samotné otvorenie odkazu
// (poštové filtre odkazy v e-mailoch automaticky otvárajú).
const quick=hash.get('prijat')==='1';
const quote=ref(null),error=ref(''),info=ref(''),busy=ref(false),name=ref(''),confirm=ref(false),choices=ref({});
const message=ref({body:'',kind:'change_request'}),rejectOpen=ref(false),reason=ref('');
const latest=computed(()=>quote.value?.versions?.[0]);
const legacy=computed(()=>latest.value&&!latest.value.totals);
const hasAlternatives=computed(()=>latest.value?.items.some(i=>i.alternatives?.length));
// Súčet podľa zvolených množstiev — rovnaký výpočet ako na serveri (QuotePricing).
const totals=computed(()=>{const v=latest.value;if(!v)return null;
 if(legacy.value)return {gross:v.items.reduce((s,i)=>s+i.quantity*Number(i.price),Number(v.shipping?.price??0))};
 return {...quoteTotals(chosenItems(v.items,choices.value),v.discount_percent,v.shipping?.price_net??0),discountPercent:v.discount_percent};});
const open=computed(()=>quote.value&&!['accepted','withdrawn'].includes(quote.value.status));
const canAccept=computed(()=>quote.value?.status==='offered'&&latest.value&&!quote.value.is_expired);
const pct=v=>Number(v).toLocaleString('sk-SK',{maximumFractionDigits:2})+' %';
const date=v=>v?new Date(v).toLocaleDateString('sk-SK'):'';
const dateTime=v=>v?new Date(v).toLocaleString('sk-SK'):'';
async function run(fn,msg=''){if(busy.value)return;busy.value=true;error.value='';info.value='';try{await fn();info.value=msg;}catch(e){error.value=salesError(e);}finally{busy.value=false;}}
async function load(){quote.value=(await axios.get('/public-quotes/'+route.params.uuid,{headers:tokenHeaders(token)})).data;name.value||=quote.value.customer?.name??'';}
onMounted(()=>run(load));
const post=(path,body)=>axios.post('/public-quotes/'+quote.value.uuid+path,body,{headers:tokenHeaders(token)});
function accept(direct=false){return run(async()=>{const {data}=await post('/accept',{version:quote.value.version,name:name.value,confirm:direct||confirm.value,choices:choices.value});await router.push('/objednavka/'+data.order_uuid);});}
function send(){return run(async()=>{quote.value=(await post('/messages',{name:name.value,...message.value})).data;message.value.body='';},'Správu sme prijali, ozveme sa Vám.');}
function reject(){if(!window.confirm('Naozaj odmietnuť ponuku?'))return;return run(async()=>{quote.value=(await post('/reject',{name:name.value,reason:reason.value})).data;rejectOpen.value=false;},'Ďakujeme za odpoveď. Ponuku sme označili ako odmietnutú.');}
function pdf(){return run(()=>downloadSales('/public-quotes/'+quote.value.uuid+'/pdf','Cenova-ponuka-'+quote.value.number+'.pdf',token));}
const unitLabel=item=>item.snapshot.unit_value||'ks';
</script>
<template><BaseLayout><template #main><section class="col-span-12 mx-auto w-full max-w-4xl space-y-5 p-4">
<div class="flex flex-wrap items-center justify-between gap-2"><h1 class="text-2xl font-semibold">Cenová ponuka <span v-if="quote?.number">č. {{ quote.number }}</span></h1>
<button v-if="latest" type="button" class="rounded border border-blue-700 px-3 py-1.5 text-sm font-semibold text-blue-700 hover:bg-blue-50" :disabled="busy" @click="pdf">Stiahnuť PDF</button></div>
<p v-if="error" role="alert" class="text-red-700">{{ error }}</p>
<p v-if="info" role="status" class="rounded bg-green-50 p-3 text-green-800">{{ info }}</p>
<div v-if="quote" class="space-y-5">
<p v-if="quote.title" class="font-semibold">{{ quote.title }}</p>
<form v-if="quick && canAccept" class="space-y-3 rounded-lg border-2 border-blue-600 bg-blue-50 p-4" @submit.prevent="accept(true)">
 <h2 class="text-lg font-semibold text-blue-900">Potvrdenie objednávky z ponuky</h2>
 <p class="text-sm">Objednávate podľa ponuky č. {{ quote.number }} v sume <strong>{{ money(totals.gross) }} s DPH</strong> ({{ money(totals.net) }} bez DPH).
  <span v-if="hasAlternatives"> Pri niektorých položkách si môžete vybrať iné množstvo v tabuľke nižšie — suma sa prepočíta.</span></p>
 <label class="block max-w-sm text-sm">Objednáva<input v-model="name" required maxlength="150" class="block w-full rounded border bg-white p-2" /></label>
 <button :disabled="busy || !name.trim()" class="rounded bg-blue-700 px-5 py-3 font-semibold text-white hover:bg-blue-800 disabled:opacity-50">Potvrdiť záväznú objednávku</button>
 <p class="text-xs text-gray-600">Kliknutím súhlasíte s ponukou, cenou a podmienkami. Objednávku Vám potvrdíme e-mailom.</p>
</form>
<p v-if="!latest" class="rounded bg-blue-50 p-3">{{ quoteLabels[quote.status] }}. Uložte si túto adresu stránky — obsahuje Váš prístup k dopytu. Keď ponuku nacenime, pošleme Vám e-mail a ponuku nájdete tu.</p>
<div class="grid gap-1 text-sm sm:grid-cols-2"><p><strong>{{ quote.customer.company }}</strong><br>{{ quote.customer.name }} · {{ quote.customer.email }}</p><p>{{ quote.customer.street }}<br>{{ quote.customer.postcode }} {{ quote.customer.city }}<span v-if="quote.customer.ico"><br>IČO {{ quote.customer.ico }}</span></p></div>
<details v-if="quote.brief || quote.attachments.length"><summary class="cursor-pointer font-semibold">Vaše zadanie</summary><p class="whitespace-pre-wrap">{{ quote.brief }}</p>
<ul><li v-for="file in quote.attachments" :key="file.id"><button @click="run(()=>downloadSales('/public-quotes/'+quote.uuid+'/files/'+file.id,file.name,token))" class="underline">{{ file.name }}</button></li></ul></details>

<div v-if="latest" class="space-y-3 rounded border p-4">
<h2 class="font-semibold">Ponuka{{ latest.version > 1 ? ' – verzia ' + latest.version : '' }}, platná do {{ date(latest.valid_until) }}</h2>
<p v-if="hasAlternatives && canAccept" class="rounded bg-amber-50 p-2 text-sm text-amber-900">Pri niektorých položkách ponúkame viac množstiev. Vyberte si — súčet sa prepočíta.</p>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="py-1">Položka</th><th class="py-1 text-right">Množstvo</th><th class="py-1 text-right">Cena/j. bez DPH</th><th class="py-1 text-right">DPH</th><th class="py-1 text-right">Spolu bez DPH</th></tr></thead>
<tbody><template v-for="(item, i) in latest.items" :key="i">
<tr class="align-top" :class="item.alternatives?.length ? '' : 'border-b'"><td class="py-1">
 <label v-if="item.alternatives?.length" class="flex gap-2"><input type="radio" :name="'q' + i" :checked="choices[i] == null" :disabled="!canAccept" @change="choices = { ...choices, [i]: null }" />
  <span>{{ item.snapshot.name }}<span v-if="item.snapshot.variant_name"> — {{ item.snapshot.variant_name }}</span></span></label>
 <template v-else>{{ item.snapshot.name }}<span v-if="item.snapshot.variant_name"> — {{ item.snapshot.variant_name }}</span></template>
 <span v-if="item.description" class="block text-xs text-gray-600 whitespace-pre-wrap">{{ item.description }}</span><span v-if="item.discount_percent > 0" class="block text-xs text-green-700">zľava {{ pct(item.discount_percent) }}</span></td>
 <td class="py-1 text-right whitespace-nowrap">{{ item.quantity }} {{ unitLabel(item) }}</td>
 <td class="py-1 text-right whitespace-nowrap">{{ item.unit_net != null ? money(item.unit_net) : money(item.price) + ' s DPH' }}</td><td class="py-1 text-right">{{ item.snapshot.vat ?? '' }} %</td>
 <td class="py-1 text-right whitespace-nowrap">{{ item.line_net != null ? money(item.line_net) : '' }}</td></tr>
<tr v-for="(alt, a) in item.alternatives ?? []" :key="i + '-' + a" class="align-top text-gray-700" :class="a === item.alternatives.length - 1 ? 'border-b' : ''">
 <td class="py-1 pl-0"><label class="flex gap-2"><input type="radio" :name="'q' + i" :checked="choices[i] === a" :disabled="!canAccept" @change="choices = { ...choices, [i]: a }" /><span>alebo {{ alt.quantity }} {{ unitLabel(item) }}</span></label></td>
 <td class="py-1 text-right whitespace-nowrap">{{ alt.quantity }} {{ unitLabel(item) }}</td><td class="py-1 text-right whitespace-nowrap">{{ money(alt.unit_net) }}</td><td class="py-1 text-right">{{ item.snapshot.vat }} %</td>
 <td class="py-1 text-right whitespace-nowrap">{{ money(alt.line_net) }}</td></tr>
</template></tbody></table></div>
<dl class="ml-auto grid max-w-sm grid-cols-2 gap-1 text-sm">
<template v-if="totals.discountNet > 0"><dt>Zľava {{ pct(totals.discountPercent) }}</dt><dd class="text-right text-green-700">−{{ money(totals.discountNet) }}</dd></template>
<template v-if="latest.shipping"><dt>Doprava ({{ latest.shipping.name }})</dt><dd class="text-right">{{ money(latest.shipping.price_net ?? latest.shipping.price) }}</dd></template>
<template v-if="!legacy"><dt>Spolu bez DPH</dt><dd class="text-right">{{ money(totals.net) }}</dd><dt>DPH</dt><dd class="text-right">{{ money(totals.vat) }}</dd></template>
<dt class="font-semibold">Spolu s DPH</dt><dd class="text-right font-semibold">{{ money(totals.gross) }}</dd></dl>
<p v-if="latest.terms" class="whitespace-pre-wrap text-sm">{{ latest.terms }}</p></div>

<details v-if="quote.versions.length > 1"><summary>Predchádzajúce verzie</summary><div v-for="v in quote.versions.slice(1)" :key="v.id" class="border p-3 text-sm"><p>Verzia {{ v.version }} (nahradená)</p><p v-for="(i, n) in v.items" :key="n">{{ i.snapshot.name }}{{ i.snapshot.variant_name ? ' / ' + i.snapshot.variant_name : '' }}: {{ i.quantity }} × {{ money(i.unit_net ?? i.price) }}</p></div></details>
<router-link v-if="quote.order_uuid" :to="'/objednavka/' + quote.order_uuid" class="text-blue-700 underline">Zobraziť vytvorenú objednávku</router-link>
<p v-if="quote.is_expired" class="text-red-700">Platnosť ponuky uplynula. Napíšte nám nižšie a pošleme Vám novú verziu.</p>
<p v-if="quote.status === 'withdrawn'" class="text-red-700">Ponuka bola stiahnutá.</p>
<p v-if="quote.status === 'rejected'" class="rounded bg-orange-50 p-3 text-orange-900">Ponuku ste odmietli. Ak si to rozmyslíte alebo chcete inú variantu, napíšte nám.</p>

<label v-if="open && latest" class="block max-w-sm text-sm">Vaše meno<input v-model="name" required maxlength="150" class="block w-full rounded border p-2" /></label>
<form v-if="canAccept" @submit.prevent="accept()" class="space-y-3 rounded border border-blue-200 bg-blue-50/40 p-4"><h2 class="font-semibold">Prijať ponuku</h2>
<label class="flex gap-2"><input v-model="confirm" required type="checkbox" />Súhlasím s ponukou, cenou a podmienkami. Prijatím vytvorím záväznú objednávku na {{ money(totals.gross) }} s DPH.</label>
<button :disabled="busy || !name.trim()" class="rounded bg-blue-700 p-3 text-white disabled:opacity-50">Prijať ponuku a objednať</button></form>

<section v-if="quote.messages?.length || (open && latest)" class="space-y-3 rounded border p-4"><h2 class="font-semibold">Otázka alebo úprava ponuky</h2>
<div v-for="m in quote.messages" :key="m.id" class="rounded px-3 py-2 text-sm" :class="m.direction === 'staff' ? 'bg-blue-50' : 'bg-gray-50'">
 <p class="text-xs text-gray-600"><strong>{{ m.direction === 'staff' ? 'Zástavy-vlajky.sk' : m.author }}</strong> · {{ dateTime(m.created_at) }}<span v-if="m.kind !== 'message'"> · {{ quoteMessageLabels[m.kind] }}</span></p>
 <p class="whitespace-pre-wrap">{{ m.body }}</p></div>
<form v-if="open && latest" class="space-y-2" @submit.prevent="send">
 <div class="flex flex-wrap gap-4 text-sm"><label class="flex gap-1"><input v-model="message.kind" type="radio" value="change_request" />Chcem upraviť ponuku</label><label class="flex gap-1"><input v-model="message.kind" type="radio" value="message" />Mám otázku</label></div>
 <textarea v-model="message.body" required maxlength="5000" rows="3" :placeholder="message.kind === 'change_request' ? 'Napr. iné množstvo, rozmer, termín dodania…' : 'Vaša otázka'" class="block w-full rounded border p-2 text-sm" />
 <button :disabled="busy || !name.trim() || !message.body.trim()" class="rounded bg-gray-800 px-4 py-2 text-sm text-white disabled:opacity-50">Odoslať</button>
 <p v-if="!name.trim()" class="text-xs text-gray-600">Vyplňte vyššie Vaše meno.</p></form></section>

<div v-if="quote.status === 'offered' && latest" class="text-sm">
 <button v-if="!rejectOpen" type="button" class="text-gray-600 underline" @click="rejectOpen = true">Ponuku nechcem – odmietnuť</button>
 <form v-else class="space-y-2 rounded border p-3" @submit.prevent="reject"><label class="block">Dôvod (nepovinné, pomôže nám zlepšiť ponuky)<textarea v-model="reason" maxlength="5000" rows="2" class="block w-full rounded border p-2" /></label>
  <button :disabled="busy || !name.trim()" class="rounded border border-red-700 px-3 py-1.5 text-red-700 disabled:opacity-50">Odmietnuť ponuku</button>
  <button type="button" class="ml-2 text-gray-600 underline" @click="rejectOpen = false">Zrušiť</button></form></div>
</div>
</section></template></BaseLayout></template>
