<script setup>
import { onMounted, ref } from 'vue';
import BaseLayout from '../layout/BaseLayout.vue';
import PageHeader from '../layout/page/pageHeader.vue';
import ButtonSubmit from '../layout/page/ButtonSubmit.vue';
import PanelDropdown from '../layout/PanelDropdown.vue';
import FormInput from '../forms/FormInput.vue';
import useCoupons from '../../store/StoreCoupons';
import axiosInstance from '../../axiosInstance';
import { formatDecimal } from '../../models/functions';

const {
    state, getCoupons, getTrashedCoupons,
    fetchCoupons, saveCoupon, editCoupon,
    destroyCoupon, restoreCoupon, resetCoupon, generateCode,
} = useCoupons();

const settings = ref({
    delay_days:           14,
    valid_days:           30,
    discount_percent:     10,
    min_order_multiplier: 1.5,
    min_order_floor:      50,
});
const settingsSaving = ref(false);
const settingsSaved  = ref(false);

const fetchSettings = async () => {
    const { data } = await axiosInstance.get('/admin/coupon-settings');
    settings.value = data.data;
};

const saveSettings = async () => {
    settingsSaving.value = true;
    settingsSaved.value  = false;
    await axiosInstance.put('/admin/coupon-settings', settings.value);
    settingsSaving.value = false;
    settingsSaved.value  = true;
    setTimeout(() => { settingsSaved.value = false; }, 3000);
};

onMounted(() => {
    fetchCoupons();
    fetchSettings();
});

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

                <!-- Nastavenia automatického kupóna -->
                <div class="mb-6 rounded-md border border-blue-200 bg-blue-50 p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-blue-700">
                        Nastavenia automatického kupóna
                    </h2>
                    <p class="mb-4 text-xs text-blue-600">
                        Kupón sa vygeneruje a pošle zákazníkovi emailom keď označíte objednávku ako <strong>Archivovanú</strong>
                        a zákazník mal zaškrtnuté „Získaj kupón".
                    </p>
                    <form @submit.prevent="saveSettings">
                        <div class="grid gap-4 md:grid-cols-5">
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-700">Oneskorenie aktivácie (dni)</label>
                                <FormInput v-model.number="settings.delay_days" type="number" min="0" max="90" required />
                                <p class="mt-1 text-xs text-slate-400">Dní po archivácii</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-700">Platnosť kupóna (dni)</label>
                                <FormInput v-model.number="settings.valid_days" type="number" min="7" max="365" required />
                                <p class="mt-1 text-xs text-slate-400">Dní od aktivácie</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-700">Zľava (%)</label>
                                <FormInput v-model.number="settings.discount_percent" type="number" min="1" max="50" required />
                                <p class="mt-1 text-xs text-slate-400">Percentuálna zľava</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-700">Násobiteľ min. objednávky</label>
                                <FormInput v-model.number="settings.min_order_multiplier" type="number" step="0.1" min="1" max="5" required />
                                <p class="mt-1 text-xs text-slate-400">× hodnota pôv. obj.</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-700">Min. objednávka (€)</label>
                                <FormInput v-model.number="settings.min_order_floor" type="number" min="0" required />
                                <p class="mt-1 text-xs text-slate-400">Spodná hranica</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-3">
                            <button
                                type="submit"
                                :disabled="settingsSaving"
                                class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ settingsSaving ? 'Ukladám...' : 'Uložiť nastavenia' }}
                            </button>
                            <span v-if="settingsSaved" class="text-sm font-semibold text-green-600">✓ Uložené</span>
                            <span class="text-xs text-slate-400">
                                Príklad: obj. 80 € → min. {{ Math.ceil(Math.max(settings.min_order_floor, 80 * settings.min_order_multiplier) / 10) * 10 }} €,
                                platný {{ settings.delay_days }}–{{ settings.delay_days + settings.valid_days }} dní od archivácie
                            </span>
                        </div>
                    </form>
                </div>

                <!-- Formulár na manuálny kupón -->
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

                <!-- Tabuľka kupónov -->
                <div class="overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th">Kód</th>
                                <th class="thead_th">Zľava</th>
                                <th class="thead_th">Podmienky</th>
                                <th class="thead_th">Platnosť</th>
                                <th class="thead_th">Použití</th>
                                <th class="thead_th">Pre</th>
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
                                <td class="tbody_td text-xs text-slate-500">
                                    <span v-if="coupon.email" :title="coupon.email" class="truncate max-w-[120px] block">{{ coupon.email }}</span>
                                    <span v-else class="text-slate-300">—</span>
                                </td>
                                <td class="tbody_td">
                                    <span :class="coupon.active ? 'text-green-600' : 'text-gray-400'">
                                        {{ coupon.active ? 'Aktívny' : 'Neaktívny' }}
                                    </span>
                                </td>
                                <td class="tbody_td"><PanelDropdown :items="dropdownItems(coupon)" /></td>
                            </tr>
                            <tr v-if="!getCoupons.length">
                                <td colspan="8" class="tbody_td py-8 text-center text-slate-400">Žiadne kupóny</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Kôš -->
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
