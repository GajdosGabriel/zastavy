<script setup lang="ts">
import { ref } from "vue";
import useCustomers from "../../store/StoreCustomers";
import FormInput from "./FormInput.vue";
import RequiredMark from "./RequiredMark.vue";

const props = withDefaults(defineProps<{
    fieldErrors?: Record<string, string>;
    highlightRequired?: boolean;
    requiredFields?: string[];
    withStatus?: boolean;
}>(), {
    fieldErrors: () => ({}),
    highlightRequired: false,
    requiredFields: () => [],
    withStatus: false,
});

const { getCustomer, findCustomerByIco, getStatuses } = useCustomers();

const isSearchingCompany = ref(false);
const icoSearchMessage = ref("");

const isRequired = (field: string) => props.requiredFields.includes(field);

const isMissing = (field: string) =>
    props.highlightRequired && isRequired(field) && !String(getCustomer.value?.[field] ?? "").trim();

const fieldError = (field: string) => {
    const err = props.fieldErrors?.[`customer.${field}`] ?? props.fieldErrors?.[field];
    return Array.isArray(err) ? err[0] : (err ?? "");
};

const icoValidationError = () => {
    if (String(getCustomer.value?.ico ?? "").length > 8) {
        return "IČO môže mať maximálne 8 číslic.";
    }
    return fieldError("ico");
};

const onlyDigits = (event: Event) => {
    getCustomer.value.ico = String((event.target as HTMLInputElement).value || "").replace(/\D/g, "");
};

const onClickIco = async () => {
    icoSearchMessage.value = "";
    if (icoValidationError()) return;

    isSearchingCompany.value = true;
    try {
        await findCustomerByIco();
        icoSearchMessage.value = "Údaje firmy boli doplnené.";
    } catch (error: any) {
        icoSearchMessage.value = error.response?.data?.message || error.message || "Firmu sa nepodarilo nájsť.";
    } finally {
        isSearchingCompany.value = false;
    }
};
</script>

<template>
    <!-- IČO vyhľadávanie -->
    <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50 p-4">
        <label class="mb-2 block text-sm font-semibold text-blue-900">Rýchle doplnenie — vyhľadajte firmu podľa IČO</label>
        <div class="flex gap-2">
            <FormInput
                v-model="getCustomer.ico"
                placeholder="IČO organizácie"
                inputmode="numeric"
                pattern="[0-9]*"
                :invalid="!!icoValidationError()"
                @input="onlyDigits"
                @keyup.enter="onClickIco"
            />
            <button
                type="button"
                @click="onClickIco"
                :disabled="isSearchingCompany"
                class="whitespace-nowrap rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:bg-gray-400"
            >
                {{ isSearchingCompany ? "Hľadám..." : "Vyhľadať firmu" }}
            </button>
        </div>
        <p v-if="icoValidationError()" class="mt-2 text-xs font-semibold text-red-600">{{ icoValidationError() }}</p>
        <p v-if="icoSearchMessage" class="mt-2 text-xs text-blue-700">{{ icoSearchMessage }}</p>
    </div>

    <!-- Polia -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="sm:col-span-2 lg:col-span-3">
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                Názov firmy <RequiredMark v-if="isRequired('company')" />
            </label>
            <FormInput v-model="getCustomer.company" :invalid="isMissing('company')" :error="fieldError('company')" placeholder="Názov firmy" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                Ulica a číslo <RequiredMark v-if="isRequired('street')" />
            </label>
            <FormInput v-model="getCustomer.street" :invalid="isMissing('street')" :error="fieldError('street')" placeholder="Ulica a číslo" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                PSČ <RequiredMark v-if="isRequired('postcode')" />
            </label>
            <FormInput v-model="getCustomer.postcode" :invalid="isMissing('postcode')" :error="fieldError('postcode')" placeholder="PSČ" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                Mesto <RequiredMark v-if="isRequired('city')" />
            </label>
            <FormInput v-model="getCustomer.city" :invalid="isMissing('city')" :error="fieldError('city')" placeholder="Mesto" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                Kontaktné meno <RequiredMark v-if="isRequired('name')" />
            </label>
            <FormInput v-model="getCustomer.name" :invalid="isMissing('name')" :error="fieldError('name')" placeholder="Meno kontaktnej osoby" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                Email <RequiredMark v-if="isRequired('email')" />
            </label>
            <FormInput v-model="getCustomer.email" type="email" :invalid="isMissing('email') || !!fieldError('email')" :error="fieldError('email')" placeholder="Email" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                Telefón <RequiredMark v-if="isRequired('phone')" />
            </label>
            <FormInput v-model="getCustomer.phone" :invalid="isMissing('phone')" :error="fieldError('phone')" placeholder="Telefón" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">IČO</label>
            <FormInput v-model="getCustomer.ico" :invalid="!!icoValidationError()" :error="icoValidationError()" inputmode="numeric" pattern="[0-9]*" placeholder="IČO" @input="onlyDigits" @keyup.enter="onClickIco" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">DIČ</label>
            <FormInput v-model="getCustomer.dic" placeholder="DIČ" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">IČ DPH</label>
            <FormInput v-model="getCustomer.ic_dic" placeholder="IČ DPH" />
        </div>

        <div v-if="withStatus && getCustomer.status" class="sm:col-span-2 lg:col-span-3">
            <label class="mb-1.5 block text-sm font-semibold text-gray-700">Status <RequiredMark /></label>
            <select
                v-model="getCustomer.status.value"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
                <option v-for="status in getStatuses" :key="status.value" :value="status.value">
                    {{ status.label }}
                </option>
            </select>
        </div>
    </div>
</template>
