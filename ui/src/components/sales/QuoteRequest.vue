<script setup>
import {ref} from 'vue';import {useRouter} from 'vue-router';import BaseLayout from '../layout/BaseLayout.vue';import axios from '../../axiosInstance';import useCheckouts from '../../store/StoreCheckouts';import {salesError} from '../../models/sales';
const router=useRouter(),cart=useCheckouts();cart.getlocalStorage();
const customer=ref({company:'',name:'',email:'',phone:'',street:'',postcode:'',city:'',ico:'',dic:'',ic_dic:''});const brief=ref(''),files=ref([]),busy=ref(false),error=ref('');
const fields={company:'Firma / meno zákazníka',name:'Kontaktná osoba',email:'E-mail',phone:'Telefón',street:'Ulica a číslo',postcode:'PSČ',city:'Mesto',ico:'IČO (voliteľné)',dic:'DIČ (voliteľné)',ic_dic:'IČ DPH (voliteľné)'};
async function submit(){if(busy.value)return;busy.value=true;error.value='';try{
 const data=new FormData();for(const [k,v] of Object.entries(customer.value))data.append('customer['+k+']',v);data.append('brief',brief.value);
 cart.getCarts.forEach((item,i)=>{if(item.variant_id){data.append('items['+i+'][variant_id]',item.variant_id);data.append('items['+i+'][quantity]',item.input_order);}});
 files.value.forEach((file,i)=>data.append('attachments['+i+']',file));const response=await axios.post('/quote-requests',data);
 await router.push('/ponuka/'+response.data.uuid+'#token='+response.data.token);
 }catch(e){error.value=salesError(e);}finally{busy.value=false;}}
</script>
<template><BaseLayout><template #main><section class="col-span-12 max-w-3xl w-full mx-auto p-4"><h1 class="text-2xl font-semibold">Cenová ponuka</h1><p class="my-3">Popíšte rozmery, množstvo, materiál a požadovaný termín. Položky z košíka priložíme k dopytu. Odoslaním zatiaľ nevzniká objednávka.</p>
<form @submit.prevent="submit" class="space-y-4"><div class="grid sm:grid-cols-2 gap-3"><label v-for="(label,key) in fields" :key="key" class="block">{{label}}<input v-model="customer[key]" :type="key==='email'?'email':'text'" :required="!['ico','dic','ic_dic'].includes(key)" class="block w-full border rounded p-2"/></label></div>
<label class="block">Zadanie<textarea v-model="brief" required maxlength="10000" rows="6" class="block w-full border rounded p-2"/></label>
<p v-if="cart.getCarts.length">Z košíka: {{cart.getCarts.map(i=>i.name+' × '+i.input_order).join(', ')}}</p>
<label class="block">Podklady (najviac 5 súborov, každý do 10 MB)<input type="file" multiple @change="files=Array.from($event.target.files)" class="block" /></label>
<p v-if="error" role="alert" class="text-red-700">{{error}}</p><button :disabled="busy" class="rounded bg-blue-700 px-4 py-2 text-white disabled:opacity-40">{{busy?'Odosielam…':'Odoslať dopyt'}}</button></form></section></template></BaseLayout></template>
