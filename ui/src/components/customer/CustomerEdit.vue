<script setup lang="ts">
import { onMounted } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import useCustomers from "../../store/StoreCustomers";
import { useRoute } from "vue-router";
import router from "../../router";
import buttonSubmitComponent from "../layout/page/ButtonSubmit.vue";
import useUnsavedChanges from "../../models/useUnsavedChanges";
import CustomerFormFields from "../forms/CustomerFormFields.vue";

const { updateCustomer, fetchCustomer, getCustomer } = useCustomers();
const { setOriginalData, markAsSaved } = useUnsavedChanges(() => getCustomer.value);
const { params: { customerId } } = useRoute();

onMounted(async () => {
    await fetchCustomer(customerId);
    setOriginalData(getCustomer.value);
});

const saveCustomer = async () => {
    await updateCustomer();
    markAsSaved();
    router.push({ name: "customers.index" });
};

const buttonBack = { name: "Späť", spinner: true, link: "/zakaznici", icon: "arrow-left" };
const requiredFields = ["company", "street", "city", "postcode", "email", "name"];
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Upraviť zákazníka', buttonLink: buttonBack }" />

                <form @submit.prevent="saveCustomer" class="mt-5 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                        <h2 class="text-base font-semibold text-gray-800">Údaje zákazníka</h2>
                    </div>
                    <div class="px-6 py-5">
                        <CustomerFormFields :requiredFields="requiredFields" :withStatus="true" />
                        <div class="mt-6 flex justify-end">
                            <buttonSubmitComponent :item="{ name: 'Uložiť zmeny', spinner: true }" />
                        </div>
                    </div>
                </form>
            </div>
        </template>
    </BaseLayout>
</template>
