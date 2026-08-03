<script setup>
import { computed } from "vue";
import PanelDropdown from "../../layout/PanelDropdown.vue";
import { useProducts } from "../../../store/StoreProducts";
import Checkmark from "../../icons/checkmark.vue";
import CheckmarkLight from "../../icons/checkmarkLight.vue";
import spinnerIcon from "../../icons/spinnerTable.vue";
import routerLinkComponent from "../../layout/RouterLinkComponent.vue";
import { htmlToText } from "../../../models/html";


const emits = defineEmits(['checkmark']);
const props = defineProps(["product"]);

const { destroyProduct, updateProduct, setProduct, getStatment } = useProducts();

// Produkt nemá jednu cenu — ukazujeme rozsah cez varianty.
const priceLabel = computed(() => {
    const from = props.product.price_from;
    const to = props.product.price_to;

    if (from === null || from === undefined) return '—';
    if (to !== null && to !== undefined && Number(to) > Number(from)) {
        return `${Number(from).toFixed(2)} – ${Number(to).toFixed(2)} €`;
    }
    return `${Number(from).toFixed(2)} €`;
});

const onClickUpdate = async () => {
    props.product.snipper = true;
    setProduct({ ...props.product, published: !props.product.published });
    await updateProduct();
};

const actionMap = {
    update: { to: '/products/' + props.product.id + '/edit' },
    delete: { onClick: () => destroyProduct(props.product.endpoints.destroy) },
};

const dropdownItems = computed(() => {
    if (!Object.keys(props.product.endpoints).length) return [];

    return Object.entries(props.product.permissions || {})
        .filter(([key, perm]) => perm.allowed && actionMap[key])
        .map(([key, perm]) => ({ label: perm.label, ...actionMap[key] }));
})

</script>

<template>

    <tr class="hover:bg-gray-50">
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
                <div class="flex-shrink-0 h-10 w-10">
                    <img class="h-10 w-10 rounded-full" :src="product.thumb" :alt="product.name" />
                </div>
                <div class="ml-4">
                    <routerLinkComponent :permission="product.endpoints.show">
                        <div class="text-sm font-medium text-gray-900">
                            {{ product.name }}
                        </div>
                    </routerLinkComponent>
                    <!-- <router-link :to="{
                        name: 'products.show',
                        params: {
                            productId: product.id
                        },
                    }">
                        <div class="text-sm font-medium text-gray-900">
                            {{ product.name }}
                        </div> -->
                    <div v-show="product.code || product.description" class="text-sm text-gray-500">
                        <span v-if="product.code" class="mr-2 font-semibold text-gray-700">{{ product.code }}</span>
                        <span v-if="product.description">
                            {{ htmlToText(product.description).substring(0, 25) }}...</span>
                    </div>
                    <!-- </router-link> -->
                </div>
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm text-gray-900" @click="$emit('checkmark')">
                <Checkmark v-if="product.quickMark" />
                <CheckmarkLight v-else="product.quickMark" />
                <span class="bg-yellow-200 px-2 rounded-md mr-2 shadow-sm" v-for="category in product.categories"
                    :key="category.id">
                    {{ category.name }}
                </span>
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm text-gray-900">{{ priceLabel }}</div>
            <div class="text-sm text-gray-500">DPH {{ product.vat }} %</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">

            <!-- <spinnerIcon v-if="getStatment.spinner.row" /> -->
            <span @click="onClickUpdate()"
                class="cursor-pointer px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"
                :class="[
                    product.published
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800',
                ]">

                <spinnerIcon v-if="product.snipper" />

                <span v-else>{{ product.published ? "ÁNO" : "STOP" }}</span>
            </span>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <span v-if="product.variants_count"
                class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                {{ product.variants_count }}×
            </span>
            <span v-else class="text-xs font-semibold text-red-600">bez variantu</span>
        </td>

        <td class="px-6 py-4 whitespace-nowrap">
            <span v-if="product.total_quantity !== null">
                {{ product.total_quantity }} {{ product.unit_value }}
            </span>
            <span v-else class="text-xs text-gray-400">nesleduje sa</span>
        </td>

        <td class="px-6 py-4 whitespace-nowrap flex justify-between">
            <panel-dropdown :items="dropdownItems" />
        </td>
    </tr>
</template>
