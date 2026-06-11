<script setup>
import { onMounted } from 'vue';
import BaseLayout from '../layout/BaseLayout.vue';
import PageHeader from '../layout/page/pageHeader.vue';
import ButtonSubmit from '../layout/page/ButtonSubmit.vue';
import PanelDropdown from '../layout/PanelDropdown.vue';
import FormInput from '../forms/FormInput.vue';
import useCoupons from '../../store/StoreCoupons';
import { formatDecimal } from '../../models/functions';

const {
    state, getCoupons, getTrashedCoupons,
    fetchCoupons, saveCoupon, editCoupon,
    destroyCoupon, restoreCoupon, resetCoupon, generateCode,
} = useCoupons();

onMounted(fetchCoupons);

const dropdownItems = (coupon) => [
    { label: 'Upraviť', onClick: () => editCoupon(coupon) },
    { label: 'Zmazať',  onClick: () => destroyCoupon(coupon) },
];
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Kupóny' }" />

                <form class="mb-6 rounded-md border border-slate-300 bg-white p-4 shadow-sm" @submit.prevent="saveCoupon">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Kód kupóna</label>
                            <div class="flex gap-2">
                                <FormInput v-model="state.coupon.code" placeholder="SUMMER10" required class="uppercase flex-1" />
                                <button type="button" @click="generateCode"
                                    title="Generovať kód"
                                    class="rounded border border-slate-300 bg-slate-100 px-3 text-sm font-semibold text-slate-600 hover:bg-slate-200 whitespace-nowrap">
                                    &#x21bb; Generovať
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Typ zľavy</label>
                            <select v-model="state.coupon.type" required class="form-control rounded border px-3 py-2 w-full">
                                <option value="percent">Percentuálna (%)</option>
                                <option value="fixed">Fixná (€)</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">
                                Hodnota {{ state.coupon.type === 'percent' ? '(%)' : '(€)' }}
                            </label>
                            <FormInput v-model.number="state.coupon.value" type="number" step="0.01" min="0" placeholder="10" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Min. hodnota objednávky (€)</label>
                            <FormInput v-model="state.coupon.min_order_price" type="number" step="0.01" min="0" placeholder="Voliteľné" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Limit použití</label>
                            <FormInput v-model="state.coupon.usage_limit" type="number" min="1" placeholder="Voliteľné" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Platný od</label>
                            <input v-model="state.coupon.valid_from" type="date" class="form-control rounded border px-3 py-2 w-full" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Platný do</label>
                            <input v-model="state.coupon.valid_to" type="date" class="form-control rounded border px-3 py-2 w-full" />
                        </div>
                        <div class="flex items-center gap-2 md:col-span-2 self-end pb-1">
                            <input id="coupon-active" type="checkbox" v-model="state.coupon.active" class="h-4 w-4 accent-blue-600" />
                            <label for="coupon-active" class="text-sm font-medium text-slate-700">Aktívny</label>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <button type="button" class="btn btn-secondary" @click="resetCoupon">Nový</button>
                        <ButtonSubmit :item="{ name: 'Uložiť', spinner: true }" />
                    </div>
                </form>

                <div class="overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th">Kód</th>
                                <th class="thead_th">Zľava</th>
                                <th class="thead_th">Podmienky</th>
                                <th class="thead_th">Platnosť</th>
                                <th class="thead_th">Použití</th>
                                <th class="thead_th">Status</th>
                                <th class="thead_th">Panel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="coupon in getCoupons" :key="coupon.id">
                                <td class="tbody_td font-semibold text-slate-800 tracking-wide">{{ coupon.code }}</td>
                                <td class="tbody_td">
                                    {{ coupon.type === 'percent' ? `${coupon.value}%` : `${formatDecimal(coupon.value)} €` }}
                                </td>
                                <td class="tbody_td text-xs text-slate-500">
                                    <div v-if="coupon.min_order_price">Min: {{ formatDecimal(coupon.min_order_price) }} €</div>
                                    <div v-else>—</div>
                                </td>
                                <td class="tbody_td text-xs text-slate-500">
                                    <div v-if="coupon.valid_from || coupon.valid_to">
                                        <div v-if="coupon.valid_from">Od: {{ coupon.valid_from }}</div>
                                        <div v-if="coupon.valid_to">Do: {{ coupon.valid_to }}</div>
                                    </div>
                                    <div v-else>—</div>
                                </td>
                                <td class="tbody_td text-center">
                                    {{ coupon.used_count }}
                                    <span v-if="coupon.usage_limit" class="text-slate-400">/ {{ coupon.usage_limit }}</span>
                                </td>
                                <td class="tbody_td">
                                    <span :class="coupon.active ? 'text-green-600' : 'text-gray-400'">
                                        {{ coupon.active ? 'Aktívny' : 'Neaktívny' }}
                                    </span>
                                </td>
                                <td class="tbody_td"><PanelDropdown :items="dropdownItems(coupon)" /></td>
                            </tr>
                            <tr v-if="!getCoupons.length">
                                <td colspan="7" class="tbody_td py-8 text-center text-slate-400">Žiadne kupóny</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="getTrashedCoupons.length" class="mt-8">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Kôš</h3>
                    <div class="overflow-x-auto rounded-md border border-red-100 bg-red-50 shadow-sm">
                        <table class="min-w-full divide-y divide-red-100">
                            <thead>
                                <tr>
                                    <th class="thead_th text-red-400">Kód</th>
                                    <th class="thead_th text-red-400">Zľava</th>
                                    <th class="thead_th text-red-400">Akcia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-red-100">
                                <tr v-for="coupon in getTrashedCoupons" :key="coupon.id">
                                    <td class="tbody_td text-slate-500 line-through tracking-wide">{{ coupon.code }}</td>
                                    <td class="tbody_td text-slate-400">
                                        {{ coupon.type === 'percent' ? `${coupon.value}%` : `${formatDecimal(coupon.value)} €` }}
                                    </td>
                                    <td class="tbody_td">
                                        <button type="button" @click="restoreCoupon(coupon)"
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
