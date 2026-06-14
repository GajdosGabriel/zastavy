<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import useUser from "../../store/StoreUsers.js";
import useCheckouts from "../../store/StoreCheckouts.js";
import useCustomer from "../../store/StoreCustomers.ts";
import useProduct from "../../store/StoreProducts.ts";
import useStock from "../../store/StoreStocks.js";
import useAnnouncement from "../../store/StoreAnnouncements.js";
import useNavigation from "../../store/StoreNavigation.js";
import mainNavigationDropdown from "./navigationMainDropdown.vue";
import NavKosikLink from "../checkout/NavKosikLink.vue";
import badge from "../plugins/badge.vue";
import { APP_NAME, Page } from "../../constants.ts";
import useOrder from "../../store/StoreOrders.js";

const mobileMenuOpen = ref(false);
const route = useRoute();
const router = useRouter();

const isNavActive = (page: Page): boolean => {
    try {
        const resolved = router.resolve({ name: page.ROUTE });
        return route.path.startsWith(resolved.path) && resolved.path !== '/';
    } catch {
        return false;
    }
};

const onClickItem = (item: Page) => {
      mobileMenuOpen.value = false;
      switch (item.ROUTE) {
            case 'dashboard.index': break;
            case 'orders.index': useOrder().resetUrl(); break;
            case 'customers.index': useCustomer().setPaginator(item.URL); break;
            case 'products.index': useProduct().setPaginator(item.URL); break;
            case 'stocks.index': useStock().setPaginator(item.URL); break;
            case 'announcements.index': useAnnouncement().setPaginator(item.URL); break;
            case 'public.contactUs': break;
            default: console.warn(`Unknown route: ${item.ROUTE}`);
      }
};

const { getlocalStorage } = useCheckouts();
const { fetchUser, getUser } = useUser();
const { getMainNavigation } = useNavigation();

onMounted(() => {
      if (localStorage.getItem('authToken') && !getUser.value?.isAuth) {
            fetchUser();
      }
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
                                                class="nav_link" :class="{ nav_link_active: isNavActive(page) }">
                                                {{ page.NAME }}
                                                <badge v-if="getUser?.order?.isConfirmed && page.ICON === 'badge'"
                                                      :kosik="{ value: getUser.order.isConfirmed, title: 'Počet položiek', class: null }"
                                                      class="ml-1" />
                                          </router-link>
                                    </li>
                              </ul>
                        </div>

                        <div class="flex items-center gap-1 sm:ml-6">
                              <main-navigation-dropdown />

                              <NavKosikLink />

                              <!-- Hamburger – only mobile -->
                              <button
                                    class="sm:hidden ml-1 rounded-md p-2 text-gray-600 hover:bg-gray-200 hover:text-gray-900 focus:outline-none"
                                    @click="mobileMenuOpen = !mobileMenuOpen"
                                    aria-label="Menu"
                              >
                                    <svg v-if="!mobileMenuOpen" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                              </button>
                        </div>
                  </div>
            </div>

            <!-- Mobile menu panel -->
            <div v-show="mobileMenuOpen" class="sm:hidden border-t border-gray-400 bg-gray-200">
                  <ul class="px-4 py-2 space-y-1">
                        <li v-for="page in getMainNavigation" :key="page.ROUTE">
                              <router-link
                                    :to="{ name: page.ROUTE }"
                                    @click="onClickItem(page)"
                                    class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-300"
                                    :class="{ 'font-bold text-blue-900': isNavActive(page) }"
                              >
                                    {{ page.NAME }}
                                    <badge v-if="getUser?.order?.isConfirmed && page.ICON === 'badge'"
                                          :kosik="{ value: getUser.order.isConfirmed, title: 'Počet položiek', class: null }"
                                          class="ml-1" />
                              </router-link>
                        </li>
                  </ul>
            </div>
      </nav>
</template>
