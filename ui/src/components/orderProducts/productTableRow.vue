<script setup>
import itemRow from "./itemRow.vue";
import useOrders from "../../store/StoreOrders";
import { formatDecimal } from "../../models/functions";
import useOrderProducts from "../../store/StoreOrderProducts";
import useProducts from "../../store/StoreProducts";
import PanelDropdown from "../layout/PanelDropdown.vue";

const props = defineProps(["item"]);

const { isOrderFinished } = useOrders();
const { updateOrderProducts, destroyOrderProducts, findProduct } = useOrderProducts();
const { state, getProducts } = useProducts();

const onClickSave = async (item) => {
    await updateOrderProducts(item);
};

const onClickDelete = async (item) => {
    if (item.stockSum) {
        alert("Položka už bola expedovaná.");
        return;
    }
    await destroyOrderProducts(item);
};

const onChangeSelect = (productId) => {
    let product = state.products.find((item) => item.id == productId);
    state.order.orderProducts.push(product)

};

</script>


<template>
    <tr class="tr">
        <td class="tbody_td flex items-center font-semibold">
            <div class="mr-3 overflow-hidden rounded-full border-2 border-gray-200">
                <img v-if="item.thumb" :src="item.thumb" :alt="item.name" class="object-cover h-8 w-8" />
            </div>
            <select
                class="shadow w-full appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                id="product" v-model="item.product_id" :disabled="isOrderFinished || item.stockSum"
                @change="onChangeSelect(item.id)">
                <option v-for="product in getProducts" :key="product.id" :value="product.id"
                    :selected="item.id == product.id">
                    {{ product.name }}
                </option>
            </select>

            <!-- <span>{{ item.name }}</span> -->
        </td>
        <td class="tbody_td">
            <input v-model="item.quantity" type="number" class="w-16" :min="item.min_order" :disabled="isOrderFinished" />
            <span class="ml-2">{{ item.unit_value }}</span>
        </td>

        <td class="tbody_td">
            <input v-model="item.price" type="number" step=".01" class="w-24" :disabled="isOrderFinished" />
        </td>
        <td class="tbody_td">
            <span v-if="item.product_vat">{{ item.product_vat + " %" }}</span>
        </td>
        <td class="tbody_td">
            <span v-if="item.price">{{
                formatDecimal(item.price * item.quantity) + " €"
                }}</span>
        </td>
        <td class="tbody_td">
            <div class="flex flex-col gap-1 text-xs">
                <div class="w-fit rounded-full px-2 font-semibold" :class="item.stockSum ? 'bg-green-200' : 'bg-red-200'">
                    {{ item.stockSum }} / {{ item.shipping_required_quantity ?? item.quantity }}
                </div>
                <div class="text-gray-500">
                    {{ item.shipping_percentage ?? 0 }} %, ostáva {{ item.shipping_remaining_quantity ?? 0 }}
                </div>
            </div>
        </td>
        <td class="tbody_td">
            <input v-model="item.storno" type="number" :max="item.quantity" class="w-14" :disabled="isOrderFinished" />
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
