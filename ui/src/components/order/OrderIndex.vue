<script setup>
import BaseLayout from '../layout/BaseLayout.vue';
import { computed, onMounted, ref, watch } from "vue";
import axiosInstance from "../../axiosInstance";
import { useRoute, useRouter } from "vue-router";
import useOrders from "../../store/StoreOrders";
import useQuery from "../../store/StoreQuery";
import usePaginator from "../../store/StorePaginator";
import { PAGE_ORDER } from "../../constants";
import { storeToRefs } from "pinia";
import { useUsers as useUser } from "../../store/StoreUsers";
import tableRowOrders from "./component/tableRowOrders.vue";
import filterOrder from "./FilterOrder.vue";
import iconStar from "../../components/icons/star.vue";
import templateOrder from "../../models/templateOrder"
import PaginationComponent from "../plugins/pagination.vue";
import plus from '../icons/plus.vue';
import PageHeader from '../layout/page/pageHeader.vue';
import PageBottom from '../layout/page/pageBottom.vue';
import spinnerTable from '../icons/spinnerTable.vue';



const ordersStore = useOrders();
const { getOrders } = storeToRefs(ordersStore);
const { fetchOrders, setPaginator, fetchMarkSelected } = ordersStore;
const { getUserCan, getUser } = storeToRefs(useUser());
const { getQuery } = storeToRefs(useQuery());
const { getPaginator } = storeToRefs(usePaginator());
const route = useRoute();
const router = useRouter();

// Strana a filtre sú v adrese (/objednavky?strana=2&customer=…), aby návrat
// z detailu, F5 aj späť v prehliadači otvorili zoznam tam, kde bol.
// Filtre z adresy načíta FilterOrder, stranu nastavíme pred prvým načítaním.
const page = Number(route.query.strana) || 1;
ordersStore.url = page > 1 ? `${PAGE_ORDER.URL}?page=${page}` : PAGE_ORDER.URL;

const ROUTE_PARAMS = { "status=": "status", "shippedAt=": "shippedAt", "bySearchInput=": "customer", "searchByProduct=": "product" };

const listQuery = () => {
      const query = {};
      for (const item of getQuery.value) {
            const split = item.indexOf("=") + 1;
            const key = item.slice(0, split);
            if (key === "isMarked=") query.marked = 1;
            else if (ROUTE_PARAMS[key]) query[ROUTE_PARAMS[key]] = decodeURIComponent(item.slice(split));
            else query.filter = key.slice(0, -1);
      }
      const current = getPaginator.value?.current_page;
      if (current > 1) query.strana = current;
      return query;
};

watch([getQuery, () => getPaginator.value?.current_page], () => {
      if (route.name !== PAGE_ORDER.ROUTE) return;
      router.replace({ query: listQuery() }).then(() => { ordersStore.listRoute = route.fullPath; });
}, { deep: true });

// Objednávky z e-mailu čakajúce na obsluhu (len super-admin, rovnako ako AI import).
const emailDrafts = ref(0);
const isSuperAdmin = computed(() => Boolean(getUser.value?.roles?.includes("super-admin")));

onMounted(async () => {
      ordersStore.listRoute = route.fullPath;
      fetchOrders();
      document.title = "Zoznam objednávok";
      if (isSuperAdmin.value) {
            try {
                  const { data } = await axiosInstance.get("/email-order-drafts", { params: { tab: "open" } });
                  emailDrafts.value = data.counts?.open ?? 0;
            } catch {
                  // Počítadlo je len pomôcka — zoznam objednávok funguje aj bez neho.
            }
      }
});

const paginatorUrl = (url) => {
      setPaginator(url);
};

const template = () => {
      return {
            page_header: {
                  title: 'Zoznam objednávok',
                  buttonLink: getUserCan.value['orders.create']
                      ? { name: 'Nová objednávka', spinner: true, link: '/objednavky/create', icon: 'plus' }
                      : null,
            },
            page_bottom: {
                  // buttonLink: { name: 'Späť', spinner: true, link: 'products.index', icon: 'arrow-left' },
                  // buttonSubmit: { name: 'Uložiť', spinner: true, link: 'products.create', icon: 'plus' }
            },
            buttonLink: ordersStore.buttonLink,
      }
}
</script>

<template>

      <BaseLayout>

            <template #main>

                  <div class="page-body col-span-12">
                        <PageHeader :item="template().page_header" />

                        <router-link v-if="isSuperAdmin" to="/objednavky/z-emailu"
                              class="mb-3 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-semibold"
                              :class="emailDrafts ? 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'">
                              ✉ Prijaté e-maily
                              <span v-if="emailDrafts" class="rounded-full bg-amber-500 px-1.5 text-xs text-white">{{ emailDrafts }}</span>
                        </router-link>

                        <filterOrder />

                        <div class="flex flex-col">

                              <div class="-my-2">

                                    <div class="py-2 block min-w-0">
                                          <div class="overflow-x-auto shadow border-b border-gray-200 sm:rounded-lg">
                                                <!-- Pod md sa tabuľka zobrazuje ako zoznam kariet (riadky v tableRowOrders). -->
                                                <table
                                                      class="block min-w-full divide-y divide-gray-200 border border-gray-200 md:table">
                                                      <thead class="thead hidden md:table-header-group">
                                                            <tr>
                                                                  <th class="thead_th whitespace-nowrap">
                                                                        Číslo
                                                                  </th>
                                                                  <th class="thead_th">
                                                                        <button type="button" @click="fetchMarkSelected"
                                                                              class="mx-auto flex h-8 w-8 items-center justify-center rounded hover:bg-white/10"
                                                                              :aria-pressed="!!ordersStore.statement.markSelected"
                                                                              :aria-label="ordersStore.statement.markSelected ? 'Zobraziť všetky objednávky' : 'Zobraziť len označené'"
                                                                              :title="ordersStore.statement.markSelected ? 'Zobraziť všetky objednávky' : 'Zobraziť len označené'">
                                                                              <icon-star :status="ordersStore.statement.markSelected" />
                                                                        </button>
                                                                  </th>
                                                                  <th class="thead_th">Zákazník</th>
                                                                  <th class="thead_th !text-right">Cena</th>
                                                                  <th class="thead_th">Status</th>
                                                                  <th class="thead_th">Expedícia</th>
                                                                  <th class="thead_th"><span class="sr-only">Akcie</span></th>
                                                            </tr>
                                                      </thead>
                                                      <tbody class="block md:table-row-group">

                                                            <spinnerTable v-if="ordersStore.isLoading" />

                                                            <tableRowOrders v-else v-for="order in getOrders"
                                                                  :key="order.id" :order="templateOrder(order)">
                                                            </tableRowOrders>
                                                      </tbody>
                                                </table>
                                          </div>
                                    </div>
                              </div>
                        </div>
                        <!-- Legenda farieb riadkov -->
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600">
                              <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm border border-gray-300 bg-green-50"></span>Neotvorená</span>
                              <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm border border-gray-300 bg-blue-50"></span>Vybavená</span>
                              <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm border border-gray-300 bg-gray-200"></span>Stornovaná</span>
                        </div>
                        <PageBottom :item="template().page_bottom" />
                        <pagination-component @setUrl="paginatorUrl"></pagination-component>
                  </div>
            </template>

      </BaseLayout>

</template>
