<script setup lang="ts">
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import useCustomers from "../../store/StoreCustomers";
import router from "../../router";
import buttonSubmitComponent from "../layout/page/ButtonSubmit.vue";
import CustomerFormFields from "../forms/CustomerFormFields.vue";

const { storeCustomer, resetCustomer } = useCustomers();

resetCustomer();

const onClickSaveCustomer = () => {
    storeCustomer();
    router.push({ name: "customers.index" });
};

const buttonBack = { name: "Späť", spinner: true, link: "/zakaznici", icon: "arrow-left" };
const requiredFields = ["company", "email"];
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Nový zákazník', buttonLink: buttonBack }" />

                <form @submit.prevent="onClickSaveCustomer" class="mt-5 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                        <h2 class="text-base font-semibold text-gray-800">Údaje zákazníka</h2>
                    </div>
                    <div class="px-6 py-5">
                        <CustomerFormFields :requiredFields="requiredFields" />
                        <div class="mt-6 flex justify-end">
                            <buttonSubmitComponent :item="{ name: 'Uložiť zákazníka', spinner: true }" />
                        </div>
                    </div>
                </form>
            </div>
        </template>
    </BaseLayout>
</template>
