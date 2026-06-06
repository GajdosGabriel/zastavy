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
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="company">
                            Názov <RequiredMark />
                        </label>
                        <input
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            type="text" v-model="state.customer.company" placeholder="Názov firmy" required />
                    </div>
                    <div class="flex space-x-3">
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2 w-full" for="street">
                                Adresa
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="street" type="text" v-model="state.customer.street" placeholder="Adresa a číslo" />
                        </div>

                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="postcode">
                                PSČ
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="postcode" type="number" v-model="state.customer.postcode"
                                placeholder="Poštové smerové číslo" />
                        </div>

                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="city">
                                Mesto
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="city" type="text" v-model="state.customer.city" placeholder="Meslo" />
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                                Email <RequiredMark />
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="email" type="email" v-model="state.customer.email" required
                                placeholder="Email na zaslanie objednávky" />
                        </div>

                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                                Vaše meno
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="name" type="text" v-model="state.customer.name" placeholder="Vaše meno" />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">
                                Telefón
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="phone" type="text" v-model="state.customer.phone" placeholder="Telefón" />
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="ico">
                                ICO
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="ico" type="text" v-model="state.customer.ico" placeholder="IČO organizácie" />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="dic">
                                DIC
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="dic" type="text" v-model="state.customer.dic" placeholder="DIČ organizácie" />
                        </div>
                        <div class="mb-4 w-full">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="ic_dic">
                                SKDIC
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="ic_dic" type="text" v-model="state.customer.ic_dic"
                                placeholder="SKDIČ organizácie" />
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
