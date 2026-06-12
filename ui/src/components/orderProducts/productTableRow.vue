<script setup>
import useOrders from "../../store/StoreOrders";
import { formatDecimal } from "../../models/functions";
import useOrderProducts from "../../store/StoreOrderProducts";
import useProducts from "../../store/StoreProducts";
import PanelDropdown from "../layout/PanelDropdown.vue";
import useErrors from "../../store/StoreErrors";

const props = defineProps(["item"]);

const { isOrderFinished } = useOrders();
const { updateOrderProducts, destroyOrderProducts, saveNewOrderProduct } = useOrderProducts();
const { getProducts } = useProducts();
const { setErrors } = useErrors();

const isNew = () => !!props.item.isNew;

const canEditProduct = () => isNew() || (!isOrderFinished.value && !props.item.stockSum);
const canEditQty     = () => isNew() || !isOrderFinished.value;

const onClickSave = async (item) => {
    if (!item.product_id) {
        alert("Vyberte produkt.");
        return;
    }
    try {
        if (item.isNew) {
            await saveNewOrderProduct(item);
        } else {
            await updateOrderProducts(item);
        }
    } catch (e) {
        setErrors(e);
    }
};

const onClickDelete = async (item) => {
    if (item.isNew) {
        // Remove temp item from state directly via index
        const { state } = useOrderProducts();
        const idx = state.orderProducts.findIndex(p => p.id === item.id);
        if (idx !== -1) state.orderProducts.splice(idx, 1);
        return;
    }
    if (item.stockSum) {
        alert("Položka už bola expedovaná.");
        return;
    }
    await destroyOrderProducts(item);
};

const onChangeProduct = (productId) => {
    const product = getProducts.value.find(p => p.id == productId);
    if (product) {
        props.item.price       = product.active_price ?? product.sale_price ?? product.price ?? props.item.price;
        props.item.unit_value  = product.unit_value;
        props.item.product_vat = product.vat;
        props.item.thumb       = product.thumb;
        props.item.name        = product.name;
    }
};
</script>

<template>
    <tr class="tr" :class="item.isNew ? 'bg-blue-50' : ''">
        <td class="tbody_td flex items-center font-semibold">
            <div class="mr-3 overflow-hidden rounded-full border-2 border-gray-200">
                <img v-if="item.thumb" :src="item.thumb" :alt="item.name" class="object-cover h-8 w-8" />
            </div>
            <select
                class="shadow w-full appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                id="product"
                v-model="item.product_id"
                :disabled="!canEditProduct()"
                @change="onChangeProduct(item.product_id)">
                <option :value="null" disabled>— vybrať produkt —</option>
                <option v-for="product in getProducts" :key="product.id" :value="product.id">
                    {{ product.name }}
                </option>
            </select>
        </td>

        <td class="tbody_td">
            <input v-model="item.quantity" type="number" class="w-16" :min="item.min_order ?? 1" :disabled="!canEditQty()" />
            <span class="ml-2">{{ item.unit_value }}</span>
        </td>

        <td class="tbody_td">
            <input v-model="item.price" type="number" step=".01" class="w-24" :disabled="!canEditQty()" />
        </td>

        <td class="tbody_td">
            <span v-if="item.product_vat">{{ item.product_vat + " %" }}</span>
        </td>

        <td class="tbody_td">
            <span v-if="item.price">{{ formatDecimal(item.price * item.quantity) + " €" }}</span>
        </td>

        <td class="tbody_td">
            <div v-if="!item.isNew" class="flex flex-col gap-1 text-xs">
                <div class="w-fit rounded-full px-2 font-semibold" :class="item.stockSum ? 'bg-green-200' : 'bg-red-200'">
                    {{ item.stockSum }} / {{ item.shipping_required_quantity ?? item.quantity }}
                </div>
                <div class="text-gray-500">
                    {{ item.shipping_percentage ?? 0 }} %, ostáva {{ item.shipping_remaining_quantity ?? 0 }}
                </div>
            </div>
            <span v-else class="text-xs text-gray-400">nová</span>
        </td>

        <td class="tbody_td">
            <input v-if="!item.isNew" v-model="item.storno" type="number" :max="item.quantity" class="w-14" :disabled="isOrderFinished" />
            <span v-else class="text-xs text-gray-400">—</span>
        </td>

        <td class="tbody_td">
            <panel-dropdown>
                <div @click="onClickSave(item)"
                    class="cursor-pointer p-2 hover:bg-indigo-300 border-b-2 border-gray-200">
                    Uložiť
                </div>
                <div @click="onClickDelete(item)"
                    class="cursor-pointer p-2 hover:bg-indigo-300 border-b-2 border-gray-200">
                    Zmazať
                </div>
            </panel-dropdown>
        </td>
    </tr>
</template>
