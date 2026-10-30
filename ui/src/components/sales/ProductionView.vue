<script setup>
import {ref,computed,onMounted} from 'vue';import {useRoute} from 'vue-router';import BaseLayout from '../layout/BaseLayout.vue';import OrderConversation from './OrderConversation.vue';import MessageTemplatePicker from './MessageTemplatePicker.vue';import axios from '../../axiosInstance';import {salesError,tokenHeaders,messagesUrl,downloadSales,productionLabels} from '../../models/sales';
const route=useRoute(),staff=computed(()=>!!route.params.orderId),token=new URLSearchParams(route.hash.slice(1)).get('token')??'';
const detail=ref(null),form=ref({status:'awaiting_artwork',production_due_at:'',delivery_due_at:'',materials:'',note:''}),file=ref(null),note=ref(''),name=ref(''),comment=ref(''),confirm=ref(false),error=ref(''),busy=ref(false),share=ref(''),uuid=ref(route.params.uuid),artText=ref('');
const ART_TEXT='Dobrý deň, posielame Vám grafický návrh na schválenie.';
async function run(fn){if(busy.value)return;busy.value=true;error.value='';try{await fn();}catch(e){error.value=salesError(e);}finally{busy.value=false;}}
// Odpovede API po každej zmene vracajú celý detail vrátane konverzácie.
function apply(data){detail.value=data;if(data.production)form.value={...data.production};uuid.value=data.uuid??route.params.uuid;}
async function load(){apply((await axios.get(staff.value?'/orders/'+route.params.orderId+'/production':'/public-artworks/'+route.params.uuid,{headers:tokenHeaders(token)})).data);artText.value=detail.value.versions[0]?.note||ART_TEXT;}
onMounted(()=>run(load));
function save(){return run(async()=>{await axios.put('/orders/'+route.params.orderId+'/production',form.value);await load();});}
function upload(){return run(async()=>{const data=new FormData();data.append('file',file.value);data.append('note',note.value);const result=(await axios.post('/orders/'+route.params.orderId+'/artworks',data)).data;share.value=messagesUrl(result);await load();});}
function renew(){return run(async()=>{share.value=messagesUrl((await axios.post('/orders/'+route.params.orderId+'/artworks/share')).data);});}
function sendArtwork(){return run(async()=>{apply((await axios.post('/orders/'+route.params.orderId+'/messages',{body:artText.value,artwork_version:detail.value.versions[0].version})).data);});}
function decision(action){return run(async()=>{await axios.post('/public-artworks/'+route.params.uuid+'/decision',{action,version:detail.value.versions[0].version,name:name.value,comment:comment.value,...(action==='approve'?{confirm:confirm.value}:{})},{headers:tokenHeaders(token)});await load();comment.value='';});}
const statuses={pending:'Čaká na schválenie',changes_requested:'Pripomienky zákazníka',approved:'Schválené'};
const statusTones={pending:'bg-amber-100 text-amber-800',changes_requested:'bg-orange-100 text-orange-800',approved:'bg-green-100 text-green-800'};
const productionTones={awaiting_artwork:'bg-amber-100 text-amber-800',ready:'bg-blue-100 text-blue-800',in_progress:'bg-indigo-100 text-indigo-800',completed:'bg-green-100 text-green-800'};
// Kedy bola verzia naposledy odoslaná zákazníkovi e-mailom.
const sentAt=v=>detail.value.messages.filter(m=>m.kind==='artwork_sent'&&m.version===v.version).at(-1)?.created_at;
const date=d=>d?new Date(d).toLocaleDateString('sk-SK'):'neurčené';
const input='w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
const label='block text-sm font-semibold text-gray-700';
const card='overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm';
const cardHead='border-b border-gray-100 bg-gray-50 px-5 py-3';
const primary='rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:bg-gray-400';
const success='rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800 disabled:bg-gray-400';
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">

                <!-- Hlavička -->
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h1 class="page-heading mb-0">{{ staff ? 'Komunikácia so zákazníkom' : 'Správy k objednávke' }} <span v-if="detail?.serial_number" class="ml-2 font-normal text-gray-500">{{ detail.serial_number }}</span></h1>
                    <div v-if="staff" class="flex flex-wrap gap-2 text-sm">
                        <router-link :to="'/objednavky/'+route.params.orderId+'/show'" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50">Detail objednávky</router-link>
                        <router-link to="/predaj" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50">Prehľad výroby</router-link>
                    </div>
                </div>

                <div v-if="detail?.production && (staff || detail.versions.length)" class="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="productionTones[detail.production.status]">{{ productionLabels[detail.production.status] }}</span>
                    <span>výroba do <strong class="text-gray-800">{{ date(detail.production.production_due_at) }}</strong></span>
                    <span>· dodanie do <strong class="text-gray-800">{{ date(detail.production.delivery_due_at) }}</strong></span>
                    <span v-if="detail.versions.length">· grafika v{{ detail.versions[0].version }}:
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusTones[detail.versions[0].status]">{{ statuses[detail.versions[0].status] }}</span>
                    </span>
                </div>

                <p v-if="error" role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ error }}</p>

                <div v-if="detail" class="grid gap-6" :class="staff || detail.versions.length ? 'lg:grid-cols-5' : ''">

                    <!-- Konverzácia -->
                    <div class="lg:col-span-3">
                        <OrderConversation :messages="detail.messages" :staff="staff" :endpoint="staff?'/orders/'+route.params.orderId+'/messages':'/public-artworks/'+route.params.uuid+'/messages'" :uuid="uuid" :token="token" @updated="apply"/>
                    </div>

                    <!-- Grafika a plán -->
                    <div v-if="staff || detail.versions.length" class="space-y-6 lg:col-span-2">

                        <!-- Schválenie zákazníkom -->
                        <form v-if="!staff && detail.versions[0].status!=='approved'" :class="card" class="border-green-300" @submit.prevent="decision('approve')">
                            <div class="border-b border-green-100 bg-green-50 px-5 py-3"><h2 class="text-base font-semibold text-green-900">Vyjadrenie k verzii {{ detail.versions[0].version }}</h2></div>
                            <div class="space-y-3 px-5 py-4">
                                <label :class="label">Vaše meno<input v-model="name" required maxlength="150" class="mt-1" :class="input"/></label>
                                <label :class="label">Pripomienky<textarea v-model="comment" maxlength="5000" rows="3" class="mt-1" :class="input"/></label>
                                <button type="button" :disabled="busy || !name || !comment" @click="decision('comment')" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40">Odoslať pripomienky</button>
                                <label class="flex gap-2 border-t pt-3 text-sm"><input v-model="confirm" required type="checkbox" class="mt-0.5"/>Skontroloval/a som aktuálnu verziu a schvaľujem ju na výrobu.</label>
                                <button :disabled="busy" :class="success" class="w-full py-3">Schváliť verziu {{ detail.versions[0].version }}</button>
                                <p class="text-xs text-gray-500">Schválenie sa zaznamená s vaším menom a časom cez tento súkromný odkaz.</p>
                            </div>
                        </form>

                        <!-- Verzie grafiky -->
                        <div v-if="detail.versions.length || staff" :class="card">
                            <div :class="cardHead"><h2 class="text-base font-semibold text-gray-800">Grafické návrhy</h2></div>
                            <p v-if="!detail.versions.length" class="px-5 py-4 text-sm text-gray-600">Zatiaľ nebol nahratý žiadny návrh.</p>
                            <ul class="divide-y divide-gray-100">
                                <li v-for="(v,index) in detail.versions" :key="v.id" class="space-y-2 px-5 py-4" :class="index>0 ? 'bg-gray-50/50 opacity-75' : ''">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="font-semibold text-gray-800">Verzia {{ v.version }} <span class="text-sm font-normal text-gray-500">{{ index===0 ? '— aktuálna' : '— nahradená' }}</span></h3>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusTones[v.status]">{{ statuses[v.status] }}</span>
                                    </div>
                                    <button :disabled="busy" @click="run(()=>downloadSales('/public-artworks/'+uuid+'/files/'+v.version,v.name,token))" class="break-all text-left text-sm font-semibold text-blue-700 hover:underline">⤓ {{ v.name }}</button>
                                    <p v-if="v.note" class="whitespace-pre-wrap text-sm text-gray-700">{{ v.note }}</p>
                                    <p v-if="v.approved_at" class="text-sm text-green-800">Schválil/a {{ v.approved_by }} · {{ new Date(v.approved_at).toLocaleString('sk-SK') }}</p>
                                    <template v-if="staff">
                                        <p v-if="sentAt(v)" class="text-xs text-green-800">Odoslané zákazníkovi {{ new Date(sentAt(v)).toLocaleString('sk-SK') }}</p>
                                        <p v-else-if="index===0" class="text-xs font-semibold text-amber-700">Zatiaľ neodoslané zákazníkovi.</p>
                                        <form v-if="index===0 && v.status!=='approved'" @submit.prevent="sendArtwork" class="space-y-2 pt-1">
                                            <MessageTemplatePicker v-model="artText"/>
                                            <label :class="label">Sprievodný text e-mailu<textarea v-model="artText" required maxlength="5000" rows="3" class="mt-1" :class="input"/></label>
                                            <button :disabled="busy || !artText.trim()" :class="success">{{ sentAt(v) ? 'Odoslať návrh znova' : 'Odoslať návrh zákazníkovi' }}</button>
                                        </form>
                                    </template>
                                </li>
                            </ul>
                            <div v-if="staff && detail.versions.length" class="space-y-2 border-t border-gray-100 px-5 py-3 text-sm">
                                <button :disabled="busy" @click="renew" class="text-left text-blue-700 hover:underline">Vytvoriť nový odkaz pre zákazníka</button>
                                <p class="text-xs text-gray-500">Zneplatní odkazy v doterajších e-mailoch.</p>
                                <label v-if="share" :class="label">Odkaz pre zákazníka<input :value="share" readonly class="mt-1" :class="input" @focus="$event.target.select()"/></label>
                            </div>
                        </div>

                        <template v-if="staff">
                            <!-- Nová verzia grafiky -->
                            <form :class="card" @submit.prevent="upload">
                                <div :class="cardHead"><h2 class="text-base font-semibold text-gray-800">Nahrať novú verziu grafiky</h2></div>
                                <div class="space-y-3 px-5 py-4">
                                    <p class="text-xs text-gray-600">PDF, PNG, JPG alebo WebP do 10 MB. Nová verzia vyžaduje nové schválenie. Po začatí výroby už návrh nemožno nahradiť. Zákazníkovi odíde až po kliknutí na „Odoslať návrh zákazníkovi“.</p>
                                    <input type="file" accept=".pdf,.png,.jpg,.jpeg,.webp" required class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:font-semibold file:text-blue-700 hover:file:bg-blue-100" @change="file=$event.target.files[0]"/>
                                    <label :class="label">Poznámka pre zákazníka<textarea v-model="note" rows="2" class="mt-1" :class="input"/></label>
                                    <button :disabled="busy || !file" :class="primary">Nahrať návrh</button>
                                </div>
                            </form>

                            <!-- Plán zákazky -->
                            <form :class="card" @submit.prevent="save">
                                <div :class="cardHead"><h2 class="text-base font-semibold text-gray-800">Plán výroby</h2></div>
                                <div class="space-y-3 px-5 py-4">
                                    <label :class="label">Stav<select v-model="form.status" class="mt-1" :class="input"><option v-for="(text,key) in productionLabels" :key="key" :value="key">{{ text }}</option></select></label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <label :class="label">Výroba do<input v-model="form.production_due_at" type="date" class="mt-1" :class="input"/></label>
                                        <label :class="label">Dodanie do<input v-model="form.delivery_due_at" type="date" class="mt-1" :class="input"/></label>
                                    </div>
                                    <label :class="label">Materiály<input v-model="form.materials" maxlength="2000" class="mt-1" :class="input"/></label>
                                    <label :class="label">Interná poznámka<textarea v-model="form.note" maxlength="5000" rows="3" class="mt-1" :class="input"/></label>
                                    <button :disabled="busy" :class="primary">Uložiť plán</button>
                                </div>
                            </form>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>
