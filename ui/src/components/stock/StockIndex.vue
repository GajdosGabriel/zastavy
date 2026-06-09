<script setup lang="ts">
import { onMounted } from "vue";
import useStocks from "../../store/StoreStocks";
import useUser from "../../store/StoreUsers";
import PaginationComponent from "../plugins/pagination.vue";
import tableRow from "./component/tableRow.vue";
import filterPanel from "./FilterPanel.vue";
import BaseLayout from "../layout/BaseLayout.vue";
import templateStock from "../../models/templateStock";
import PageHeader from '../layout/page/pageHeader.vue';
import PageBottom from '../layout/page/pageBottom.vue';
import spinnerTable from '../icons/spinnerTable.vue';
import loadingStore from '../../store/StoreLoading';

const { state, fetchStocks, setPaginator, getStocks } = useStocks();
const { getUserCan } = useUser();

onMounted(() => {
    fetchStocks();
});

const paginatorUrl = (url) => {
    setPaginator(url);
};

const template = () => {
    return {
        page_header: {
            title: 'Sklad - pohyby',
            buttonLink: getUserCan.value['stocks.create']
                ? { name: 'Nový príjem', spinner: true, link: '/sklad/create', icon: 'plus' }
                : null
        },
        page_bottom: {
            // buttonLink: { name: 'Späť', spinner: true, link: 'products.index', icon: 'arrow-left' },
            // buttonSubmit: { name: 'Uložiť', spinner: true, link: 'products.create', icon: 'plus' }
        },
        buttonLink: state.buttonLink,
    }
}

</script>


<template>
    <BaseLayout>
        <!-- Content for the default slot -->
        <template #main>

            <div class="page-body col-span-12">
                <PageHeader :item="template().page_header" />

                <filterPanel></filterPanel>

                <div class="flex flex-col">
                    <div class="-my-2 sm:-mx-6 lg:-mx-8">
                        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                            <div class="shadow border-b border-gray-200 sm:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-500 border-2 border-gray-400">
                                    <thead class="thead">
                                        <tr>
                                            <th class="thead_th flex items-center">
                                                Dodací list
                                            </th>
                                            <th class="thead_th">Produkt</th>
                                            <th class="thead_th">Expedícia</th>
                                            <th class="thead_th">Odberateľ</th>
                                            <th class="thead_th">Počet</th>
                                            <th class="thead_th">Voľné</th>
                                            <th class="thead_th">Panel</th>
                                        </tr>
                                    </thead>
                                    <tbody class="">
                                        <spinnerTable v-if="loadingStore.isLoading" />
                                        <tableRow v-else v-for="item in getStocks" :key="item.id"
                                            :item="templateStock(item)">
                                        </tableRow>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <PageBottom :item="template().page_bottom" />
                <pagination-component @setUrl="paginatorUrl" />
            </div>
        </template>
    </BaseLayout>
</template>
