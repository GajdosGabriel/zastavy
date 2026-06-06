<script setup>
import { onMounted, watch } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import PageBottom from "../layout/page/pageBottom.vue";
import PaginationComponent from "../plugins/pagination.vue";
import FilterPanel from "./FilterPanel.vue";
import tableRow from "./component/tableRowUsers.vue";
import spinnerTable from "../icons/spinnerTable.vue";
import loadingStore from "../../store/StoreLoading";
import useAdminUsers from "../../store/StoreAdminUsers";
import useQuery from "../../store/StoreQuery";

const { fetchUsers, setPaginator, getUsers, resetUrl } = useAdminUsers();
const { resetQuery, getQueryStringUrl } = useQuery();

onMounted(() => {
    resetUrl();
    resetQuery();
    fetchUsers();
});

const paginatorUrl = (url) => {
    setPaginator(url);
};

watch(getQueryStringUrl, () => {
    resetUrl();
    fetchUsers();
});

const template = () => {
    return {
        page_header: {
            title: "Pouzivatelia",
            buttonLink: {},
        },
        page_bottom: {},
    };
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="template().page_header" />

                <FilterPanel />

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th">ID</th>
                                <th class="thead_th">Pouzivatel</th>
                                <th class="thead_th">Kontakt</th>
                                <th class="thead_th">Zakaznik</th>
                                <th class="thead_th">Objednavky</th>
                                <th class="thead_th">Role</th>
                                <th class="thead_th">Vytvoreny</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <spinnerTable v-if="loadingStore.isLoading" />
                            <table-row v-else v-for="user in getUsers" :user="user" :key="user.id" />
                        </tbody>
                    </table>
                </div>

                <PageBottom :item="template().page_bottom" />
                <PaginationComponent @setUrl="paginatorUrl" />
            </div>
        </template>
    </BaseLayout>
</template>
