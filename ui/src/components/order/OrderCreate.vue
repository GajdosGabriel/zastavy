<script setup>
import { computed, onMounted, ref } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import ButtonLink from "../layout/page/ButtonLink.vue";
import useCustomers from "../../store/StoreCustomers";
import useOrders from "../../store/StoreOrders";
import useProducts from "../../store/StoreProducts";
import useUser from "../../store/StoreUsers";
import router from "../../router";
import { formatDecimal } from "../../models/functions";
import RequiredMark from "../forms/RequiredMark.vue";

const { getCustomer, setCustomer, findCustomerByIco } = useCustomers();
const { storeOrder, state: orderState } = useOrders();
const { fetchProducts, getProducts } = useProducts();
const { getUser } = useUser();

const orderProducts = ref([]);
const selectedProductId = ref("");
const productSearch = ref("");
const notifyCustomer = ref(true);
const showSaveModal = ref(false);
const isSubmitting = ref(false);
const icoSearchMessage = ref("");
const isSearchingCompany = ref(false);

const buttonBack = { name: "Späť", spinner: true, link: "/objednavky", icon: "arrow-left" };

const isSuperAdmin = computed(() => getUser.value?.roles?.includes("super-admin"));

const filteredProducts = computed(() => {
    const search = productSearch.value.trim().toLowerCase();
    const products = getProducts.value || [];

    if (!search) {
        return products;
    }

    return products.filter((product) => {
        return [product.code, product.name, product.description]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(search));
    });
});

const selectedProduct = computed(() => {
    return (getProducts.value || []).find((product) => String(product.id) === String(selectedProductId.value));
});

const grandQuantity = computed(() => orderProducts.value.reduce((sum, item) => sum + Number(item.input_order || 0), 0));
const grandTotal = computed(() => orderProducts.value.reduce((sum, item) => {
    return sum + Number(item.active_price || 0) * Number(item.input_order || 0);
}, 0));

const requiredCustomerFields = ["company", "name", "email", "phone", "street", "postcode", "city"];

const isCustomerComplete = computed(() => {
    return requiredCustomerFields.every((field) => String(getCustomer.value?.[field] ?? "").trim());
});

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

const onClickIco = async () => {
    icoSearchMessage.value = "";
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
        return alert("Vyplňte povinné údaje zákazníka.");
    }

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
        orderNotice: orderState.order.notice,
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
    setCustomer({});
    orderProducts.value = [];
});
</script>

<template>
    <BaseLayout>
        <template #main>
            <h1 class="page-heading">
                Nová objednávka
                <ButtonLink :item="buttonBack" class="text-sm" />
            </h1>

            <div class="page-body col-span-12">
                <div class="grid gap-5 lg:grid-cols-3">
                    <section class="rounded border border-gray-300 bg-white p-5 shadow lg:col-span-2">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Zákazník</h2>

                        <div class="mb-4">
                            <label class="mb-2 block text-sm font-bold text-gray-700" for="ico">IČO</label>
                            <div class="flex gap-3">
                                <input
                                    id="ico"
                                    v-model="getCustomer.ico"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="IČO organizácie"
                                    @keyup.enter="onClickIco"
                                />
                                <button
                                    type="button"
                                    class="whitespace-nowrap rounded bg-blue-500 px-4 py-2 font-bold text-white hover:bg-blue-700 disabled:bg-gray-400"
                                    :disabled="isSearchingCompany"
                                    @click="onClickIco"
                                >
                                    {{ isSearchingCompany ? "Hľadám..." : "Vyhľadať firmu" }}
                                </button>
                            </div>
                            <p v-if="icoSearchMessage" class="mt-2 text-xs text-gray-500">{{ icoSearchMessage }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-bold text-gray-700">Názov firmy <RequiredMark /></label>
                                <input v-model="getCustomer.company" type="text" required class="w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-bold text-gray-700">Kontaktné meno <RequiredMark /></label>
                                <input v-model="getCustomer.name" type="text" required class="w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-bold text-gray-700">Email <RequiredMark /></label>
                                <input v-model="getCustomer.email" type="email" required class="w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-bold text-gray-700">Telefón <RequiredMark /></label>
                                <input v-model="getCustomer.phone" type="text" required class="w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-bold text-gray-700">Ulica <RequiredMark /></label>
                                <input v-model="getCustomer.street" type="text" required class="w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-bold text-gray-700">PSČ <RequiredMark /></label>
                                <input v-model="getCustomer.postcode" type="text" required class="w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-bold text-gray-700">Mesto <RequiredMark /></label>
                                <input v-model="getCustomer.city" type="text" required class="w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-bold text-gray-700">DIČ</label>
                                <input v-model="getCustomer.dic" type="text" class="w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-bold text-gray-700">IČ DPH</label>
                                <input v-model="getCustomer.ic_dic" type="text" class="w-full rounded border px-3 py-2" />
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="mb-2 block text-sm font-bold text-gray-700">Poznámka</label>
                            <input v-model="orderState.order.notice" type="text" class="w-full rounded border px-3 py-2" />
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
                                        <div class="font-semibold text-gray-900">{{ product.name }}</div>
                                        <div class="text-xs text-gray-500">{{ product.code }}</div>
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
                            <button type="button" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="confirmSave()">
                                Potvrdiť
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </template>
    </BaseLayout>
</template>
