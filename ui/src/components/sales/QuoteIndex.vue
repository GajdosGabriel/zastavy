<script setup>
import {ref,watch,onMounted} from 'vue';import {useRoute,useRouter} from 'vue-router';import BaseLayout from '../layout/BaseLayout.vue';import PageHeader from '../layout/page/pageHeader.vue';import axios from '../../axiosInstance';import {salesError,quoteLabels,quoteTones,money} from '../../models/sales';
const route=useRoute(),router=useRouter();
const quotes=ref(null),error=ref(''),busy=ref(false);
const filters=ref({status:route.query.status??'',search:route.query.search??'',source:route.query.source??''});
const statusOptions=[['','Všetky'],['requested','Nové dopyty'],['draft','Koncepty'],['offered','Odoslané – platné'],['expiring','Končí do 7 dní'],['expired','Po platnosti'],['accepted','Prijaté'],['withdrawn','Stiahnuté']];
const date=v=>v?new Date(v).toLocaleDateString('sk-SK'):'—';
async function load(page=1){busy.value=true;error.value='';try{
 const params=Object.fromEntries(Object.entries({...filters.value,page}).filter(([,v])=>v!==''&&v!==null));
 router.replace({query:{...params,page:page>1?page:undefined}});
 quotes.value=(await axios.get('/sales-quotes',{params})).data;}catch(e){error.value=salesError(e);}finally{busy.value=false;}}
let timer;watch(()=>filters.value.search,()=>{clearTimeout(timer);timer=setTimeout(()=>load(),350);});
onMounted(()=>load(Number(route.query.page)||1));
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12 space-y-4">
                <PageHeader :item="{ title: 'Cenové ponuky', buttonLink: { name: 'Nová ponuka', link: '/predaj/ponuky/nova', icon: 'plus' } }" />

                <form class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm" @submit.prevent="load()">
                    <label class="text-sm text-gray-700">Hľadať
                        <input v-model="filters.search" type="search" placeholder="Číslo, firma, e-mail, IČO" class="mt-1 block w-64 rounded border border-gray-300 px-3 py-1.5" />
                    </label>
                    <label class="text-sm text-gray-700">Stav
                        <select v-model="filters.status" class="mt-1 block rounded border border-gray-300 px-3 py-1.5" @change="load()">
                            <option v-for="[value,label] in statusOptions" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label class="text-sm text-gray-700">Zdroj
                        <select v-model="filters.source" class="mt-1 block rounded border border-gray-300 px-3 py-1.5" @change="load()">
                            <option value="">Všetky</option><option value="web">Dopyt z webu</option><option value="staff">Založené obsluhou</option>
                        </select>
                    </label>
                </form>

                <p v-if="error" role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ error }}</p>

                <section class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div v-if="!quotes" role="status" class="animate-pulse space-y-3 px-5 py-5">
                        <span class="sr-only">Načítavam ponuky…</span>
                        <div v-for="n in 4" :key="n" class="h-4 rounded bg-gray-100" aria-hidden="true"></div>
                    </div>
                    <p v-else-if="!quotes.data.length" class="px-5 py-8 text-center text-sm text-gray-600">Žiadne ponuky nezodpovedajú filtru.</p>
                    <table v-else class="min-w-full text-sm" :class="{ 'opacity-60': busy }">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                            <tr><th class="px-4 py-2">Číslo</th><th class="px-4 py-2">Zákazník</th><th class="px-4 py-2">Stav</th><th class="px-4 py-2 text-right">Spolu bez DPH</th><th class="px-4 py-2 text-right">S DPH</th><th class="px-4 py-2">Platná do</th><th class="px-4 py-2">Vytvorená</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="q in quotes.data" :key="q.id" class="hover:bg-gray-50">
                                <td class="px-4 py-2 whitespace-nowrap"><router-link :to="'/predaj/ponuky/' + q.id" class="font-semibold text-blue-700 hover:underline">{{ q.number ?? '#' + q.id }}</router-link></td>
                                <td class="px-4 py-2">
                                    <span class="font-medium text-gray-900">{{ q.customer.company || q.customer.name }}</span>
                                    <span v-if="q.title" class="block text-xs text-gray-600">{{ q.title }}</span>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="q.is_expired ? 'bg-red-100 text-red-700' : quoteTones[q.status]">{{ q.is_expired ? 'Po platnosti' : quoteLabels[q.status] }}</span>
                                    <span v-if="q.has_draft && q.status !== 'draft'" class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">rozpracovaná</span>
                                    <span v-if="q.status === 'offered' && q.viewed_at" class="ml-1 text-xs text-green-700" :title="'Zobrazená ' + new Date(q.viewed_at).toLocaleString('sk-SK')">zobrazená</span>
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">{{ q.total_net != null ? money(q.total_net) : '—' }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap font-medium">{{ q.total_gross != null ? money(q.total_gross) : '—' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap" :class="{ 'text-red-700': q.is_expired }">{{ date(q.valid_until) }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ date(q.created_at) }} <span class="text-xs">{{ q.source === 'web' ? '· web' : '' }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="quotes?.last_page > 1" class="flex items-center justify-center gap-3 border-t border-gray-100 px-5 py-3 text-sm">
                        <button type="button" class="rounded border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50" :disabled="busy || quotes.current_page === 1" @click="load(quotes.current_page - 1)">Predchádzajúca</button>
                        <span class="text-gray-600">{{ quotes.current_page }} / {{ quotes.last_page }}</span>
                        <button type="button" class="rounded border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50" :disabled="busy || quotes.current_page === quotes.last_page" @click="load(quotes.current_page + 1)">Ďalšia</button>
                    </div>
                </section>
            </div>
        </template>
    </BaseLayout>
</template>
