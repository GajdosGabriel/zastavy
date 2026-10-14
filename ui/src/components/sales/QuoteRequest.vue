<script setup>
import {ref} from 'vue';import {useRouter} from 'vue-router';import BaseLayout from '../layout/BaseLayout.vue';import axios from '../../axiosInstance';import useCheckouts from '../../store/StoreCheckouts';import {salesError} from '../../models/sales';
const router=useRouter(),cart=useCheckouts();cart.getlocalStorage();
const customer=ref({company:'',name:'',email:'',phone:'',street:'',postcode:'',city:'',ico:'',dic:'',ic_dic:''});const brief=ref(''),files=ref([]),busy=ref(false),error=ref('');
const MAX_FILES=5,MAX_SIZE=10*1024*1024,ACCEPT='.pdf,.jpg,.jpeg,.png,.gif,.webp,.svg,.ai,.eps,.cdr,.psd,.zip,.rar,.doc,.docx,.xls,.xlsx',fileError=ref('');
const meta={company:{ac:'organization',max:191},name:{ac:'name',max:191},email:{ac:'email',max:191,type:'email'},phone:{ac:'tel',max:30,type:'tel'},street:{ac:'street-address',max:191},postcode:{ac:'postal-code',max:10},city:{ac:'address-level2',max:191},ico:{ac:'off',max:20},dic:{ac:'off',max:20},ic_dic:{ac:'off',max:20}};
function pick(e){const list=Array.from(e.target.files);fileError.value='';
 if(list.length>MAX_FILES)fileError.value='Naraz je možné priložiť najviac '+MAX_FILES+' súborov.';
 else if(list.some(f=>f.size>MAX_SIZE))fileError.value='Každý súbor môže mať najviac 10 MB.';
 if(fileError.value){e.target.value='';files.value=[];return;}files.value=list;}
const fields={company:'Firma / meno zákazníka',name:'Kontaktná osoba',email:'E-mail',phone:'Telefón',street:'Ulica a číslo',postcode:'PSČ',city:'Mesto',ico:'IČO (voliteľné)',dic:'DIČ (voliteľné)',ic_dic:'IČ DPH (voliteľné)'};
const icoQuery=ref(''),icoBusy=ref(false),icoMsg=ref(''),icoOk=ref(false);
// Doplní len firemné a adresné údaje — kontakt z databázy verejnému formuláru nedávame.
async function findByIco(){const ico=icoQuery.value.replace(/\D/g,'');icoMsg.value='';
 if(!ico||ico.length>8){icoOk.value=false;icoMsg.value='Zadajte IČO (najviac 8 číslic).';return;}
 icoBusy.value=true;try{const c=(await axios.get('/checkouts/'+ico)).data.data||{};
 for(const k of ['company','street','postcode','city','dic','ic_dic'])if(c[k])customer.value[k]=c[k];customer.value.ico=c.ico||ico;
 icoOk.value=true;icoMsg.value='Údaje firmy sme doplnili.';}catch(e){icoOk.value=false;icoMsg.value=e.response?.data?.message||'Firmu sa nepodarilo nájsť.';}finally{icoBusy.value=false;}}
async function submit(){if(busy.value||fileError.value)return;busy.value=true;error.value='';try{
 const data=new FormData();for(const [k,v] of Object.entries(customer.value))data.append('customer['+k+']',v);data.append('brief',brief.value);
 cart.getCarts.forEach((item,i)=>{if(item.variant_id){data.append('items['+i+'][variant_id]',item.variant_id);data.append('items['+i+'][quantity]',item.input_order);}});
 files.value.forEach((file,i)=>data.append('attachments['+i+']',file));const response=await axios.post('/quote-requests',data);
 await router.push('/ponuka/'+response.data.uuid+'#token='+response.data.token);
 }catch(e){error.value=salesError(e);}finally{busy.value=false;}}
</script>
<template><BaseLayout><template #main><section class="col-span-12 max-w-3xl w-full mx-auto p-4"><h1 class="text-2xl font-semibold">Cenová ponuka</h1><p class="my-3">Popíšte rozmery, množstvo, materiál a požadovaný termín. Položky z košíka priložíme k dopytu. Odoslaním zatiaľ nevzniká objednávka.</p>
<form @submit.prevent="submit" class="space-y-4">
<div class="flex flex-wrap items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm"><label for="quote-ico" class="text-slate-600">Máte IČO? Doplníme údaje firmy</label>
<div class="ml-auto flex gap-2"><input id="quote-ico" v-model="icoQuery" inputmode="numeric" maxlength="8" autocomplete="off" placeholder="IČO" class="w-28 rounded border border-slate-300 px-2 py-1" @input="icoQuery=icoQuery.replace(/\D/g,'')" @keydown.enter.prevent="findByIco"/>
<button type="button" :disabled="icoBusy||!icoQuery" class="rounded bg-slate-700 px-3 py-1 text-white disabled:opacity-40" @click="findByIco">{{icoBusy?'Hľadám…':'Vyhľadať'}}</button></div>
<p v-if="icoMsg" role="status" class="w-full text-xs" :class="icoOk?'text-green-700':'text-red-700'">{{icoMsg}}</p></div><div class="grid sm:grid-cols-2 gap-3"><label v-for="(label,key) in fields" :key="key" class="block">{{label}}<input v-model="customer[key]" :name="key" :type="meta[key].type||'text'" :autocomplete="meta[key].ac" :maxlength="meta[key].max" :required="!['ico','dic','ic_dic'].includes(key)" class="block w-full border rounded p-2"/></label></div>
<label class="block">Zadanie<textarea v-model="brief" name="brief" required maxlength="10000" rows="6" class="block w-full border rounded p-2"/></label>
<p v-if="cart.getCarts.length">Z košíka: {{cart.getCarts.map(i=>i.name+' × '+i.input_order).join(', ')}}</p>
<label class="block">Podklady (najviac 5 súborov, každý do 10 MB)<input type="file" name="attachments" multiple :accept="ACCEPT" @change="pick" class="block" /></label>
<p v-if="fileError" role="alert" class="text-red-700">{{fileError}}</p>
<p v-if="error" role="alert" class="text-red-700">{{error}}</p><button :disabled="busy" class="rounded bg-blue-700 px-4 py-2 text-white disabled:opacity-40">{{busy?'Odosielam…':'Odoslať dopyt'}}</button></form></section></template></BaseLayout></template>
