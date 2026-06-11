<script setup>
import { onMounted } from 'vue';
import BaseLayout from '../layout/BaseLayout.vue';
import PageHeader from '../layout/page/pageHeader.vue';
import ButtonSubmit from '../layout/page/ButtonSubmit.vue';
import PanelDropdown from '../layout/PanelDropdown.vue';
import FormInput from '../forms/FormInput.vue';
import usePaymentMethods from '../../store/StorePaymentMethods';
import { formatDecimal } from '../../models/functions';

const {
    state, getPaymentMethods, getTrashedMethods, getPaymentTypes,
    fetchPaymentMethods, savePaymentMethod, editPaymentMethod,
    destroyPaymentMethod, restorePaymentMethod, resetPaymentMethod,
} = usePaymentMethods();

onMounted(fetchPaymentMethods);

const typeLabel = (value) => getPaymentTypes.value.find(t => t.value === value)?.label ?? value;

const dropdownItems = (method) => [
    { label: 'Upraviť', onClick: () => editPaymentMethod(method) },
    { label: 'Zmazať',  onClick: () => destroyPaymentMethod(method) },
];
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Spôsoby platby' }" />

                <form class="mb-6 rounded-md border border-slate-300 bg-white p-4 shadow-sm" @submit.prevent="savePaymentMethod">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Názov</label>
                            <FormInput v-model="state.paymentMethod.name" placeholder="Napr. Bankový prevod" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Poplatok (€)</label>
                            <FormInput v-model.number="state.paymentMethod.fee" type="number" step="0.01" min="0" placeholder="0.00" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Typ platby</label>
                            <select v-model="state.paymentMethod.type" required class="form-control rounded border px-3 py-2 w-full">
                                <option v-for="t in getPaymentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Poradie</label>
                            <FormInput v-model.number="state.paymentMethod.sort_order" type="number" min="0" placeholder="99" />
                        </div>
                        <div class="flex items-center gap-2 md:col-span-2 self-end pb-1">
                            <input id="payment-active" type="checkbox" v-model="state.paymentMethod.active" class="h-4 w-4 accent-blue-600" />
                            <label for="payment-active" class="text-sm font-medium text-slate-700">Aktívny</label>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <button type="button" class="btn btn-secondary" @click="resetPaymentMethod">Nový</button>
                        <ButtonSubmit :item="{ name: 'Uložiť', spinner: true }" />
                    </div>
                </form>

                <div class="overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th">Poradie</th>
                                <th class="thead_th">Názov</th>
                                <th class="thead_th">Poplatok</th>
                                <th class="thead_th">Typ</th>
                                <th class="thead_th">Status</th>
                                <th class="thead_th">Panel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="method in getPaymentMethods" :key="method.id">
                                <td class="tbody_td text-slate-400">{{ method.sort_order ?? 99 }}</td>
                                <td class="tbody_td font-semibold text-slate-800">{{ method.name }}</td>
                                <td class="tbody_td">{{ method.fee > 0 ? `+ ${formatDecimal(method.fee)} €` : 'Zdarma' }}</td>
                                <td class="tbody_td">{{ typeLabel(method.type) }}</td>
                                <td class="tbody_td">
                                    <span :class="method.active ? 'text-green-600' : 'text-gray-400'">
                                        {{ method.active ? 'Aktívny' : 'Neaktívny' }}
                                    </span>
                                </td>
                                <td class="tbody_td"><PanelDropdown :items="dropdownItems(method)" /></td>
                            </tr>
                            <tr v-if="!getPaymentMethods.length">
                                <td colspan="6" class="tbody_td py-8 text-center text-slate-400">Žiadne spôsoby platby</td>
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
                                    <th class="thead_th text-red-400">Poplatok</th>
                                    <th class="thead_th text-red-400">Akcia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-red-100">
                                <tr v-for="method in getTrashedMethods" :key="method.id">
                                    <td class="tbody_td text-slate-500 line-through">{{ method.name }}</td>
                                    <td class="tbody_td text-slate-400">{{ method.fee > 0 ? `+ ${formatDecimal(method.fee)} €` : 'Zdarma' }}</td>
                                    <td class="tbody_td">
                                        <button type="button" @click="restorePaymentMethod(method)"
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
