<script setup lang="ts">
import { onMounted } from "vue";
import useUser from "../../store/StoreUsers.js";
import useCheckouts from "../../store/StoreCheckouts.js";
import useCustomer from "../../store/StoreCustomers.ts";
import useProduct from "../../store/StoreProducts.ts";
import useStock from "../../store/StoreStocks.js";
import useNavigation from "../../store/StoreNavigation.js";
import mainNavigationDropdown from "./navigationMainDropdown.vue";
import kosik from "../icons/kosik.vue";
import badge from "../plugins/badge.vue";
import { APP_NAME, Page } from "../../constants.ts";
import useOrder from "../../store/StoreOrders.js";


const onClickItem = (item: Page) => {
      switch (item.ROUTE) {
            case 'orders.index': {
                  useOrder().setPaginator(item.URL);
                  break;
            }
            case 'customers.index': {
                  useCustomer().setPaginator(item.URL);;
                  break;
            }

            case 'products.index': {
                  useProduct().setPaginator(item.URL);
                  break;
            }
            case 'stocks.index': {
                  useStock().setPaginator(item.URL);
                  break;
            }
            case 'public.contactUs': {
                  break;
            }
            default:
                  console.warn(`Unknown route: ${item.ROUTE}`); // Logovanie neznámej cesty
      }
};

const { getCarts, getlocalStorage } = useCheckouts();
const { fetchUser, getUser } = useUser();
const { getMainNavigation } = useNavigation();


onMounted(() => {
      fetchUser();
      getlocalStorage();
});

</script>

<template>

      <nav class="bg-gray-300 border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                  <div class="flex justify-between h-16">
                        <div class="flex">
                              <div class="shrink-0 flex items-center">
                                    <router-link :to="{ name: 'public.index' }"
                                          class="nav_link_active router-link-exact-active nav_link bg-blue-900 rounded-md">
                                          <span class="text-gray-200 font-semibold p-2">
                                                {{ APP_NAME }}
                                          </span>
                                    </router-link>
                              </div>

                              <ul class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">

                                    <li v-for="page in getMainNavigation" :key="page.ROUTE" class="nav_link">
                                          <router-link :to="{ name: page.ROUTE }" @click="onClickItem(page)"
                                                class="nav_link">
                                                {{ page.NAME }}

                                                <badge v-if="getUser?.order?.isConfirmed && page.ICON === 'badge'"
                                                      :kosik="{ value: getUser.order.isConfirmed, title: 'Počet položiek', class: null }"
                                                      class="ml-1" />
                                          </router-link>
                                    </li>

                              </ul>

                              <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">

                              </div>
                        </div>

                        <div class="sm:flex sm:items-center sm:ml-6 flex">
                              <main-navigation-dropdown />

                              <router-link class="nav_link" :to="{ name: 'public.cart.index' }">
                                    <kosik class="text-blue-700" />
                                    <badge :kosik="{ value: getCarts ? getCarts?.length : 0, title: 'Počet položiek', class: null }"
                                          class="mr-2" />
                                    Košík
                              </router-link>

                              <router-link :to="{ name: 'public.login.index' }" class="nav_link" v-if="!getUser.isAuth">
                                    Vstup
                              </router-link>

                              <!-- <router-link :to="{ name: 'public.register.index' }" class="nav_link"
                                    v-if="!getUser.isAuth">
                                    Register
                              </router-link> -->
                        </div>
                  </div>
            </div>
      </nav>

</template>
