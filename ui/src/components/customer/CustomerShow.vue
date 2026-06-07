<script setup lang="ts">
import { onMounted, watch } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import useCustomers from "../../store/StoreCustomers";
import useOrders from "../../store/StoreOrders";
import { useRoute } from "vue-router";
import iconEmail from "../icons/email.vue";
import iconPhone from "../icons/phone.vue";
import iconUser from "../icons/user.vue";
import tableRowOrders from "../order/component/tableRowOrders.vue";
import templateOrder from "../../models/templateOrder";
import templateCustomer from "../../models/templateCustomer";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import buttonSubmitComponent from "../layout/page/ButtonSubmit.vue";


const route = useRoute();

const { state, fetchCustomer, getCustomer, fetchCustomerOrders } = useCustomers();
const { state: ooorrr, getOrders } = useOrders();


onMounted(() => {
    document.title = "Detail zákazníka";
});

const buttonTopRight = { name: 'Nový zákazník', spinner: true, link: '/zakaznici/create', icon: 'plus' }

const buttonBottomLeft = { name: 'Späť', spinner: true, link: '/zakaznici', icon: 'arrow-left' }

watch(
    () => route.params.customerId,
    (customerId) => {
        fetchCustomer(customerId);
        fetchCustomerOrders(customerId);
    },
    { immediate: true }
);

state.buttonTopRight = buttonTopRight;
state.buttonBottomLeft = buttonBottomLeft;
</script>

<template>
    <BaseLayout>

        <template #main>
            <h1 class="page-heading">Detail zákazníka
                <buttonRouterLink :item="state.buttonTopRight" class="text-sm" />
            </h1>

            <div class="page-body col-span-12">

                <div
                    class="bg-white p-4 border-2 border-gray-300 rounded-ms shadow mb-4 md:flex justify-between md:space-x-6">
                    <div class="md:w-1/2 max-w-xs bg-gray-50 p-2">
                        <div class="font-semibold text-lg mb-2">
                            {{ getCustomer.company }}
                        </div>
                        <div>{{ getCustomer.street }}</div>
                        <div>{{ getCustomer.city }}</div>
                        <div>{{ getCustomer.postcode }}</div>
                        <div class="border-b-2 border-gray-300 w-full mb-1 mt-1"></div>
                        <div>ICO: {{ getCustomer.ico }}</div>
                        <div>DIC: {{ getCustomer.dic }}</div>

                    </div>
                    <div class="md:w-1/2 max-w-xs bg-gray-50 p-2">
                        <div>Dátum: {{ getCustomer.created_at }}</div>
                        <div class="flex items-center">
                            <iconUser />
                            {{ getCustomer.name }}
                        </div>
                        <div class="flex items-center">
                            <iconPhone />
                            {{ getCustomer.phone }}
                        </div>
                        <div class="flex items-center">
                            <iconEmail />
                            {{ getCustomer.email }}
                        </div>

                    </div>

                    <div class="md:w-1/2 max-w-xs bg-gray-50 p-2">
                        <div>Hodnota objednávok</div>
                    </div>
                </div>



                <div class="shadow border-b border-gray-200 sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-500 border-2 border-gray-400">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th flex items-center">
                                    Id
                                </th>
                                <th class="thead_th">
                                    <!-- <icon-star :status="iconStatus" @click="onClickSelectMark" class="mx-auto" /> -->
                                </th>
                                <th class="thead_th">Name</th>
                                <th class="thead_th">Cena</th>
                                <th class="thead_th">Status</th>
                                <th class="thead_th">Expedícia</th>
                                <th class="thead_th">Panel</th>
                            </tr>
                        </thead>
                        <tbody class="">
                            <tableRowOrders v-for="order in templateOrder(getOrders)" :key="order.id" :order="order" />
                        </tbody>
                    </table>
                </div>


                <div class="flex justify-between mt-5">
                    <buttonRouterLink :item="state.buttonBottomLeft" />
                    <buttonSubmitComponent :item="state.buttonSubmit" />
                </div>

            </div>
        </template>
    </BaseLayout>
</template>
