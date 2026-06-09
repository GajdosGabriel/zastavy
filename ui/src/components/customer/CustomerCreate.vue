<script setup lang="ts">
import BaseLayout from "../layout/BaseLayout.vue";
import useCustomers from "../../store/StoreCustomers";
import router from "../../router";
import validationBar from "../bars/ValidationBar.vue";
import { onMounted } from "vue";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import buttonSubmitComponent from "../layout/page/ButtonSubmit.vue";
import templateCustomer from "../../models/templateCustomer";
import ButtonSave from "../../types/ButtonSubmit";
import RequiredMark from "../forms/RequiredMark.vue";
import FormInput from "../forms/FormInput.vue";

const { state, storeCustomer, resetCustomer, getCustomer } = useCustomers();

onMounted(() => {
    resetCustomer();
})

const onClickSaveCustomer = () => {
    storeCustomer();
    router.push({ name: "customers.index" });
};

const buttonSubmit = { name: 'Uložiť', spinner: true }
const buttonBack = { name: 'Späť', spinner: true, link: '/zakaznici', icon: 'arrow-left' }

</script>

<template>
    <BaseLayout>

        <template #main>
            <h1 class="page-heading">Nový zákazník
                <buttonRouterLink :item="buttonBack" class="text-sm" />
            </h1>

            <div class="page-body col-span-8">

                <form @submit.prevent="onClickSaveCustomer"
                    class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4 w-full">
                    <validationBar :validations="null" />

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Názov <RequiredMark /></label>
                        <FormInput v-model="state.customer.company" placeholder="Názov firmy" required />
                    </div>
                    <div class="flex space-x-3">
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Adresa</label>
                            <FormInput v-model="state.customer.street" placeholder="Adresa a číslo" />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">PSČ</label>
                            <FormInput v-model="state.customer.postcode" placeholder="Poštové smerové číslo" />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Mesto</label>
                            <FormInput v-model="state.customer.city" placeholder="Mesto" />
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Email <RequiredMark /></label>
                            <FormInput v-model="state.customer.email" type="email" placeholder="Email" required />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Vaše meno</label>
                            <FormInput v-model="state.customer.name" placeholder="Vaše meno" />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Telefón</label>
                            <FormInput v-model="state.customer.phone" placeholder="Telefón" />
                        </div>
                    </div>
                    <div class="flex space-x-3">
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

                    <div class="flex justify-between mt-5">
                        <buttonRouterLink :item="buttonBack" />
                        <buttonSubmitComponent :item="buttonSubmit" />
                    </div>
                </form>
            </div>
        </template>
    </BaseLayout>

</template>
