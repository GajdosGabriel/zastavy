<script setup>
import BaseLayout from "../layout/BaseLayout.vue";
import useOrders from "../../store/StoreOrders";
import useOrderProducts from "../../store/StoreOrderProducts";
import useProducts from "../../store/StoreProducts";
import { useRoute } from "vue-router";
import { onMounted, ref, watch } from "vue";
import productTableRow from "../orderProducts/productTableRow.vue";
import { formatDecimal } from "../../models/functions";
import shippingButton from "./component/shippingButton.vue";
import iconEmail from "../icons/email.vue";
import iconPhone from "../icons/phone.vue";
import iconCalendar from "../icons/calendar.vue";
import iconUser from "../icons/user.vue";
import PageHeader from '../layout/page/pageHeader.vue';
import buttonSubmitComponent from '../layout/page/ButtonSubmit.vue';
import buttonLink from '../layout/page/ButtonLink.vue';
import axiosInstance from "../../axiosInstance";
import useErrors from "../../store/StoreErrors";

const { getOrder, fetchOrder, customer, updateOrder } = useOrders();
const { getOrderProducts, getStatement, addOrderProduct, saveNewOrderProduct } = useOrderProducts();
const { fetchProducts } = useProducts();
const { setErrors } = useErrors();

const { params: { orderId } } = useRoute();

const shippingMethods = ref([]);
const paymentMethods  = ref([]);
const selectedShippingId = ref(null);
const selectedPaymentId  = ref(null);
const originalShippingId = ref(null);
const originalPaymentId  = ref(null);

const showNotifyModal = ref(false);
const notifyCustomer  = ref(true);
const isSubmitting    = ref(false);

onMounted(async () => {
    await fetchOrder(orderId);
    fetchProducts();
    selectedShippingId.value = getOrder.value?.shipping_method?.id ?? null;
    selectedPaymentId.value  = getOrder.value?.payment_method?.id  ?? null;
    originalShippingId.value = selectedShippingId.value;
    originalPaymentId.value  = selectedPaymentId.value;

    const [sm, pm] = await Promise.all([
        axiosInstance.get('/shipping-methods'),
        axiosInstance.get('/payment-methods'),
    ]);
    shippingMethods.value = sm.data?.data ?? [];
    paymentMethods.value  = pm.data?.data ?? [];
});

watch(getOrder, (order) => {
    if (!order) return;
    if (originalShippingId.value === null) {
        selectedShippingId.value = order.shipping_method?.id ?? null;
        originalShippingId.value = selectedShippingId.value;
    }
    if (originalPaymentId.value === null) {
        selectedPaymentId.value = order.payment_method?.id ?? null;
        originalPaymentId.value = selectedPaymentId.value;
    }
});

const hasChanges = () =>
    selectedShippingId.value !== originalShippingId.value ||
    selectedPaymentId.value  !== originalPaymentId.value;

const onSaveClick = () => {
    if (hasChanges()) {
        notifyCustomer.value = true;
        showNotifyModal.value = true;
    } else {
        submitUpdate(false);
    }
};

const confirmUpdate = async () => {
    showNotifyModal.value = false;
    await submitUpdate(notifyCustomer.value);
};

const submitUpdate = async (notify) => {
    isSubmitting.value = true;
    try {
        const pendingNew = getOrderProducts.value.filter(p => p.isNew && p.product_id);
        await Promise.all(pendingNew.map(p => saveNewOrderProduct(p)));

        await axiosInstance.put(`/orders/${orderId}`, {
            shipping_method_id: selectedShippingId.value,
            payment_method_id:  selectedPaymentId.value,
            notify_customer:    notify,
        });
        originalShippingId.value = selectedShippingId.value;
        originalPaymentId.value  = selectedPaymentId.value;
    } catch (e) {
        setErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const buttonSubmit = { name: 'Uložiť', spinner: false };
const buttonBack   = { name: 'Späť',   spinner: true, link: 'orders.index', icon: 'arrow-left' };
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Upraviť objednávku', buttonLink: buttonBack }" />

                <!-- Zákazník + objednávka -->
                <div class="bg-white p-4 border-2 border-gray-300 rounded-ms shadow mb-4 md:flex justify-between md:space-x-6">
                    <div class="md:w-1/2 max-w-xs bg-gray-50 p-2">
                        <div class="font-semibold text-lg mb-2">{{ customer.company }}</div>
                        <div>{{ customer.street }}</div>
                        <div>{{ customer.city }}</div>
                        <div>{{ customer.postcode }}</div>
                        <div class="border-b-2 border-gray-300 w-full mb-1 mt-1"></div>
                        <div>ICO: {{ customer.ico }}</div>
                        <div>DIC: {{ customer.dic }}</div>
                    </div>
                    <div class="md:w-1/2 max-w-xs bg-gray-50 p-2">
                        <div class="font-semibold text-lg mb-2">
                            <span class="text-gray-400">No.</span> {{ getOrder.serial_number }}
                        </div>
                        <div class="border-b-2 border-gray-300 w-full mb-1 mt-1"></div>
                        <div class="flex items-center"><icon-user />{{ getOrder.user?.username || customer.name }}</div>
                        <div class="flex items-center"><icon-phone />{{ getOrder.user?.phone || customer.phone }}</div>
                        <div class="flex items-center"><icon-email />{{ getOrder.user?.email || customer.email }}</div>
                        <div class="border-b-2 border-gray-300 w-full mb-1 mt-1"></div>
                        <div>{{ getOrder.created_at_human }}</div>
                        <div class="flex items-center">
                            <icon-calendar />{{ getOrder.created_at }}
                            <div class="text-gray-500 ml-2">prijatá</div>
                        </div>
                        <div class="flex items-center">
                            <div v-for="shipping in getOrder.shippings" :key="shipping.id" class="flex items-center">
                                <icon-calendar />{{ shipping.created_at }}
                                <div class="text-gray-500 ml-2">expedovaná</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Doprava + platba -->
                <div v-if="shippingMethods.length || paymentMethods.length"
                     class="mb-4 grid gap-4 md:grid-cols-2 bg-white p-4 border-2 border-gray-300 rounded-ms shadow">
                    <div v-if="shippingMethods.length">
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Spôsob dopravy</label>
                        <select v-model="selectedShippingId"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option :value="null">— nevybrané —</option>
                            <option v-for="m in shippingMethods" :key="m.id" :value="m.id">
                                {{ m.name }} ({{ m.price > 0 ? `${parseFloat(m.price).toFixed(2)} €` : 'Zdarma' }})
                            </option>
                        </select>
                    </div>
                    <div v-if="paymentMethods.length">
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Spôsob platby</label>
                        <select v-model="selectedPaymentId"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option :value="null">— nevybrané —</option>
                            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">
                                {{ m.name }}{{ m.fee > 0 ? ` (+ ${parseFloat(m.fee).toFixed(2)} €)` : '' }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Zákaznícka poznámka + záujem o kupón -->
                <div v-if="getOrder.note || getOrder.wants_coupon"
                     class="mb-4 space-y-1 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <div v-if="getOrder.note"><span class="font-semibold">Poznámka:</span> {{ getOrder.note }}</div>
                    <div v-if="getOrder.wants_coupon" class="font-semibold">Zákazník chce získať zľavový kupón na ďalší nákup</div>
                </div>

                <!-- Produkty -->
                <div class="flex flex-col">
                    <div class="-my-2 sm:-mx-6 lg:-mx-8">
                        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                            <div class="shadow border-b border-gray-200 sm:rounded-lg">
                                <table class="min-w-full divide-y border-2 border-gray-500">
                                    <thead class="thead">
                                        <tr>
                                            <th class="thead_th">Name</th>
                                            <th class="thead_th">Množstvo</th>
                                            <th class="thead_th">Cena</th>
                                            <th class="thead_th">DPH</th>
                                            <th class="thead_th">s DPH</th>
                                            <th class="thead_th">Exped.</th>
                                            <th class="thead_th">Storno</th>
                                            <th class="thead_th">Panel</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <product-table-row v-for="item in getOrderProducts" :key="item.id" :item="item" />
                                    </tbody>
                                    <tfoot class="bg-gray-200 divide-gray-200">
                                        <tr>
                                            <td class="tbody_td font-semibold">Rekapitulácia objednávky:</td>
                                            <td class="tbody_td" colspan="3">Položiek: {{ getStatement.grandQuantity }}</td>
                                            <td class="tbody_td font-semibold">
                                                Cena: {{ formatDecimal(getStatement.grandTotal) }} €
                                            </td>
                                            <td colspan="2" class="tbody_td font-semibold text-3xl">
                                                <shipping-button :order="getOrder" />
                                            </td>
                                            <td colspan="1" class="tbody_td font-semibold" @click="addOrderProduct(orderId)">
                                                <span class="text-3xl cursor-pointer">+</span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between mt-5">
                    <buttonLink :item="buttonBack" />
                    <buttonSubmitComponent :item="buttonSubmit" :loading="isSubmitting" type="button" @click="onSaveClick" />
                </div>
            </div>
        </template>
    </BaseLayout>

    <!-- Modal: Informovať zákazníka o zmene -->
    <Teleport to="body">
        <div v-if="showNotifyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-2 text-lg font-semibold text-gray-800">Zmena objednávky</h3>
                <p class="mb-4 text-sm text-gray-600">
                    Zmenili ste spôsob dopravy alebo platby. Chcete o tejto zmene informovať zákazníka emailom?
                </p>
                <label class="mb-5 flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                    <input type="checkbox" v-model="notifyCustomer" class="rounded" />
                    Informovať zákazníka o zmene
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showNotifyModal = false"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                        Zrušiť
                    </button>
                    <button type="button" @click="confirmUpdate"
                        class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Uložiť
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
