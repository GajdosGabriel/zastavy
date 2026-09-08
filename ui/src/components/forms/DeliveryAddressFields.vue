<script setup lang="ts">
/**
 * Doručovacia adresa — spoločný blok pre košík, novú objednávku aj verejnú
 * zmenu adresy z e-mailu.
 *
 * Kým je prepínač vypnutý, tovar ide na fakturačnú adresu a formulár neposiela
 * nič. Zapnutie sa dá čítať zvonku (`v-model:enabled`), aby si volajúci vedel
 * povedať, či má do requestu vôbec dať kľúč `delivery`.
 */
import { computed } from "vue";
import FormInput from "./FormInput.vue";
import RequiredMark from "./RequiredMark.vue";

const props = withDefaults(defineProps<{
    /** Objekt s poľami company, name, street, postcode, city, phone, note, label, save_address. */
    modelValue: Record<string, any>;
    enabled: boolean;
    fieldErrors?: Record<string, any>;
    /** Fakturačná adresa — ukazuje sa ako to, čo platí, kým je prepínač vypnutý. */
    billing?: Record<string, any> | null;
    /** Uložené adresy zákazníka; prázdne pole výber skryje. */
    savedAddresses?: any[];
    /** Ponúknuť „uložiť do adresára" — vo verejnom formulári nemá zmysel. */
    allowSave?: boolean;
    title?: string;
}>(), {
    fieldErrors: () => ({}),
    billing: null,
    savedAddresses: () => [],
    allowSave: false,
    title: "Adresa doručenia",
});

const emit = defineEmits<{
    (e: "update:enabled", value: boolean): void;
    (e: "pick", address: any | null): void;
}>();

const fieldError = (field: string) => {
    const error = props.fieldErrors?.[`delivery.${field}`] ?? props.fieldErrors?.[field];
    return Array.isArray(error) ? error[0] : (error ?? "");
};

const billingLine = computed(() => {
    const billing = props.billing ?? {};
    return [
        billing.company,
        billing.street,
        [billing.postcode, billing.city].filter(Boolean).join(" "),
    ].filter(Boolean).join(", ");
});

const toggle = (value: boolean) => {
    emit("update:enabled", value);
};

const applySaved = (event: Event) => {
    const id = (event.target as HTMLSelectElement).value;
    const address = props.savedAddresses.find((item) => String(item.id) === String(id)) ?? null;
    emit("pick", address);
};
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-800">{{ title }}</h2>
        </div>

        <div class="px-6 py-5">
            <!-- Prepínač: sídlo vs. iná adresa -->
            <div class="space-y-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                       :class="!enabled ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                    <input type="radio" :checked="!enabled" class="mt-0.5 h-4 w-4 text-blue-600" @change="toggle(false)" />
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Doručiť na fakturačnú adresu</span>
                        <span v-if="billingLine" class="mt-0.5 block text-gray-500">{{ billingLine }}</span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                       :class="enabled ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                    <input type="radio" :checked="enabled" class="mt-0.5 h-4 w-4 text-blue-600" @change="toggle(true)" />
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Doručiť na inú adresu</span>
                        <span class="mt-0.5 block text-gray-500">Pobočka, sklad, škola, kultúrny dom — kdekoľvek inde než sídlo.</span>
                    </span>
                </label>
            </div>

            <div v-if="enabled" class="mt-5 space-y-4">
                <!-- Uložené adresy zákazníka -->
                <div v-if="savedAddresses.length">
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">Uložené adresy</label>
                    <select
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        @change="applySaved"
                    >
                        <option value="">— vypísať novú adresu —</option>
                        <option v-for="address in savedAddresses" :key="address.id" :value="address.id">
                            {{ address.label ? address.label + ' — ' : '' }}{{ address.summary }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Názov príjemcu</label>
                        <FormInput v-model="modelValue.company" :error="fieldError('company')" placeholder="Napr. Základná škola Testovce" field-key="delivery.company" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                            Ulica a číslo <RequiredMark />
                        </label>
                        <FormInput v-model="modelValue.street" :error="fieldError('street')" placeholder="Ulica a číslo" field-key="delivery.street" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                            PSČ <RequiredMark />
                        </label>
                        <FormInput v-model="modelValue.postcode" :error="fieldError('postcode')" placeholder="PSČ" field-key="delivery.postcode" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                            Mesto <RequiredMark />
                        </label>
                        <FormInput v-model="modelValue.city" :error="fieldError('city')" placeholder="Mesto" field-key="delivery.city" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Kontaktná osoba na mieste</label>
                        <FormInput v-model="modelValue.name" :error="fieldError('name')" placeholder="Kto zásielku prevezme" field-key="delivery.name" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Telefón na mieste</label>
                        <FormInput v-model="modelValue.phone" :error="fieldError('phone')" placeholder="Telefón pre kuriéra" field-key="delivery.phone" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Poznámka pre doručenie</label>
                        <FormInput v-model="modelValue.note" :error="fieldError('note')" placeholder="Napr. doručiť na sekretariát, zvoniť na vrátnicu" field-key="delivery.note" />
                    </div>
                </div>

                <!-- Uloženie do adresára -->
                <div v-if="allowSave" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" v-model="modelValue.save_address" class="rounded" />
                        Uložiť adresu pre ďalšie objednávky
                    </label>
                    <div v-if="modelValue.save_address" class="mt-3">
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Pomenovanie</label>
                        <FormInput v-model="modelValue.label" placeholder="Napr. Škola, Sklad Nitra" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
