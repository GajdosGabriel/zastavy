<script setup>
import BaseLayout from '../layout/BaseLayout.vue';
import { onMounted } from "vue";
import useOrders from "../../store/StoreOrders";
import tableRowOrders from "./component/tableRowOrders.vue";
import filterOrder from "./FilterOrder.vue";
import iconStar from "../../components/icons/star.vue";
import templateOrder from "../../models/templateOrder"
import PaginationComponent from "../plugins/pagination.vue";
import plus from '../icons/plus.vue';
import PageHeader from '../layout/page/pageHeader.vue';
import PageBottom from '../layout/page/pageBottom.vue';
import spinnerTable from '../icons/spinnerTable.vue';



const { state, fetchOrders, setPaginator, getOrders, fetchMarkSelected } = useOrders();

onMounted(() => {
      fetchOrders();
      document.title = "Zoznam objednávok";
});

const paginatorUrl = (url) => {
      setPaginator(url);
};

const template = () => {
      return {
            page_header: {
                  title: 'Zoznam objednávok',
                  buttonLink: { name: 'Nová objednávka', spinner: true, link: '/objednavky/create', icon: 'plus' }
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

            <template #main>

                  <div class="page-body col-span-12">
                        <PageHeader :item="template().page_header" />

                        <filterOrder />

                        <div class="flex flex-col">

                              <div class="-my-2 sm:-mx-6 lg:-mx-8">

                                    <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                                          <div class="overflow-x-auto shadow border-b border-gray-200 sm:rounded-lg">
                                                <table
                                                      class="min-w-full divide-y divide-gray-200 border border-gray-200">
                                                      <thead class="thead">
                                                            <tr>
                                                                  <th class="thead_th whitespace-nowrap">
                                                                        Číslo
                                                                  </th>
                                                                  <th class="thead_th">
                                                                        <icon-star
                                                                              :status="state.statement.markSelected"
                                                                              @click="fetchMarkSelected"
                                                                              class="mx-auto" />
                                                                  </th>
                                                                  <th class="thead_th">Zákazník</th>
                                                                  <th class="thead_th">Cena</th>
                                                                  <th class="thead_th">Status</th>
                                                                  <th class="thead_th">Expedícia</th>
                                                                  <th class="thead_th">Panel</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody class="">

                                                            <spinnerTable v-if="state.isLoading" />

                                                            <tableRowOrders v-else v-for="order in getOrders"
                                                                  :key="order.id" :order="templateOrder(order)">
                                                            </tableRowOrders>
                                                      </tbody>
                                                </table>
                                          </div>
                                    </div>
                              </div>
                        </div>
                        <PageBottom :item="template().page_bottom" />
                        <pagination-component @setUrl="paginatorUrl"></pagination-component>
                  </div>
            </template>

      </BaseLayout>

</template>
