<script setup>
import { computed } from "vue";
import useOrders from "../../store/StoreOrders";
import { formatDecimal } from "../../models/functions";
import useOrderProducts from "../../store/StoreOrderProducts";
import { useProducts } from "../../store/StoreProducts";
import PanelDropdown from "../layout/PanelDropdown.vue";
import useErrors from "../../store/StoreErrors";
import { storeToRefs } from "pinia";

const props = defineProps(["item"]);

const { isOrderFinished } = storeToRefs(useOrders());
const { updateOrderProducts, destroyOrderProducts, saveNewOrderProduct, removeOrderProduct } = useOrderProducts();
const { getProducts } = storeToRefs(useProducts());
const { setErrors } = useErrors();

const isNew = () => !!props.item.isNew;

const canEditProduct = () => isNew() || (!isOrderFinished.value && !props.item.stockSum);
const canEditQty     = () => isNew() || !isOrderFinished.value;

const onClickSave = async (item) => {
    if (item.isNew && !item.product_variant_id) {
        alert("Vyberte variant.");
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
        // Remove temp (unsaved) item from the store
        removeOrderProduct(item.id);
        return;
    }
    if (item.stockSum) {
        alert("Položka už bola expedovaná.");
        return;
    }
    await destroyOrderProducts(item);
};

// Do výberu ide každý variant zvlášť — cena aj min. odber patria variantu.
const variantRows = computed(() =>
    (getProducts.value ?? []).flatMap((product) =>
        (product.variants ?? []).map((variant) => ({
            id: variant.id,
            label: variant.name ? `${product.name} — ${variant.name}` : product.name,
            product,
            variant,
        }))
    )
);

const onChangeVariant = (variantId) => {
    const row = variantRows.value.find(r => r.id == variantId);
    if (!row) return;

    props.item.product_id         = row.product.id;
    props.item.product_variant_id = row.variant.id;
    props.item.variant_name       = row.variant.name;
    props.item.price              = row.variant.active_price ?? row.variant.price ?? props.item.price;
    props.item.unit_value         = row.product.unit_value;
    props.item.product_vat        = row.product.vat;
    props.item.thumb              = row.variant.thumb ?? row.product.thumb;
    props.item.name               = row.product.name;
    props.item.quantity           = row.variant.min_order ?? 1;
};
</script>

<template>
    <tr class="tr" :class="item.isNew ? 'bg-blue-50' : ''">
        <td class="tbody_td flex items-center font-semibold">
            <div class="mr-3 overflow-hidden rounded-full border-2 border-gray-200">
                <img v-if="item.thumb" :src="item.thumb" :alt="item.name" class="object-cover h-8 w-8" />
            </div>
            <div class="w-full">
                <select v-if="item.isNew"
                    class="shadow w-full appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="product"
                    v-model="item.product_variant_id"
                    :disabled="!canEditProduct()"
                    @change="onChangeVariant(item.product_variant_id)">
                    <option :value="null" disabled>— vybrať variant —</option>
                    <option v-for="row in variantRows" :key="row.id" :value="row.id">
                        {{ row.label }}
                    </option>
                </select>
                <template v-else>
                    <div>{{ item.name }}</div>
                    <div v-if="item.variant_name" class="text-xs font-medium text-blue-700">
                        {{ item.variant_name }}
                    </div>
                </template>
            </div>
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
                <div v-if="item.isNew || !item.stockSum"
                    @click="onClickDelete(item)"
                    class="cursor-pointer p-2 hover:bg-indigo-300 border-b-2 border-gray-200">
                    Zmazať
                </div>
            </panel-dropdown>
        </td>
    </tr>
</template>
