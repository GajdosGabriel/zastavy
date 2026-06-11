<script setup>
import { computed, onMounted, ref } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import useCustomers from "../../store/StoreCustomers";
import useOrders from "../../store/StoreOrders";
import useProducts from "../../store/StoreProducts";
import useUser from "../../store/StoreUsers";
import useErrors from "../../store/StoreErrors";
import router from "../../router";
import { formatDecimal } from "../../models/functions";
import SpinnerButton from "../icons/spinnerButton.vue";
import loadingStore from "../../store/StoreLoading";
import CustomerFormFields from "../forms/CustomerFormFields.vue";

const { getCustomer, setCustomer, fetchCustomer } = useCustomers();
const { storeOrder, state: orderState } = useOrders();
const { fetchProducts, getProducts } = useProducts();
const { getUser } = useUser();
const { getFieldErrors, setErrors, resetErrors } = useErrors();

const orderProducts = ref([]);
const selectedProductId = ref("");
const productSearch = ref("");
const notifyCustomer = ref(true);
const showSaveModal = ref(false);
const isSubmitting = ref(false);
const highlightMissingRequired = ref(false);

const buttonBack = { name: "Späť", spinner: true, link: "/objednavky", icon: "arrow-left" };
const requiredCustomerFields = ["company", "name", "email", "phone", "street", "postcode", "city"];

const isSuperAdmin = computed(() => getUser.value?.roles?.includes("super-admin"));

const filteredProducts = computed(() => {
    const search = productSearch.value.trim().toLowerCase();
    const products = getProducts.value || [];
    if (!search) return products;
    return products.filter((product) =>
        [product.code, product.name, product.description]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(search))
    );
});

const selectedProduct = computed(() =>
    (getProducts.value || []).find((product) => String(product.id) === String(selectedProductId.value))
);

const grandQuantity = computed(() => orderProducts.value.reduce((sum, item) => sum + Number(item.input_order || 0), 0));
const grandTotal = computed(() => orderProducts.value.reduce((sum, item) =>
    sum + Number(item.active_price || 0) * Number(item.input_order || 0), 0));

const isCustomerComplete = computed(() =>
    requiredCustomerFields.every((field) => String(getCustomer.value?.[field] ?? "").trim())
);

const addProduct = () => {
    const product = selectedProduct.value;

    if (!product) {
        return;
    }

    const existing = orderProducts.value.find((item) => item.id === product.id);
    const minOrder = Number(product.min_order || 1);

    if (existing) {
        existing.input_order = Number(existing.input_order || 0) + minOrder;
    } else {
        orderProducts.value.push({
            ...product,
            input_order: Number(product.input_order || minOrder),
        });
    }

    selectedProductId.value = "";
};

const removeProduct = (product) => {
    orderProducts.value = orderProducts.value.filter((item) => item.id !== product.id);
};

const normalizeQuantity = (product) => {
    const minOrder = Number(product.min_order || 1);
    product.input_order = Math.max(minOrder, Number(product.input_order || minOrder));
};

const onlyDigits = (event) => {
    getCustomer.value.ico = String(event.target.value || '').replace(/\D/g, '');
};

const onClickIco = async () => {
    icoSearchMessage.value = "";

    if (icoError()) {
        return;
    }

    isSearchingCompany.value = true;

    try {
        await findCustomerByIco();
        icoSearchMessage.value = "Údaje firmy boli doplnené.";
    } catch (error) {
        icoSearchMessage.value = error.response?.data?.message || error.message || "Firmu sa nepodarilo nájsť.";
    } finally {
        isSearchingCompany.value = false;
    }
};

const openSaveModal = () => {
    if (!orderProducts.value.length) {
        return alert("Pridajte aspoň jednu položku objednávky.");
    }

    if (!isCustomerComplete.value) {
        highlightMissingRequired.value = true;
        setErrors({ message: "Vyplňte všetky povinné údaje zákazníka označené hviezdičkou." });
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }

    highlightMissingRequired.value = false;
    resetErrors();

    if (isSuperAdmin.value) {
        notifyCustomer.value = true;
        showSaveModal.value = true;
        return;
    }

    confirmSave(false);
};

const confirmSave = async (sendNotification = notifyCustomer.value) => {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    const order = await storeOrder({
        customer: getCustomer.value,
        orderProducts: orderProducts.value,
        note: orderState.order.note || null,
        notify_customer: Boolean(sendNotification),
    });

    isSubmitting.value = false;
    showSaveModal.value = false;

    if (order?.id) {
        router.push({ name: "orders.show", params: { orderId: order.id } });
    }
};

onMounted(() => {
    fetchProducts();
    orderProducts.value = [];

    const user = getUser.value;
    if (!isSuperAdmin.value && user?.customer_id) {
        fetchCustomer(user.customer_id);
    } else {
        setCustomer({});
    }
});
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Nová objednávka', buttonLink: buttonBack }" />
<div class="grid gap-5 lg:grid-cols-3">
                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
                        <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                            <h2 class="text-base font-semibold text-gray-800">Zákazník</h2>
                        </div>
                        <div class="p-5">
                            <CustomerFormFields
                                :fieldErrors="getFieldErrors"
                                :highlightRequired="highlightMissingRequired"
                                :requiredFields="requiredCustomerFields"
                            />
                            <div class="mt-4">
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700">Poznámka</label>
                                <input v-model="orderState.order.note" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Poznámka k objednávke" />
                            </div>
                        </div>
                    </section>

                    <aside class="rounded border border-gray-300 bg-white p-5 shadow">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Súhrn</h2>
                        <div class="mb-2 flex justify-between text-sm">
                            <span class="text-gray-500">Položky</span>
                            <span class="font-semibold">{{ orderProducts.length }}</span>
                        </div>
                        <div class="mb-2 flex justify-between text-sm">
                            <span class="text-gray-500">Množstvo</span>
                            <span class="font-semibold">{{ grandQuantity }} ks</span>
                        </div>
                        <div class="mb-5 flex justify-between text-sm">
                            <span class="text-gray-500">Spolu</span>
                            <span class="font-semibold">{{ formatDecimal(grandTotal) }} €</span>
                        </div>
                        <button
                            type="button"
                            class="w-full rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-gray-400"
                            :disabled="isSubmitting"
                            @click="openSaveModal"
                        >
                            {{ isSubmitting ? "Ukladám..." : "Uložiť objednávku" }}
                        </button>
                    </aside>
                </div>

                <section class="mt-5 rounded border border-gray-300 bg-white p-5 shadow">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Položky objednávky</h2>

                    <div class="mb-4 grid gap-3 md:grid-cols-[1fr_2fr_auto]">
                        <input
                            v-model="productSearch"
                            type="text"
                            class="rounded border px-3 py-2"
                            placeholder="Filtrovať podľa kódu alebo názvu"
                        />
                        <select v-model="selectedProductId" class="rounded border px-3 py-2">
                            <option value="">Vyberte produkt</option>
                            <option v-for="product in filteredProducts" :key="product.id" :value="product.id">
                                {{ product.code }} - {{ product.name }} ({{ formatDecimal(product.active_price) }} €)
                            </option>
                        </select>
                        <button type="button" class="rounded bg-gray-800 px-4 py-2 text-white hover:bg-gray-700" @click="addProduct">
                            Pridať
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="thead">
                                <tr>
                                    <th class="thead_th text-left">Produkt</th>
                                    <th class="thead_th text-center">Cena</th>
                                    <th class="thead_th text-center">Množstvo</th>
                                    <th class="thead_th text-center">Spolu</th>
                                    <th class="thead_th text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="product in orderProducts" :key="product.id">
                                    <td class="tbody_td">
                                        <div class="flex items-center gap-3">
                                            <img
                                                v-if="product.thumb"
                                                :src="product.thumb"
                                                :alt="product.name"
                                                class="h-10 w-10 shrink-0 rounded border border-gray-200 object-cover"
                                            />
                                            <div>
                                                <div class="font-semibold text-gray-900">{{ product.name }}</div>
                                                <div class="text-xs text-gray-500">{{ product.code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="tbody_td text-center">{{ formatDecimal(product.active_price) }} €</td>
                                    <td class="tbody_td text-center">
                                        <input
                                            v-model.number="product.input_order"
                                            type="number"
                                            :min="product.min_order || 1"
                                            class="w-24 rounded border px-2 py-1 text-center"
                                            @input="normalizeQuantity(product)"
                                        />
                                        {{ product.unit_value }}
                                    </td>
                                    <td class="tbody_td text-center font-semibold">
                                        {{ formatDecimal(Number(product.active_price || 0) * Number(product.input_order || 0)) }} €
                                    </td>
                                    <td class="tbody_td text-center">
                                        <button type="button" class="rounded bg-red-50 px-3 py-1 text-xs font-semibold text-red-700" @click="removeProduct(product)">
                                            Vymazať
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!orderProducts.length">
                                    <td colspan="5" class="tbody_td py-8 text-center text-gray-500">
                                        Objednávka zatiaľ nemá položky.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Teleport to="body">
                <div v-if="showSaveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
                    <div class="w-full max-w-sm rounded bg-white p-5 shadow-lg">
                        <h3 class="mb-3 text-lg font-semibold text-gray-800">Uložiť objednávku</h3>
                        <p class="mb-4 text-sm text-gray-600">
                            Vytvoriť objednávku pre {{ getCustomer.company || getCustomer.name }}?
                        </p>
                        <label class="mb-5 flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="notifyCustomer" type="checkbox" class="rounded" />
                            Poslať notifikáciu používateľovi
                        </label>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700" @click="showSaveModal = false">
                                Zrušiť
                            </button>
                            <button type="button" @click="confirmSave()"
                                :disabled="loadingStore.isLoading"
                                class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:bg-blue-400 disabled:cursor-not-allowed">
                                <SpinnerButton v-if="loadingStore.isLoading" />
                                Potvrdiť
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </template>
    </BaseLayout>
</template>
