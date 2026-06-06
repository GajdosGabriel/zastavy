<script setup>
import { computed } from "vue";
import PanelDropdown from "../../layout/PanelDropdown.vue";
import useProducts from "../../../store/StoreProducts";
import Checkmark from "../../icons/checkmark.vue";
import CheckmarkLight from "../../icons/checkmarkLight.vue";
import spinnerIcon from "../../icons/spinnerTable.vue";
import routerLinkComponent from "../../layout/RouterLinkComponent.vue";


const emits = defineEmits(['checkmark']);
const props = defineProps(["product"]);

const { destroyProduct, updateProduct, setProduct, getProducts, getProduct, getStatment } = useProducts();

const deleteProduct = (url) => {
    destroyProduct(url);
};

const onClickUpdate = async () => {

    props.product.snipper = true
    setProduct({ ...props.product, published: !props.product.published });
    await updateProduct();
};


const dropdownItems = computed(() => {
    const items = []

    if (!Object.keys(props.product.endpoints).length) {
        return []
    }

    const permissions = props.product.permissions || {};
    const canUpdate = permissions.update ?? Boolean(props.product.endpoints.update);
    const canDelete = permissions.delete ?? Boolean(props.product.endpoints.destroy);

    if (canUpdate) {
        items.push({
            label: 'Upraviť',
            to: '/products/' + props.product.id + '/edit'
        })
    }

if (canDelete) {
    items.push({
        label: "Zmazať",
        onClick: () => destroyProduct(props.product.endpoints.destroy)
    })
}

return items
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
                            {{
                                product.description.substring(0, 25)
                            }}...</span>
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
            <div class="text-sm text-gray-900">{{ product.active_price }} €</div>
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
            <span v-if="product.discount">{{ product.discount }} % </span><br />
            <span v-if="product.discount">{{ product.sale_price }} €</span>
        </td>

        <td class="px-6 py-4 whitespace-nowrap">
            {{ product.quantity }}
            {{ product.unit_value }}
        </td>

        <td class="px-6 py-4 whitespace-nowrap flex justify-between">
            <panel-dropdown :items="dropdownItems" />
        </td>
    </tr>
</template>
