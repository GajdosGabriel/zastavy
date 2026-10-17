<script setup>
import {ref,onMounted} from 'vue';import BaseLayout from '../layout/BaseLayout.vue';import PageHeader from '../layout/page/pageHeader.vue';import axios from '../../axiosInstance';import {salesError,productionLabels,quoteLabels} from '../../models/sales';
const quotes=ref(null),production=ref(null),unread=ref([]),error=ref(''),busy=ref(false),overdue=ref(false),status=ref('');
async function load(q=1,p=1){if(busy.value)return;busy.value=true;error.value='';try{const [a,b,c]=await Promise.all([axios.get('/sales-quotes',{params:{page:q}}),axios.get('/production',{params:{page:p,overdue:overdue.value?1:0,status:status.value}}),axios.get('/order-messages/unread')]);quotes.value=a.data;production.value=b.data;unread.value=c.data;}catch(e){error.value=salesError(e);}finally{busy.value=false;}}
onMounted(()=>load());
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12 space-y-6">
                <PageHeader :item="{ title: 'Ponuky a výroba' }" />

                <p v-if="error" role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ error }}</p>

                <!-- Neprečítané správy -->
                <section v-if="unread.length" class="overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm">
                    <h2 class="border-b border-blue-100 bg-blue-50 px-5 py-3 text-sm font-semibold text-blue-900">
                        Neprečítané správy zákazníkov
                    </h2>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="u in unread" :key="u.order_id" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 text-sm">
                            <router-link :to="'/vyroba/' + u.order_id" class="font-semibold text-blue-700 hover:underline">
                                {{ u.serial_number }} — {{ u.company }}
                            </router-link>
                            <span class="rounded-full bg-red-600 px-2 text-xs font-bold text-white">{{ u.unread }}×</span>
                            <span class="text-gray-600">naposledy {{ new Date(u.last_at).toLocaleString('sk-SK') }}</span>
                        </li>
                    </ul>
                </section>

                <!-- Cenové dopyty a ponuky -->
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <h2 class="flex justify-between border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-semibold text-gray-700">Cenové dopyty a ponuky <router-link to="/predaj/ponuky" class="font-normal text-blue-700 hover:underline">Všetky ponuky →</router-link></h2>

                    <div v-if="!quotes" role="status" class="animate-pulse space-y-3 px-5 py-5">
                        <span class="sr-only">Načítavam dopyty…</span>
                        <div v-for="n in 3" :key="n" class="h-4 rounded bg-gray-100" aria-hidden="true"></div>
                    </div>
                    <p v-else-if="!quotes.data.length" class="px-5 py-8 text-center text-sm text-gray-600">Zatiaľ žiadne dopyty.</p>
                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="q in quotes.data" :key="q.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 text-sm">
                            <router-link :to="'/predaj/ponuky/' + q.id" class="font-semibold text-blue-700 hover:underline">
                                {{ q.number ?? '#' + q.id }} — {{ q.customer.company || q.customer.name }}
                            </router-link>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ quoteLabels[q.status] }}</span>
                            <span class="text-gray-600">{{ q.customer.email }}</span>
                        </li>
                    </ul>

                    <div v-if="quotes?.last_page > 1" class="flex items-center justify-center gap-3 border-t border-gray-100 px-5 py-3 text-sm">
                        <button type="button" class="rounded border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="busy || quotes.current_page === 1" @click="load(quotes.current_page - 1, production.current_page)">Predchádzajúca</button>
                        <span class="text-gray-600">{{ quotes.current_page }} / {{ quotes.last_page }}</span>
                        <button type="button" class="rounded border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="busy || quotes.current_page === quotes.last_page" @click="load(quotes.current_page + 1, production.current_page)">Ďalšia</button>
                    </div>
                </section>

                <!-- Plán výroby -->
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-5 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-700">Plán výroby</h2>
                            <p class="mt-0.5 text-xs text-gray-600">Novú zákazku otvoríte cez „Komunikácia, grafika a výroba“ v detaile objednávky.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="flex min-h-9 cursor-pointer items-center gap-2 text-sm font-semibold text-gray-700">
                                <input v-model="overdue" type="checkbox" class="h-4 w-4 rounded border-gray-300" @change="load(quotes?.current_page ?? 1, 1)" />
                                Iba omeškané
                            </label>
                            <select v-model="status" aria-label="Stav výroby" class="rounded border border-gray-300 px-3 py-1.5 text-sm" @change="load(quotes?.current_page ?? 1, 1)">
                                <option value="">Všetky stavy</option>
                                <option v-for="(label, key) in productionLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="!production" role="status" class="animate-pulse space-y-3 px-5 py-5">
                        <span class="sr-only">Načítavam plán výroby…</span>
                        <div v-for="n in 3" :key="n" class="h-4 rounded bg-gray-100" aria-hidden="true"></div>
                    </div>
                    <p v-else-if="!production.data.length" class="px-5 py-8 text-center text-sm text-gray-600">Žiadne zákazky pre tento filter.</p>
                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="p in production.data" :key="p.id" class="px-5 py-3 text-sm"
                            :class="p.overdue ? 'border-l-4 border-red-500 bg-red-50' : ''">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <router-link :to="'/vyroba/' + p.order_id" class="font-semibold text-blue-700 hover:underline">
                                    {{ p.serial_number }} — {{ p.company }}
                                </router-link>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ productionLabels[p.status] }}</span>
                                <strong v-if="p.overdue" class="text-xs font-bold uppercase text-red-700">Po termíne</strong>
                            </div>
                            <p class="mt-1 text-gray-600">
                                Výroba do {{ p.production_due_at || 'neurčené' }} · dodanie do {{ p.delivery_due_at || 'neurčené' }}
                                · materiál: {{ p.materials || 'neurčený' }}
                            </p>
                        </li>
                    </ul>

                    <div v-if="production?.last_page > 1" class="flex items-center justify-center gap-3 border-t border-gray-100 px-5 py-3 text-sm">
                        <button type="button" class="rounded border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="busy || production.current_page === 1" @click="load(quotes.current_page, production.current_page - 1)">Predchádzajúca</button>
                        <span class="text-gray-600">{{ production.current_page }} / {{ production.last_page }}</span>
                        <button type="button" class="rounded border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="busy || production.current_page === production.last_page" @click="load(quotes.current_page, production.current_page + 1)">Ďalšia</button>
                    </div>
                </section>
            </div>
        </template>
    </BaseLayout>
</template>
