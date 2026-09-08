<script setup>
/**
 * Adresár doručovacích adries zákazníka.
 *
 * Sídlo firmy tu nie je — to žije na zákazníkovi ako fakturačná adresa. Tu sú
 * miesta, kam sa zákazníkovi vozí tovar: pobočka, sklad, škola, kultúrny dom.
 *
 * Úprava riadku sa nedotkne už zadaných objednávok — tie si adresu odfotili.
 */
import { onMounted, ref, watch } from 'vue';
import axiosInstance from '../../../axiosInstance';
import useErrors from '../../../store/StoreErrors';

const props = defineProps({
    customerId: { type: [String, Number], required: true },
    canEdit: { type: Boolean, default: true },
});

const { setErrors } = useErrors();

const addresses = ref([]);
const loading = ref(false);
const saving = ref(false);
const editingId = ref(null);
const showForm = ref(false);
const fieldErrors = ref({});

const emptyForm = () => ({
    label: '', company: '', name: '', street: '', postcode: '', city: '',
    phone: '', note: '', is_default: false,
});

const form = ref(emptyForm());

const endpoint = () => `/customers/${props.customerId}/addresses`;

const fetchAddresses = async () => {
    loading.value = true;
    try {
        const response = await axiosInstance.get(endpoint());
        addresses.value = response.data?.data ?? [];
    } catch (e) {
        setErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchAddresses);
watch(() => props.customerId, fetchAddresses);

const openCreate = () => {
    editingId.value = null;
    form.value = emptyForm();
    fieldErrors.value = {};
    showForm.value = true;
};

const openEdit = (address) => {
    editingId.value = address.id;
    form.value = {
        label: address.label ?? '',
        company: address.company ?? '',
        name: address.name ?? '',
        street: address.street ?? '',
        postcode: address.postcode ?? '',
        city: address.city ?? '',
        phone: address.phone ?? '',
        note: address.note ?? '',
        is_default: Boolean(address.is_default),
    };
    fieldErrors.value = {};
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    editingId.value = null;
    fieldErrors.value = {};
};

const save = async () => {
    saving.value = true;
    fieldErrors.value = {};

    try {
        if (editingId.value) {
            await axiosInstance.put(`${endpoint()}/${editingId.value}`, form.value);
        } else {
            await axiosInstance.post(endpoint(), form.value);
        }
        await fetchAddresses();
        closeForm();
    } catch (e) {
        if (e.response?.status === 422) {
            fieldErrors.value = e.response.data.errors ?? {};
        } else {
            setErrors(e);
        }
    } finally {
        saving.value = false;
    }
};

const remove = async (address) => {
    if (!window.confirm(`Zmazať adresu ${address.summary}?`)) return;

    try {
        await axiosInstance.delete(`${endpoint()}/${address.id}`);
        await fetchAddresses();
    } catch (e) {
        setErrors(e);
    }
};

const errorFor = (field) => {
    const error = fieldErrors.value?.[field];
    return Array.isArray(error) ? error[0] : (error ?? '');
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-2.5">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Doručovacie adresy</span>
            <button
                v-if="canEdit && !showForm"
                type="button"
                @click="openCreate"
                class="rounded border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-100"
            >
                + Pridať adresu
            </button>
        </div>

        <!-- Formulár -->
        <div v-if="showForm" class="border-b border-gray-100 bg-gray-50 px-5 py-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Pomenovanie</label>
                    <input v-model="form.label" type="text" placeholder="Napr. Sklad Nitra"
                           class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Názov príjemcu</label>
                    <input v-model="form.company" type="text" placeholder="Napr. Základná škola Testovce"
                           class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Ulica a číslo *</label>
                    <input v-model="form.street" type="text"
                           class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none"
                           :class="errorFor('street') ? 'border-red-500 bg-red-50' : 'border-gray-300 focus:border-blue-500'" />
                    <p v-if="errorFor('street')" class="mt-1 text-xs font-semibold text-red-600">{{ errorFor('street') }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">PSČ *</label>
                    <input v-model="form.postcode" type="text"
                           class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none"
                           :class="errorFor('postcode') ? 'border-red-500 bg-red-50' : 'border-gray-300 focus:border-blue-500'" />
                    <p v-if="errorFor('postcode')" class="mt-1 text-xs font-semibold text-red-600">{{ errorFor('postcode') }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Mesto *</label>
                    <input v-model="form.city" type="text"
                           class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none"
                           :class="errorFor('city') ? 'border-red-500 bg-red-50' : 'border-gray-300 focus:border-blue-500'" />
                    <p v-if="errorFor('city')" class="mt-1 text-xs font-semibold text-red-600">{{ errorFor('city') }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Kontaktná osoba na mieste</label>
                    <input v-model="form.name" type="text"
                           class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Telefón na mieste</label>
                    <input v-model="form.phone" type="text"
                           class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Poznámka pre doručenie</label>
                    <input v-model="form.note" type="text" placeholder="Napr. zvoniť na vrátnicu"
                           class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
            </div>

            <div class="mt-3 flex items-center justify-between">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" v-model="form.is_default" class="rounded" />
                    Predvolená doručovacia adresa
                </label>
                <div class="flex gap-2">
                    <button type="button" @click="closeForm"
                            class="rounded bg-gray-200 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                        Zrušiť
                    </button>
                    <button type="button" @click="save" :disabled="saving"
                            class="rounded bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-gray-400">
                        {{ saving ? 'Ukladám…' : 'Uložiť' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Zoznam -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="thead">
                    <tr>
                        <th class="thead_th">Pomenovanie</th>
                        <th class="thead_th">Adresa</th>
                        <th class="thead_th">Kontakt</th>
                        <th class="thead_th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="address in addresses" :key="address.id" class="tr">
                        <td class="tbody_td">
                            <span class="font-semibold">{{ address.label || '—' }}</span>
                            <span v-if="address.is_default"
                                  class="ml-2 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">
                                predvolená
                            </span>
                        </td>
                        <td class="tbody_td">
                            <div v-if="address.company" class="font-medium text-gray-800">{{ address.company }}</div>
                            <div class="text-gray-600">{{ address.street }}</div>
                            <div class="text-gray-600">{{ address.postcode }} {{ address.city }}</div>
                            <div v-if="address.note" class="mt-0.5 text-xs text-gray-400">{{ address.note }}</div>
                        </td>
                        <td class="tbody_td text-gray-600">
                            <div v-if="address.name">{{ address.name }}</div>
                            <a v-if="address.phone" :href="`tel:${address.phone}`" class="text-blue-600 hover:underline">{{ address.phone }}</a>
                            <span v-if="!address.name && !address.phone">—</span>
                        </td>
                        <td class="tbody_td whitespace-nowrap text-right">
                            <button v-if="canEdit" type="button" @click="openEdit(address)"
                                    class="mr-3 text-xs text-indigo-600 hover:underline">Upraviť</button>
                            <button v-if="canEdit" type="button" @click="remove(address)"
                                    class="text-xs text-red-600 hover:underline">Zmazať</button>
                        </td>
                    </tr>
                    <tr v-if="!addresses.length && !loading">
                        <td colspan="4" class="px-6 py-6 text-center text-sm text-gray-400">
                            Žiadne uložené adresy — tovar chodí na fakturačnú adresu.
                        </td>
                    </tr>
                    <tr v-if="loading">
                        <td colspan="4" class="px-6 py-6 text-center text-sm text-gray-400">Načítavam…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
