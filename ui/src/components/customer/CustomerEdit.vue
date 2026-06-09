<script setup lang="ts">
import { onMounted } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import useCustomers from "../../store/StoreCustomers";
import { useRoute } from "vue-router";
import router from "../../router";
import validationBar from "../bars/ValidationBar.vue";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import buttonSubmitComponent from "../layout/page/ButtonSubmit.vue";
import templateCustomer from "../../models/templateCustomer";
import useUnsavedChanges from '../../models/useUnsavedChanges';
import RequiredMark from "../forms/RequiredMark.vue";
import FormInput from "../forms/FormInput.vue";


const { state, updateCustomer, fetchCustomer, getCustomer, getStatuses } = useCustomers();
const { setOriginalData, markAsSaved, isFormChanged } = useUnsavedChanges(() => getCustomer.value);
const {
    params: { customerId },
} = useRoute();

onMounted(async () => {
    await fetchCustomer(customerId);
    setOriginalData(getCustomer.value); // nastavíme originál
});

const saveCustomer = async () => {

    await updateCustomer();
    router.push({ name: "customers.index" });
    markAsSaved(); // označíme ako uložené
};


const buttonBack = { name: 'Späť', spinner: true, link: '/zakaznici', icon: 'arrow-left' }
const buttonSubmit = { name: 'Uložiť', spinner: true }

</script>


<template>
    <BaseLayout>

        <template #main>
            <h1 class="page-heading">Upraviť zákazníka
                <buttonRouterLink :item="buttonBack" class="text-sm" />
            </h1>

            <div class="page-body col-span-12">

                <form @submit.prevent="saveCustomer" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4 w-full">
                    <validationBar :validations="null" />

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Názov <RequiredMark /></label>
                        <FormInput v-model="state.customer.company" placeholder="Názov firmy" required />
                    </div>
                    <div class="md:flex md:space-x-3">
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Adresa <RequiredMark /></label>
                            <FormInput v-model="state.customer.street" placeholder="Adresa a číslo" required />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Mesto <RequiredMark /></label>
                            <FormInput v-model="state.customer.city" placeholder="Mesto" required />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">PSČ <RequiredMark /></label>
                            <FormInput v-model="state.customer.postcode" placeholder="Poštové smerové číslo" required />
                        </div>
                    </div>

                    <div class="md:flex md:space-x-3">
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Email <RequiredMark /></label>
                            <FormInput v-model="state.customer.email" type="email" placeholder="Email" required />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Vaše meno <RequiredMark /></label>
                            <FormInput v-model="state.customer.name" placeholder="Vaše meno" required />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Telefón</label>
                            <FormInput v-model="state.customer.phone" placeholder="Telefón" />
                        </div>
                    </div>
                    <div class="md:flex md:space-x-3">
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">ICO</label>
                            <FormInput v-model="state.customer.ico" placeholder="IČO organizácie" />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">DIC</label>
                            <FormInput v-model="state.customer.dic" placeholder="DIČ organizácie" />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">SKDIC</label>
                            <FormInput v-model="state.customer.ic_dic" placeholder="SKDIČ organizácie" />
                        </div>
                    </div>

                    <div v-if="state.customer.status" class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="status">
                            Status <RequiredMark />
                        </label>
                        <select
                            id="status"
                            v-model="state.customer.status.value"
                            required
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        >
                            <option v-for="status in getStatuses" :key="status.value" :value="status.value">
                                {{ status.label }}
                            </option>
                        </select>
                    </div>

                    <div class="flex justify-between mt-5">
                        <buttonRouterLink :item="buttonBack" />
                        <buttonSubmitComponent :item="buttonSubmit" />
                    </div>
                </form>
            </div>
        </template>
    </BaseLayout>
</template>
