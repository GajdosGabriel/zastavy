<script setup>
import { onMounted } from 'vue';
import BaseLayout from '../layout/BaseLayout.vue';
import PageHeader from '../layout/page/pageHeader.vue';
import ButtonSubmit from '../layout/page/ButtonSubmit.vue';
import PanelDropdown from '../layout/PanelDropdown.vue';
import FormInput from '../forms/FormInput.vue';
import useShippingMethods from '../../store/StoreShippingMethods';
import { formatDecimal } from '../../models/functions';

const {
    state, getShippingMethods, getTrashedMethods,
    fetchShippingMethods, saveShippingMethod, editShippingMethod,
    destroyShippingMethod, restoreShippingMethod, resetShippingMethod,
} = useShippingMethods();

onMounted(fetchShippingMethods);

const dropdownItems = (method) => [
    { label: 'Upraviť', onClick: () => editShippingMethod(method) },
    { label: 'Zmazať',  onClick: () => destroyShippingMethod(method) },
];
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Spôsoby dopravy' }" />

                <form class="mb-6 rounded-md border border-slate-300 bg-white p-4 shadow-sm" @submit.prevent="saveShippingMethod">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Názov</label>
                            <FormInput v-model="state.shippingMethod.name" placeholder="Napr. Packeta" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Cena (€)</label>
                            <FormInput v-model.number="state.shippingMethod.price" type="number" step="0.01" min="0" placeholder="4.99" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Zdarma od (€)</label>
                            <FormInput v-model="state.shippingMethod.free_from_price" type="number" step="0.01" min="0" placeholder="50.00 (voliteľné)" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Poradie</label>
                            <FormInput v-model.number="state.shippingMethod.sort_order" type="number" min="0" placeholder="99" />
                        </div>
                        <div class="flex items-center gap-2 md:col-span-2 self-end pb-1">
                            <input id="shipping-active" type="checkbox" v-model="state.shippingMethod.active" class="h-4 w-4 accent-blue-600" />
                            <label for="shipping-active" class="text-sm font-medium text-slate-700">Aktívny</label>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <button type="button" class="btn btn-secondary" @click="resetShippingMethod">Nový</button>
                        <ButtonSubmit :item="{ name: 'Uložiť', spinner: true }" />
                    </div>
                </form>

                <div class="overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th">Poradie</th>
                                <th class="thead_th">Názov</th>
                                <th class="thead_th">Cena</th>
                                <th class="thead_th">Zdarma od</th>
                                <th class="thead_th">Status</th>
                                <th class="thead_th">Panel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="method in getShippingMethods" :key="method.id">
                                <td class="tbody_td text-slate-400">{{ method.sort_order ?? 99 }}</td>
                                <td class="tbody_td font-semibold text-slate-800">{{ method.name }}</td>
                                <td class="tbody_td">{{ formatDecimal(method.price) }} €</td>
                                <td class="tbody_td">{{ method.free_from_price ? `${formatDecimal(method.free_from_price)} €` : '—' }}</td>
                                <td class="tbody_td">
                                    <span :class="method.active ? 'text-green-600' : 'text-gray-400'">
                                        {{ method.active ? 'Aktívny' : 'Neaktívny' }}
                                    </span>
                                </td>
                                <td class="tbody_td"><PanelDropdown :items="dropdownItems(method)" /></td>
                            </tr>
                            <tr v-if="!getShippingMethods.length">
                                <td colspan="6" class="tbody_td py-8 text-center text-slate-400">Žiadne spôsoby dopravy</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="getTrashedMethods.length" class="mt-8">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Kôš</h3>
                    <div class="overflow-x-auto rounded-md border border-red-100 bg-red-50 shadow-sm">
                        <table class="min-w-full divide-y divide-red-100">
                            <thead>
                                <tr>
                                    <th class="thead_th text-red-400">Názov</th>
                                    <th class="thead_th text-red-400">Cena</th>
                                    <th class="thead_th text-red-400">Akcia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-red-100">
                                <tr v-for="method in getTrashedMethods" :key="method.id">
                                    <td class="tbody_td text-slate-500 line-through">{{ method.name }}</td>
                                    <td class="tbody_td text-slate-400">{{ formatDecimal(method.price) }} €</td>
                                    <td class="tbody_td">
                                        <button type="button" @click="restoreShippingMethod(method)"
                                            class="rounded bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 hover:bg-green-200">
                                            Obnoviť
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>
