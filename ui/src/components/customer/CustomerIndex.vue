<script setup lang="ts">
import { onMounted, watch, ref } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import useCustomers from "../../store/StoreCustomers";
import useQuery from "../../store/StoreQuery";
import PaginationComponent from "../plugins/pagination.vue";
import FilterPanel from "./FilterPanel.vue";
import chevronComponent from "../../components/icons/chevron.vue";
import customerCreate from "./CustomerCreate.vue";
import iconStar from "../../components/icons/star.vue";
import tableRow from "./component/tableRowCustomers.vue";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import templateCustomer from "../../models/templateCustomer";
import PageHeader from '../layout/page/pageHeader.vue';
import PageBottom from '../layout/page/pageBottom.vue';
import spinnerTable from '../icons/spinnerTable.vue';
import loadingStore from '../../store/StoreLoading';



const {
    state,
    fetchCustomers,
    setPaginator,
    getCustomers,
} = useCustomers();

const { state: q, setQuery, getQuery, getQueryStringUrl, removeQuery, resetQuery } = useQuery();

const sortById = ref<boolean>(false);
const sortByOrders = ref<boolean>(false);
const iconStatus = ref<boolean>(false);

watch(getQueryStringUrl, () => {
    fetchCustomers();
});

watch(sortById, () => {
    sortById.value
        ? setQuery("sortById=true")
        : removeQuery("sortById=true");
});

watch(sortByOrders, () => {
    sortByOrders.value
        ? setQuery("sortByOrders=true")
        : removeQuery("sortByOrders=true");
});

watch(iconStatus, () => {
    iconStatus.value
        ? setQuery("isMarked=true")
        : removeQuery("isMarked=true");
});

onMounted(() => {
    fetchCustomers();
});

const paginatorUrl = (url: string): void => {
    setPaginator(url);
};
const onClickMark = (): void => {
    iconStatus.value = !iconStatus.value;
};
templateCustomer(state);

const template = () => {
    return {
        page_header: {
            title: 'Zákazníci',
            buttonLink: { name: 'Nový zákazník', spinner: true, link: '/zakaznici/create', icon: 'plus' }
        },
        page_bottom: {
            buttonBottomLeft: { name: 'Späť', spinner: true, link: '/zakaznici', icon: 'arrow-left' }
        },
        buttonLink: state.buttonLink,
    }
}

const buttonTopRight = { name: 'Nový zákazník', spinner: true, link: '/zakaznici/create', icon: 'plus' }
const buttonSubmit = { name: 'Uložiť', spinner: true }
const buttonBottomLeft = { name: 'Späť', spinner: true, link: '/zakaznici', icon: 'arrow-left' }

</script>

<template>

    <BaseLayout>

        <template #main>


            <div class="page-body col-span-12">
                <PageHeader :item="template().page_header" />

                <filter-panel />

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="thead">
                        <tr>
                            <th class="thead_th flex items-center cursor-pointer" @click="sortById = !sortById">
                                ID
                                <chevron-component :icon="sortById" />
                            </th>
                            <th class="thead_th">
                                <icon-star :status="iconStatus" @click="onClickMark" class="mx-auto" />
                            </th>

                            <th class="thead_th cursor-pointer" @click="sortByOrders = !sortByOrders">
                                <div class="flex">
                                    Objednávky
                                    <chevron-component :icon="sortByOrders" />
                                </div>
                            </th>
                            <th class="thead_th">Firma</th>
                            <th class="thead_th">Adresa</th>
                            <th class="thead_th">ICO</th>
                            <th class="thead_th">Panel</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <spinnerTable v-if="loadingStore.isLoading" />
                        <table-row v-else v-for="customer in getCustomers" :customer="customer" :key="customer.id" />
                    </tbody>
                </table>
                <PageBottom :item="template().page_bottom" />
                <pagination-component @setUrl="paginatorUrl"></pagination-component>
            </div>
        </template>
    </BaseLayout>
</template>
