<script setup>
import { onMounted, ref } from 'vue';
import axiosInstance from '../../axiosInstance';
import { PAGE_STOCK } from '../../constants';
import useErrors from '../../store/StoreErrors';
import { formatPrice } from '../../models/functions';
import BaseLayout from '../layout/BaseLayout.vue';
import PageHeader from '../layout/page/pageHeader.vue';

const search = ref('');
const status = ref('');
const rows = ref([]);
const meta = ref(null);
const loading = ref(false);
const failed = ref(false);
const load = async (page = 1) => {
    loading.value = true;
    failed.value = false;
    try {
        const { data } = await axiosInstance.get(PAGE_STOCK.URL + '/receipts', { params: { search: search.value, status: status.value, page } });
        rows.value = data.data;
        meta.value = data.meta;
    } catch (error) { failed.value = true; useErrors().setErrors(error); }
    finally { loading.value = false; }
};
onMounted(() => load());
const date = (value) => value.split('-').reverse().join('.');
</script>

<template>
    <BaseLayout><template #main><div class="page-body col-span-12">
        <PageHeader :item="{ title: 'Evidencia príjemiek', buttonLink: { name: 'Späť na sklad', link: 'stocks.index', icon: 'arrow-left' } }" />
        <div class="mb-5 flex justify-end"><router-link :to="{ name: 'stocks.create' }" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white">+ Nová príjemka</router-link></div>
        <form class="mb-5 flex flex-wrap gap-3" @submit.prevent="load()"><label class="flex-1"><span class="sr-only">Hľadať príjemku</span><input v-model="search" maxlength="255" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Číslo príjemky, dodávateľ, dodací list / faktúra…" /></label><label><span class="sr-only">Stav príjemky</span><select v-model="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="">Všetky stavy</option><option value="active">Naskladnené</option><option value="cancelled">Stornované</option></select></label><button :disabled="loading" type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Hľadať</button></form>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-left text-xs text-gray-500"><tr><th class="p-4">Príjemka</th><th class="p-4">Dátum príjmu</th><th class="p-4">Dodávateľ / doklad</th><th class="p-4">Sklad</th><th class="p-4 text-right">Položky</th><th class="p-4 text-right">Celkom s DPH</th><th class="p-4">Stav</th></tr></thead><tbody><tr v-if="loading"><td colspan="7" class="p-8 text-center text-gray-500">Načítavam príjemky…</td></tr><tr v-else-if="failed"><td colspan="7" class="p-8 text-center text-red-600">Príjemky sa nepodarilo načítať.</td></tr><tr v-else-if="!rows.length"><td colspan="7" class="p-8 text-center text-gray-500">Žiadne príjemky pre zvolené filtre.</td></tr><template v-else><tr v-for="row in rows" :key="row.id" class="border-t hover:bg-gray-50"><td class="p-4"><router-link :to="{ name: 'stocks.receipts.show', params: { receiptId: row.id } }" class="font-semibold text-blue-700 hover:underline">{{ row.number }}</router-link></td><td class="p-4">{{ date(row.received_at) }}</td><td class="p-4"><div class="font-semibold">{{ row.supplier }}</div><div class="text-xs text-gray-500">{{ row.document_number }}</div></td><td class="p-4">{{ row.warehouse }}</td><td class="p-4 text-right">{{ row.items.length }}</td><td class="p-4 text-right font-semibold">{{ formatPrice(row.total) }} €</td><td class="p-4"><span class="rounded-full px-2 py-1 text-xs font-semibold" :class="row.cancelled_at ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'">{{ row.cancelled_at ? 'Stornovaná' : 'Naskladnená' }}</span></td></tr></template></tbody></table></div><div v-if="meta" class="flex items-center justify-between border-t px-4 py-3 text-sm text-gray-500"><button :disabled="loading || meta.current_page <= 1" class="disabled:opacity-30" @click="load(meta.current_page - 1)">← Predchádzajúca</button><span>{{ meta.current_page }} / {{ meta.last_page }} · {{ meta.total }} dokladov</span><button :disabled="loading || meta.current_page >= meta.last_page" class="disabled:opacity-30" @click="load(meta.current_page + 1)">Nasledujúca →</button></div></div>
    </div></template></BaseLayout>
</template>
